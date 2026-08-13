#!/usr/bin/env python3
"""Compose un mot en lettrage à partir de la planche de glyphes.

La planche porte ses propres mesures : chaque glyphe et chaque motif y est un groupe
<g id="glyphe-…" | "decor-…" data-boite data-ancre data-cadre> posé par planche_index.py.
Il ne reste donc ici que la mise en page : les lettres sont enfilées sur un cercle (ou sur
une ligne quand l'ouverture est nulle), leur ancre — milieu de la boîte, sur la ligne de
base — amenée sur le tracé. C'est la construction de logo.svg, issu de la même planche.

    ./scripts/lettrage.py Gallery -a 24 --decor    # angle négatif : arc creux
    ./scripts/lettrage.py Gallery -a 0             # droit, sans décor
    ./scripts/lettrage.py --menu --titres --accueil
"""

import argparse
import math
import re
import subprocess
import tempfile
from pathlib import Path

RACINE = Path(__file__).resolve().parent.parent
PLANCHE = RACINE / 'camagru/public/images/elements/planche.svg'
ELEMENTS = RACINE / 'camagru/public/images/elements'

LISERE = ('fill:#ffffff;stroke:#ffffff;stroke-width:42.66;'
          'stroke-linejoin:round;stroke-linecap:round')
INTERLETTRE = 4.0      # jeu entre deux lettres, en unités de la planche
ESPACE = 150.0         # avance d'un blanc de mot

# Semis du logo, relevé sur Camagru : motif, position le long du mot (en fraction de la
# demi-ouverture), hauteur au-dessus de la ligne de base (en hauteur de capitale),
# échelle et inclinaison. Les deux moustaches sont posées à part, sur la ligne médiane.
SEMIS = [
    ('fleur2', -1.03,  1.36, 0.315, -14),
    ('coeur',   0.23,  1.24, 0.272,  14),
    ('fleur1',  0.94, -0.02, 0.234,  10),
    ('fleur3', -0.84, -0.22, 0.214,  -6),
]
MOUSTACHE = 0.5        # échelle des deux éclats latéraux
DEGAGEMENT = 120.0     # jeu entre la dernière lettre et l'éclat, en unités de la planche

# mot, fichier, ouverture de l'arc en degrés (négatif = arc creux)
MENU = [
    ('Home', 'home', 0), ('Gallery', 'gallery', 0), ('Friends', 'friends', 0),
    ('Preferences', 'preferences', 0), ('Profile', 'profile', 0), ('Admin', 'admin', 0),
    ('Login', 'login', 0), ('Sign up', 'signup', 0), ('Logout', 'logout', 0),
]
TITRES = [                 # même ouverture que l'enseigne : les titres ont sa forme
    ('Gallery', 'gallery'), ('Friends', 'friends'), ('Preferences', 'preferences'),
    ('Profile', 'profile'), ('Admin', 'admin'), ('Login', 'login'),
    ('Sign up', 'signup'), ('403', '403'), ('404', '404'),   # logout ne rend pas de page
]
OUVERTURE_TITRE = 29.87

# Liens de l'accueil : ni droits comme le menu, ni décorés comme les titres. Chacun sa
# courbure, creuse ou bombée, pour que la page d'accueil ne ressemble à aucune autre.
ACCUEIL = [
    ('Gallery', 'gallery', 24), ('Friends', 'friends', -16), ('Preferences', 'preferences', 13),
    ('Profile', 'profile', -22), ('Admin', 'admin', 18), ('Login', 'login', 20),
    ('Sign up', 'signup', -14), ('Logout', 'logout', 15),
]


def planche():
    """{caractère: glyphe}, {nom: motif} lus dans la planche indexée."""
    source = PLANCHE.read_text(encoding='utf-8')
    motif = (r'<g id="(?:glyphe|decor)-[^"]*" data-(char|decor)="(.+?)" data-boite="([^"]*)"'
             r' data-ancre="([^"]*)" data-cadre="([^"]*)">\n(.*?)\n</g><!-- (?:glyphe|decor) -->')

    glyphes, decors = {}, {}
    for genre, cle, boite, ancre, cadre, paths in re.findall(motif, source, re.S):
        (glyphes if genre == 'char' else decors)[cle] = {
            'boite': [float(v) for v in boite.split(',')],
            'ancre': [float(v) for v in ancre.split(',')],
            'cadre': cadre,
            'paths': re.findall(r'<path.*?/>', paths, re.S),
        }
    if not glyphes:
        raise SystemExit('planche non indexée : passer scripts/planche_index.py')
    return glyphes, decors


def groupe(pose, cadre, paths, tete=''):
    return ('  <g%s transform="%s">\n    <g transform="%s">\n%s\n    </g>\n  </g>'
            % (tete, pose, cadre, '\n'.join('      ' + p for p in paths)))


def composer(mot, ouverture, glyphes, decors=None):
    """SVG du mot : arc d'ouverture |ouverture| degrés, ou ligne droite si elle est nulle."""
    lettres = [glyphes[c] if c != ' ' else None for c in mot]
    avances = [ESPACE if None in (g, d) else (g['boite'][2] + d['boite'][2]) / 2 + INTERLETTRE
               for g, d in zip(lettres, lettres[1:])]

    longueur = sum(avances)
    parcours, courant = [], 0.0
    for avance in [0.0] + avances:
        courant += avance
        parcours.append(courant - longueur / 2)

    creux = ouverture < 0
    rayon = longueur / math.radians(abs(ouverture)) if ouverture else 0.0

    def pose(distance):
        """Place l'ancre sur le tracé, la lettre tournée de la tangente."""
        if not rayon:
            return 'translate(%.4f,0)' % distance
        angle = math.degrees(distance / rayon)
        return ('rotate(%.4f) translate(0,%.4f)'
                % (-angle if creux else angle, rayon if creux else -rayon))

    lisere, lettrage = [], []
    for i, (caractere, glyphe) in enumerate(zip(mot, lettres)):
        if glyphe is None:
            continue
        assise = '%s translate(%.4f,%.4f)' % (pose(parcours[i]), *[-v for v in glyphe['ancre']])
        lisere.append(groupe(assise, glyphe['cadre'],
                             [re.sub(r'style="[^"]*"', 'style="%s"' % LISERE, p)
                              for p in glyphe['paths'] if 'fill:#ffffff' not in p]))
        lettrage.append(groupe(assise, glyphe['cadre'], glyphe['paths'],
                               ' id="lettre-%s-%d"' % (caractere, i)))

    corps = ('<g id="lisere">\n%s\n</g>\n<g id="lettrage">\n%s\n</g>'
             % ('\n'.join(lisere), '\n'.join(lettrage)))
    if decors and rayon:
        corps += '\n<g id="decor">\n%s\n</g>' % semer(mot, longueur, rayon, creux,
                                                     glyphes, decors)

    return ('<?xml version="1.0" encoding="UTF-8" standalone="no"?>\n'
            '<svg version="1.1" width="4000" height="4000" viewBox="-2000 -%.4f 4000 4000"\n'
            '   xmlns="http://www.w3.org/2000/svg" xmlns:svg="http://www.w3.org/2000/svg"\n'
            '   role="img" aria-label="%s">\n  <defs />\n%s\n</svg>\n'
            % (rayon + 2000, mot, corps))


def famille(motif):
    """Classe d'animation d'un motif : les décors d'un même genre réagissent ensemble."""
    if motif in ('gauche', 'droite'):
        return 'moustache moustache-' + motif
    return 'fleur' if motif.startswith('fleur') else motif


def semer(mot, longueur, rayon, creux, glyphes, decors):
    """Éclats latéraux sur la ligne médiane du corps, puis fleurs et cœur autour du mot."""
    capitale = max(glyphes[c]['boite'][3] for c in mot if c.isupper()) if any(
        c.isupper() for c in mot) else glyphes[mot[0]]['boite'][3]
    corps_x = min(glyphes[c]['boite'][3] for c in mot if c.islower()) if any(
        c.islower() for c in mot) else capitale / 2

    sens = -1 if creux else 1

    def poser(motif, distance, hauteur, echelle, inclinaison, tangente=False):
        """distance : le long du tracé ; hauteur : au-dessus de la ligne de base.

        La classe d'animation va sur une enveloppe sans transformation : le CSS lui pose
        transform-box: fill-box, qui déplacerait la pose si elle était sur le même groupe.
        """
        r = rayon + hauteur
        angle = math.degrees(distance / r)
        redresse = '' if tangente else ' rotate(%.4f)' % (-sens * angle)
        pose = groupe(
            'rotate(%.4f) translate(0,%.4f)%s rotate(%.4f) scale(%.6f) translate(%.4f,%.4f)'
            % (sens * angle, sens * -r, redresse, inclinaison, echelle,
               *[-v for v in decors[motif]['ancre']]),
            decors[motif]['cadre'], decors[motif]['paths'])
        return '  <g class="decor-anim %s">\n%s\n  </g>' % (famille(motif), pose)

    largeur_eclat = decors['gauche']['boite'][2] * MOUSTACHE
    ecart = longueur / 2 + largeur_eclat / 2 + DEGAGEMENT

    poses = [poser('gauche', -ecart, corps_x / 2, MOUSTACHE, 0, tangente=True),
             poser('droite', ecart, corps_x / 2, MOUSTACHE, 0, tangente=True)]
    poses += [poser(motif, part * longueur / 2, hauteur * capitale, echelle, inclinaison)
              for motif, part, hauteur, echelle, inclinaison in SEMIS]
    return '\n'.join(poses)


def ajuster(svg, echelle, marge=8.0):
    """Recadre le viewBox sur le dessin et fixe la taille en pixels."""
    with tempfile.NamedTemporaryFile('w', suffix='.svg', encoding='utf-8', delete=False) as f:
        f.write(svg)
        chemin = f.name
    mesures = subprocess.run(['inkscape', '--query-all', chemin],
                             capture_output=True, text=True, check=True).stdout
    Path(chemin).unlink()

    x, y, largeur, hauteur = [float(v) for v in mesures.splitlines()[0].split(',')[1:]]
    vue = re.search(r'viewBox="(\S+) (\S+) ', svg)
    boite = (float(vue.group(1)) + x - marge, float(vue.group(2)) + y - marge,
             largeur + 2 * marge, hauteur + 2 * marge)

    return re.sub(r'width="\S+" height="\S+" viewBox="[^"]*"',
                  'width="%.4f" height="%.4f" viewBox="%.4f %.4f %.4f %.4f"'
                  % (boite[2] / echelle, boite[3] / echelle, *boite), svg, count=1)


def ecrire(mot, ouverture, chemin, echelle, glyphes, decors):
    chemin.parent.mkdir(parents=True, exist_ok=True)
    svg = composer(mot, ouverture, glyphes, decors)
    chemin.write_text(ajuster(svg, echelle), encoding='utf-8')
    print('%-14s %s' % (mot, chemin.relative_to(RACINE)))


def main():
    parser = argparse.ArgumentParser(description=__doc__,
                                     formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument('mot', nargs='?')
    parser.add_argument('-a', '--angle', type=float, default=20,
                        help="ouverture de l'arc en degrés ; 0 pour un mot droit")
    parser.add_argument('-e', '--echelle', type=float, default=5.5,
                        help='unités de la planche par pixel')
    parser.add_argument('-o', '--out', help='fichier produit, relatif à images/elements')
    parser.add_argument('--decor', action='store_true', help='moustaches, fleurs et cœur')
    parser.add_argument('--menu', action='store_true', help='entrées du menu, droites et nues')
    parser.add_argument('--titres', action='store_true', help='titres de page, arqués et décorés')
    parser.add_argument('--accueil', action='store_true', help="liens de l'accueil, arqués et nus")
    args = parser.parse_args()

    glyphes, decors = planche()
    if args.menu:
        for mot, nom, ouverture in MENU:
            ecrire(mot, ouverture, ELEMENTS / 'menu' / (nom + '.svg'), 8.0, glyphes, None)
    if args.titres:
        for mot, nom in TITRES:
            ecrire(mot, OUVERTURE_TITRE, ELEMENTS / 'titres' / (nom + '.svg'), 4.0,
                   glyphes, decors)
    if args.accueil:
        for mot, nom, ouverture in ACCUEIL:
            ecrire(mot, ouverture, ELEMENTS / 'accueil' / (nom + '.svg'), 5.0, glyphes, None)
    if args.mot:
        nom = args.out or (args.mot.replace(' ', '').lower() + '.svg')
        ecrire(args.mot, args.angle, ELEMENTS / nom, args.echelle, glyphes,
               decors if args.decor else None)
    elif not (args.menu or args.titres or args.accueil):
        parser.error('donner un mot, --menu ou --titres')


if __name__ == '__main__':
    main()
