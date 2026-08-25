#!/usr/bin/env python3
"""Fill a running Camagru with people, montages and the traffic between them.

Nothing is written to the database: the script signs up over HTTP, reads the
confirmation link out of MailHog, logs in, uploads a portrait to the photobooth
with its overlays, then likes, comments, befriends and reports the way a reader
would. What it leaves behind is therefore exactly what the application allows.

The portraits come from assets/seed, drawn by scripts/portraits.py, and their
portraits.json says where each face sits, so an overlay lands on the head.

    ./scripts/seed.py                 # five people on http://localhost:8080
    ./scripts/seed.py -n 3 --url http://localhost:8080
"""

import argparse
import html
import json
import random
import re
import sys
import time
from pathlib import Path

import requests
from PIL import Image

RACINE = Path(__file__).resolve().parent.parent
SEED = RACINE / 'assets/seed'
FILTRES = RACINE / 'camagru/public/filtres'

MONTAGE = (800, 600)   # config/settings.php photobooth.width / height

# username, email, password, model avatar, notifications kept on
PERSONNES = [
    ('alice',   'alice@camagru.local',   'Sunflower42',  'modele_03.png', 'all'),
    ('bruno',   'bruno@camagru.local',   'Longboard42',  'modele_08.png', 'all'),
    ('carmen',  'carmen@camagru.local',  'Espresso42',   'modele_10.png', 'friends'),
    ('dimitri', 'dimitri@camagru.local', 'Nightowl42',   'modele_15.png', 'none'),
    ('elena',   'elena@camagru.local',   'Peppermint42', 'modele_21.png', 'all'),
    ('farid',   'farid@camagru.local',   'Blueprint42',  'modele_06.png', 'all'),
    ('gwen',    'gwen@camagru.local',    'Driftwood42',  'modele_19.png', 'friends'),
]

REGLAGES = {
    'all':     ['notify_comment', 'notify_friend_request',
                'notify_friend_accepted', 'notify_friend_removed'],
    'friends': ['notify_friend_request', 'notify_friend_accepted', 'notify_friend_removed'],
    'none':    [],
}

# overlay: anchor in the portrait, width against the head, how much of the
# overlay hangs below that anchor (0.5 centres it)
POSES = {
    'cat-ears':      ('crane', 1.10, 0.34),
    'kitten-ears':   ('crane', 1.05, 0.30),
    'mouse-ears':    ('crane', 1.15, 0.28),
    'dog-ears':      ('crane', 1.25, 0.30),
    'rose-crown':    ('crane', 1.12, 0.40),
    'blue-crown':    ('crane', 1.12, 0.40),
    'golden-crown':  ('crane', 1.05, 0.38),
    'purple-horns':  ('crane', 1.30, 0.26),
    'ram-horns':     ('crane', 1.35, 0.30),
    'red-horns':     ('crane', 1.20, 0.24),
    'heart-glasses': ('yeux',  1.02, 0.50),
    'dog-face':      ('visage', 1.20, 0.42),
    'pastel-cat':    ('visage', 1.15, 0.44),
    'hearts':        ('coin',  0.42, 0.50),
}

COMMENTAIRES = [
    'The ears suit you.',
    'This one belongs on the wall.',
    'Best montage of the week, no contest.',
    'How did you line that up so well?',
    'The colours work perfectly here.',
    'Stealing this filter, thanks.',
    'That crown was made for you.',
    'Laughed out loud at this one.',
    'Clean framing.',
    'You have to teach me that trick.',
    'This is the one.',
    'Absolutely unhinged, love it.',
]

# who asks whom; accepted pairs first, the rest stays pending on purpose
AMITIES = [(0, 1, True), (0, 2, True), (1, 3, True), (2, 4, False), (3, 0, False)]


class Erreur(Exception):
    pass


class Client:
    """One reader, with their cookie jar and their csrf token."""

    def __init__(self, base, nom=''):
        self.base = base.rstrip('/')
        self.nom = nom
        self.session = requests.Session()
        self.jeton = None

    def page(self, chemin):
        reponse = self.session.get(self.base + chemin, timeout=15)
        if reponse.status_code >= 400:
            raise Erreur(f'GET {chemin} -> {reponse.status_code}')
        trouve = re.search(r'name="csrf_token" value="([^"]+)"', reponse.text)
        if trouve:
            self.jeton = trouve.group(1)
        return reponse.text

    def poste(self, chemin, donnees=None, fichiers=None, formulaire='/'):
        """Posts with the token of the moment; a stale one is fetched again."""
        for essai in (1, 2):
            if self.jeton is None:
                self.page(formulaire)
            charge = dict(donnees or {}, csrf_token=self.jeton)
            reponse = self.session.post(self.base + chemin, data=charge, files=fichiers,
                                        timeout=30, allow_redirects=True)
            if reponse.status_code != 403:
                return reponse
            self.jeton = None
            if essai == 2:
                raise Erreur(f'POST {chemin} -> 403')
        raise Erreur(f'POST {chemin} unreachable')


def attendre(url, patience=20):
    fin = time.time() + patience
    while time.time() < fin:
        try:
            requests.get(url, timeout=3)
            return True
        except requests.RequestException:
            time.sleep(0.5)
    return False


def lien_de_confirmation(mailhog, email, patience=15):
    """The sign-up link, read from the mailbox rather than forged."""
    fin = time.time() + patience
    while time.time() < fin:
        boite = requests.get(f'{mailhog}/api/v2/messages', timeout=5).json()
        for message in boite.get('items', []):
            entetes = message['Content']['Headers']
            if email not in ' '.join(entetes.get('To', [])):
                continue
            if 'Confirm' not in ' '.join(entetes.get('Subject', [])):
                continue
            # raw body: quoted-printable decoding would eat the "=" of ?token=
            trouve = re.search(r'href="([^"]*/verify\?token=[^"]+)"',
                               message['Content']['Body'])
            if trouve:
                return html.unescape(trouve.group(1))
        time.sleep(0.4)
    return None


RATIOS = {}


def ratio(slug):
    """Height over width of an overlay: the server keeps it when scaling."""
    if slug not in RATIOS:
        largeur, hauteur = Image.open(FILTRES / f'{slug}.png').size
        RATIOS[slug] = hauteur / largeur
    return RATIOS[slug]


def calques(geometrie, choix, hasard):
    """Turns 'a crown on that head' into the fractions the photobooth stores."""
    poses = []
    for slug in choix:
        ancre, part_tete, recouvrement = POSES[slug]
        largeur_tete = geometrie['tete'][0]

        if ancre == 'coin':
            x, y = hasard.choice([(0.17, 0.24), (0.83, 0.26), (0.2, 0.74)])
        else:
            x, y = geometrie[ancre]

        largeur = largeur_tete * part_tete
        hauteur = largeur * MONTAGE[0] * ratio(slug) / MONTAGE[1]

        poses.append({
            'o': slug,
            'x': round(min(max(x, 0.02), 0.98), 4),
            'y': round(min(max(y + hauteur * (recouvrement - 0.5), 0.02), 0.98), 4),
            'w': round(min(max(largeur, 0.08), 1.6), 4),
        })
    return poses


def inscrire(client, mailhog, username, email, motdepasse):
    """Sign-up, mailbox, confirmation, login: the whole front door."""
    client.page('/register')
    reponse = client.poste('/register',
                           {'username': username, 'email': email, 'password': motdepasse},
                           formulaire='/register')

    if 'already exists' not in reponse.text:
        lien = lien_de_confirmation(mailhog, email)
        if lien is None:
            raise Erreur(f'no confirmation mail for {email}')
        client.session.get(lien, timeout=15)

    client.page('/login')
    reponse = client.poste('/login', {'email': email, 'password': motdepasse},
                           formulaire='/login')
    if '/logout' not in reponse.text:
        raise Erreur(f'login refused for {email}')


def actionnables(page):
    """Montages this reader may act on, and whether the like is already theirs.

    Their own montages carry a delete form and no like one, so they drop out.

    @return dict of montage id => already liked
    """
    cartes = re.findall(r'id="montage-(\d+)"(.*?)(?=id="montage-|\Z)', page, re.S)

    return {int(identite): '>Unlike<' in corps
            for identite, corps in cartes if '/gallery/like' in corps}


def parcourir(client, pages_max=20):
    """The whole wall, page after page: the oldest montages live on the last one.

    @return dict of montage id => (page it sits on, already liked)
    """
    cibles = {}
    page = 1
    while page <= pages_max:
        contenu = client.page(f'/gallery?page={page}')
        for identite, aime in actionnables(contenu).items():
            cibles[identite] = (page, aime)
        if f'/gallery?page={page + 1}"' not in contenu:
            break
        page += 1

    return cibles


def identifiant(client, username):
    """The account id, read off the friend search like a reader would."""
    page = client.page(f'/friends?q={username}')
    for bloc in re.findall(r'<li class="friend">.*?</li>', page, re.S):
        if re.search(r'friend-name">%s<' % re.escape(username), bloc):
            trouve = re.search(r'name="user" value="(\d+)"', bloc)
            if trouve:
                return int(trouve.group(1))
    return None


def peupler(options):
    hasard = random.Random(options.graine)
    portraits = json.loads((SEED / 'portraits.json').read_text())
    disponibles = sorted(portraits)
    hasard.shuffle(disponibles)

    filtres = sorted(POSES)
    personnes = PERSONNES[:options.nombre]
    clients, comptes = [], []

    print(f'Camagru <- {options.url}\n')

    for index, (username, email, motdepasse, avatar, reglage) in enumerate(personnes):
        client = Client(options.url, username)
        inscrire(client, options.mailhog, username, email, motdepasse)

        client.page('/profile')
        client.poste('/profile/avatar', {'model': avatar}, formulaire='/profile')

        client.page('/preferences')
        client.poste('/preferences/notifications',
                     {colonne: 'on' for colonne in REGLAGES[reglage]},
                     formulaire='/preferences')

        poses = 0
        for _ in range(options.montages):
            if not disponibles:
                break
            nom = disponibles.pop()
            choix = hasard.sample(filtres, hasard.randint(1, 2))
            client.page('/photobooth')
            with (SEED / nom).open('rb') as fichier:
                reponse = client.poste(
                    '/photobooth/capture',
                    {'layers': json.dumps(calques(portraits[nom], choix, hasard))},
                    fichiers={'file': (nom, fichier, 'image/jpeg')},
                    formulaire='/photobooth')
            if 'Montage saved' in reponse.text:
                poses += 1
            else:
                erreur = re.search(r'<p class="error">([^<]+)', reponse.text)
                print(f'  ! {username}: {erreur.group(1) if erreur else "montage refused"}')

        clients.append(client)
        comptes.append((username, email, motdepasse, poses))
        print(f'  {username:<8} {poses} montage(s), avatar {avatar}')

    social(clients, personnes, hasard, options)
    return comptes


def social(clients, personnes, hasard, options):
    liens = attentes = 0
    for demandeur, destinataire, acceptee in AMITIES:
        if demandeur >= len(clients) or destinataire >= len(clients):
            continue
        cible = identifiant(clients[demandeur], personnes[destinataire][0])
        if cible is None:
            continue
        reponse = clients[demandeur].poste('/friends/request', {'user': cible},
                                           formulaire='/friends')
        if not acceptee:
            attentes += 'Request sent' in reponse.text
            continue

        source = identifiant(clients[destinataire], personnes[demandeur][0])
        reponse = clients[destinataire].poste('/friends/accept', {'user': source},
                                              formulaire='/friends')
        liens += 'are now friends' in reponse.text

    aimes = ecrits = signales = 0
    for client in clients:
        cibles = parcourir(client)
        # liking is a toggle: a second run would take back what the first one gave
        libres = [montage for montage, (_, aime) in cibles.items() if not aime]
        hasard.shuffle(libres)

        for montage in libres[:3]:
            client.poste('/gallery/like', {'id': montage, 'page': cibles[montage][0]},
                         formulaire='/gallery')
            aimes += 1
        # spread them: commenting the same two montages leaves most of the wall bare
        for montage in hasard.sample(sorted(cibles), min(2, len(cibles))):
            client.poste('/gallery/comment',
                         {'id': montage, 'page': cibles[montage][0],
                          'comment': hasard.choice(COMMENTAIRES)},
                         formulaire='/gallery')
            ecrits += 1

    # two readers flag the same montage: the admin desk needs something to answer
    if len(clients) >= 3:
        juges = clients[-2:]
        vues = [parcourir(client) for client in juges]
        for cible in sorted(set(vues[0]) & set(vues[1]))[:1]:
            for client, cibles in zip(juges, vues):
                reponse = client.poste('/gallery/report',
                                       {'id': cible, 'page': cibles[cible][0]},
                                       formulaire='/gallery')
                signales += 'Montage reported' in reponse.text

    print(f'\n  {liens} friendships, {attentes} pending request(s), '
          f'{aimes} likes, {ecrits} comments, {signales} report(s)')


def main():
    analyseur = argparse.ArgumentParser(description=__doc__.split('\n')[0])
    analyseur.add_argument('-n', '--nombre', type=int, default=5,
                           help='people to create (max %d)' % len(PERSONNES))
    analyseur.add_argument('-m', '--montages', type=int, default=2,
                           help='montages per person')
    analyseur.add_argument('-g', '--graine', type=int, default=7)
    analyseur.add_argument('--url', default='http://localhost:8080')
    analyseur.add_argument('--mailhog', default='http://localhost:8025')
    options = analyseur.parse_args()
    options.nombre = max(1, min(options.nombre, len(PERSONNES)))

    if not (SEED / 'portraits.json').is_file():
        sys.exit('No portrait in assets/seed: run scripts/portraits.py first.')
    if not attendre(options.url):
        sys.exit(f'{options.url} does not answer: is make up done?')
    if not attendre(options.mailhog + '/api/v2/messages'):
        sys.exit(f'{options.mailhog} does not answer: MailHog carries the sign-up links.')

    try:
        comptes = peupler(options)
    except Erreur as erreur:
        sys.exit(f'\nSeeding stopped: {erreur}')

    largeur = max(len(email) for _, email, _, _ in comptes)
    print('\n  ' + 'user'.ljust(9) + 'email'.ljust(largeur + 2) + 'password')
    print('  ' + '-' * (9 + largeur + 14))
    for username, email, motdepasse, _ in comptes:
        print(f'  {username.ljust(9)}{email.ljust(largeur + 2)}{motdepasse}')
    print(f'\n  {options.url}/login\n')


if __name__ == '__main__':
    main()
