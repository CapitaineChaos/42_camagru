#!/usr/bin/env python3
"""Cut the filter sheets into motifs, vectorise each one, rasterise the result.

Sources are the sheets in scripts/sources. Each motif is traced by vectoriser.py,
kept as an editable SVG in assets/filtres, then rendered to the PNG the photobooth
superimposes (GD reads no SVG). Slugs match the catalogue in config/settings.php.

    ./scripts/filtres.py              # the whole sheet set
    ./scripts/filtres.py cat-ears
"""

import subprocess
import sys
from pathlib import Path

import numpy as np
from PIL import Image

sys.path.insert(0, str(Path(__file__).resolve().parent))
from vectoriser import vectoriser  # noqa: E402

RACINE = Path(__file__).resolve().parent.parent
SOURCES = Path(__file__).resolve().parent / 'sources'
SVG = RACINE / 'assets/filtres'
PNG = RACINE / 'camagru/public/filtres'

MARGE = 6          # px kept around a motif once trimmed
VIDE = 4           # a row/column with fewer opaque pixels counts as empty
HAUTEUR_MIN = 8    # px, below this a strip is a leftover rule, not a motif
FILET = 5          # px, thickness under which a full-length component is a rule
AMONT = 6          # upsampling of a motif before tracing, small sheets need it
LARGEUR = 900      # px, rendered width of a filter

MOTIF_SEUL = {'amont': 5, 'tolerance': 0.3, 'blob': 150, 'flou': 0.1, 'part': 0.004,
              'ecart': 40}

# sheet: cutting mode, slugs in reading order, and pixels shaved off the sides
PLANCHES = {
    'grille.png': ('grille-3x3', [
        'kitten-ears', 'dog-ears', 'mouse-ears',
        'rose-crown', 'blue-crown', 'golden-crown',
        'purple-horns', 'ram-horns', 'red-horns',
    ], 0, {'motifs': {'kitten-ears': {'part': 0.08, 'centrer': True}}}),
    # the card of this sheet keeps a darker edge: shave it before anything else

    'chat.png': ('entier', ['cat-ears']),
    # one motif per sheet, captured cleanly: no cutting, light hand on the tracing
    'hearts.png': ('entier', ['hearts'], 0, MOTIF_SEUL),
    'pastel-cat.png': ('entier', ['pastel-cat'], 0, MOTIF_SEUL),
    'heart-glasses.png': ('entier', ['heart-glasses'], 0, MOTIF_SEUL),
    'dog-face.png': ('entier', ['dog-face'], 0,
                     {'amont': 2, 'tolerance': 0.5, 'blob': 400, 'flou': 0.15,
                      'part': 0.004, 'ecart': 24}),
}


def detourer(image):
    """Background out: flood from the whole border, following its own shades.

    A sheet is a screenshot: white page, tinted card, rounded corners. Seeding the
    four corners is not enough, so the fill accepts any colour already seen on the
    border and spreads while neighbouring pixels stay close to one another.
    """
    # a sheet may already carry alpha: flatten it, the background goes by colour
    image = Image.alpha_composite(Image.new('RGBA', image.size, (255, 255, 255, 255)),
                                  image.convert('RGBA'))
    donnees = np.asarray(image, dtype=np.int32).copy()
    hauteur, largeur = donnees.shape[:2]
    couleurs = donnees[..., :3]

    bord = ([(0, x) for x in range(largeur)] + [(hauteur - 1, x) for x in range(largeur)]
            + [(y, 0) for y in range(hauteur)] + [(y, largeur - 1) for y in range(hauteur)])

    # only the shades that dominate the border are background: a motif touching the
    # edge would otherwise let the fill eat its way through the drawing
    familles = []
    for y, x in bord:
        teinte = couleurs[y, x]
        for famille in familles:
            if ((teinte - famille['teinte']) ** 2).sum() < 1600:
                famille['compte'] += 1
                break
        else:
            familles.append({'teinte': teinte, 'compte': 1})

    teintes = [famille['teinte'] for famille in familles
               if famille['compte'] >= 0.08 * len(bord)]

    fond = np.zeros((hauteur, largeur), dtype=bool)
    pile = []
    for point in bord:
        if not fond[point]:
            fond[point] = True
            pile.append(point)

    while pile:
        y, x = pile.pop()
        courante = couleurs[y, x]
        for dy, dx in ((1, 0), (-1, 0), (0, 1), (0, -1)):
            v, u = y + dy, x + dx
            if not (0 <= v < hauteur and 0 <= u < largeur) or fond[v, u]:
                continue
            voisine = couleurs[v, u]
            if ((voisine - courante) ** 2).sum() < 300 \
                    and any(((voisine - teinte) ** 2).sum() < 2500 for teinte in teintes):
                fond[v, u] = True
                pile.append((v, u))

    donnees[fond, 3] = 0
    return Image.fromarray(donnees.astype(np.uint8), mode='RGBA')


def retirer_filets(image):
    """Drops the rules of the screenshot: a component as long as the sheet, a few px thick."""
    donnees = np.asarray(image, dtype=np.uint8).copy()
    opaque = donnees[..., 3] > 40
    hauteur, largeur = opaque.shape

    vu = np.zeros_like(opaque)
    for depart in zip(*np.nonzero(opaque)):
        if vu[depart]:
            continue
        pile, composante = [depart], []
        vu[depart] = True
        while pile:
            y, x = pile.pop()
            composante.append((y, x))
            for dy, dx in ((1, 0), (-1, 0), (0, 1), (0, -1)):
                v, u = y + dy, x + dx
                if 0 <= v < hauteur and 0 <= u < largeur and opaque[v, u] and not vu[v, u]:
                    vu[v, u] = True
                    pile.append((v, u))

        ys = [p[0] for p in composante]
        xs = [p[1] for p in composante]
        etendue_y = max(ys) - min(ys) + 1
        etendue_x = max(xs) - min(xs) + 1
        filet = (etendue_y > 0.9 * hauteur and etendue_x <= FILET) \
            or (etendue_x > 0.9 * largeur and etendue_y <= FILET)
        if filet:
            for y, x in composante:
                donnees[y, x, 3] = 0

    return Image.fromarray(donnees, mode='RGBA')


def eroder(image, pas):
    """Shaves the fringe left where the motif met the background of the sheet.

    Cancelled on a motif made of hairlines: eroding those leaves nothing at all.
    """
    donnees = np.asarray(image, dtype=np.uint8).copy()
    opaque = donnees[..., 3] > 40
    depart = int(opaque.sum())
    for _ in range(pas):
        interieur = opaque.copy()
        interieur[1:, :] &= opaque[:-1, :]
        interieur[:-1, :] &= opaque[1:, :]
        interieur[:, 1:] &= opaque[:, :-1]
        interieur[:, :-1] &= opaque[:, 1:]
        opaque = interieur

    if opaque.sum() < 0.7 * depart:
        return image

    donnees[~opaque, 3] = 0
    return Image.fromarray(donnees, mode='RGBA')


def epurer(image, part=0.01):
    """Drops the leftovers of the screenshot: components too small to be a motif part."""
    donnees = np.asarray(image, dtype=np.uint8).copy()
    opaque = donnees[..., 3] > 40
    hauteur, largeur = opaque.shape
    total = int(opaque.sum())

    vu = np.zeros_like(opaque)
    for depart in zip(*np.nonzero(opaque)):
        if vu[depart]:
            continue
        pile, composante = [depart], []
        vu[depart] = True
        while pile:
            y, x = pile.pop()
            composante.append((y, x))
            for dy, dx in ((1, 0), (-1, 0), (0, 1), (0, -1)):
                v, u = y + dy, x + dx
                if 0 <= v < hauteur and 0 <= u < largeur and opaque[v, u] and not vu[v, u]:
                    vu[v, u] = True
                    pile.append((v, u))
        if len(composante) < part * total:
            for y, x in composante:
                donnees[y, x, 3] = 0

    return Image.fromarray(donnees, mode='RGBA')


def centrer(image):
    """Pads the side that needs it so the mass of the motif sits in the middle.

    A detail sticking out on one side (the hairs of an ear) drags the bounding box
    with it; placing by the centre of the image would then hang the motif off-axis.
    """
    opaque = np.asarray(image)[..., 3] > 40
    poids = opaque.sum(axis=0).astype(float)
    if poids.sum() == 0:
        return image

    milieu = float((poids * np.arange(len(poids))).sum() / poids.sum())
    ecart = int(round(image.width / 2 - milieu))
    if abs(ecart) < 2:
        return image

    large = Image.new('RGBA', (image.width + 2 * abs(ecart), image.height), (0, 0, 0, 0))
    large.paste(image, (2 * abs(ecart) if ecart > 0 else 0, 0))
    return large


def rogner(image):
    boite = image.getbbox()
    if boite is None:
        return None
    gauche = max(0, boite[0] - MARGE)
    haut = max(0, boite[1] - MARGE)
    droite = min(image.width, boite[2] + MARGE)
    bas = min(image.height, boite[3] + MARGE)
    return image.crop((gauche, haut, droite, bas))


def tranches(occupation):
    """Runs of non-empty rows (or columns) in a projection."""
    blocs, debut = [], None
    for i, compte in enumerate(occupation):
        if compte > VIDE and debut is None:
            debut = i
        elif compte <= VIDE and debut is not None:
            blocs.append((debut, i))
            debut = None
    if debut is not None:
        blocs.append((debut, len(occupation)))
    return blocs


def decouper(image, mode, groupes=None):
    """Motifs of a sheet, in reading order; groupes merges consecutive strips."""
    if mode == 'entier':
        return [rogner(image)]

    opaque = np.asarray(image)[..., 3] > 40

    if mode == 'bandes':
        bandes = [(haut, bas) for haut, bas in tranches(opaque.sum(axis=1))
                  if bas - haut >= HAUTEUR_MIN]
        if groupes:
            fusionnees, rang = [], 0
            for taille in groupes:
                paquet = bandes[rang:rang + taille]
                fusionnees.append((paquet[0][0], paquet[-1][1]))
                rang += taille
            bandes = fusionnees
        return [rogner(image.crop((0, haut, image.width, bas))) for haut, bas in bandes]

    morceaux = []
    hauteur, largeur = opaque.shape
    for rang in range(3):
        haut, bas = rang * hauteur // 3, (rang + 1) * hauteur // 3
        for colonne in range(3):
            gauche = colonne * largeur // 3
            droite = (colonne + 1) * largeur // 3
            morceaux.append(rogner(image.crop((gauche, haut, droite, bas))))
    return morceaux


def produire(nom_planche, voulus):
    reglage = PLANCHES[nom_planche]
    mode, slugs = reglage[0], reglage[1]
    marge = reglage[2] if len(reglage) > 2 else 0
    reglages = reglage[3] if len(reglage) > 3 else {}

    planche = Image.open(SOURCES / nom_planche).convert('RGBA')
    fond = [tuple(np.asarray(planche)[0, 0][:3])]
    if reglages.get('cadre'):
        planche = planche.crop(reglages['cadre'])
    elif marge:
        planche = planche.crop((marge, 0, planche.width - marge, planche.height))
    image = retirer_filets(detourer(planche))
    motifs = decouper(image, mode, reglages.get('groupes'))

    if len(motifs) != len(slugs):
        raise SystemExit('%s: %d motifs cut for %d slugs'
                         % (nom_planche, len(motifs), len(slugs)))

    SVG.mkdir(parents=True, exist_ok=True)
    PNG.mkdir(parents=True, exist_ok=True)

    for slug, motif in zip(slugs, motifs):
        if motif is None or (voulus and slug not in voulus):
            continue
        propre_reglages = dict(reglages)
        propre_reglages.update(reglages.get('motifs', {}).get(slug, {}))

        amont = propre_reglages.get('amont', AMONT)
        propre = epurer(retirer_filets(motif), propre_reglages.get('part', 0.01))
        if propre_reglages.get('centrer'):
            propre = centrer(propre)
        if propre_reglages.get('eroder'):
            propre = eroder(propre, propre_reglages['eroder'])
        grand = propre.resize((propre.width * amont, propre.height * amont), Image.LANCZOS)
        dessin = vectoriser(grand, amont, fond=fond,
                            tolerance=propre_reglages.get('tolerance', 0.55),
                            blob=propre_reglages.get('blob', 400),
                            flou=propre_reglages.get('flou', 1 / 3),
                            ecart=propre_reglages.get('ecart', 26))
        (SVG / (slug + '.svg')).write_text(dessin, encoding='utf-8')
        subprocess.run(['rsvg-convert', '-w', str(propre_reglages.get('largeur', LARGEUR)),
                        '-o', str(PNG / (slug + '.png'))],
                       input=dessin.encode('utf-8'), check=True)
        print('%-14s %s' % (slug, (PNG / (slug + '.png')).relative_to(RACINE)))


def main():
    voulus = set(sys.argv[1:])
    connus = {slug for reglage in PLANCHES.values() for slug in reglage[1]}
    inconnus = voulus - connus
    if inconnus:
        raise SystemExit('unknown filter: ' + ', '.join(sorted(inconnus)))

    for planche in PLANCHES:
        produire(planche, voulus)


if __name__ == '__main__':
    main()
