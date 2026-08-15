#!/usr/bin/env python3
"""Trace a flat-colour bitmap into SVG, one filled path per colour.

Written for the filter sheets: they are flat vector art saved as small PNG, so the
colours are few and the edges are clean. Marching squares gives the outlines of each
colour mask, Ramer-Douglas-Peucker drops the staircase, and a Catmull-Rom pass turns
the polyline into curves while keeping the sharp corners sharp.

    ./scripts/vectoriser.py motif.png motif.svg
"""

import math
import sys
from pathlib import Path

import numpy as np
from PIL import Image, ImageFilter

ECHELLE = 2           # mask upsampling: smooths the outline before tracing
ALPHA_OPAQUE = 190    # a pixel below this is background or antialiased fringe
ALPHA_PUR = 235       # colours are counted on full pixels only, never on the fringe
SURFACE_MIN = 0.0022  # a colour under this share of the drawing is noise
ECART_COULEUR = 26    # rgb distance below which two fills are the same one
ECART_FOND = 58       # a colour this close to the sheet background is its fringe
QUANTIFICATION = 40   # palette size before the merge
BLOB_MIN = 400        # px at the upsampled scale: below it, a speck of the screenshot
TOLERANCE = 0.55      # RDP, in pixels of the source drawing
ANGLE_COIN = 62       # degrees: past this the joint stays a corner
TENSION = 0.42

# corner bits: 1 top-left, 2 top-right, 4 bottom-right, 8 bottom-left.
# Values are the doubled coordinates of the edge midpoints, so chaining is exact.
SEGMENTS = {
    1: [('L', 'T')], 2: [('T', 'R')], 3: [('L', 'R')], 4: [('R', 'B')],
    5: [('L', 'T'), ('R', 'B')], 6: [('T', 'B')], 7: [('L', 'B')], 8: [('B', 'L')],
    9: [('B', 'T')], 10: [('T', 'R'), ('B', 'L')], 11: [('B', 'R')], 12: [('R', 'L')],
    13: [('R', 'T')], 14: [('T', 'L')],
}


def couleurs(image, ecart=ECART_COULEUR, fond=()):
    """Dominant colours, largest area first, and the index map of the pixels.

    Screenshots of flat art are noisy: one fill scatters into near-identical values.
    A median pass flattens the speckle, then close values are merged into one entry.
    """
    lisse = image.convert('RGBA').filter(ImageFilter.MedianFilter(3))
    donnees = np.asarray(lisse, dtype=np.int16)
    donnees[..., 3] = np.asarray(image.convert('RGBA'), dtype=np.int16)[..., 3]

    opaque = donnees[..., 3] >= ALPHA_OPAQUE
    plein = donnees[..., 3] >= ALPHA_PUR
    if not plein.any():
        raise SystemExit('empty image')

    # compression scatters a flat fill over hundreds of values: quantise, then count
    reduite = Image.fromarray(donnees[..., :3].astype(np.uint8), mode='RGB').quantize(
        colors=QUANTIFICATION, method=Image.MEDIANCUT, dither=Image.Dither.NONE)
    table = np.array(reduite.getpalette()[:QUANTIFICATION * 3],
                     dtype=np.int16).reshape(-1, 3)
    index_brut = np.asarray(reduite)

    valeurs, comptes = np.unique(index_brut[plein], return_counts=True)
    ordre = np.argsort(-comptes)

    retenues = []
    for i in ordre:
        teinte = table[valeurs[i]]
        for garde in retenues:
            if float(((teinte.astype(np.int32)
                       - garde['couleur'].astype(np.int32)) ** 2).sum()) < ecart ** 2:
                garde['compte'] += int(comptes[i])
                break
        else:
            retenues.append({'couleur': teinte, 'compte': int(comptes[i])})

    gardees = [r for r in retenues if r['compte'] >= SURFACE_MIN * plein.sum()]
    gardees.sort(key=lambda r: -r['compte'])
    palette = np.array([r['couleur'] for r in gardees] or [retenues[0]['couleur']],
                       dtype=np.int16)

    # every opaque pixel joins its nearest kept colour, antialiased edges included;
    # int32 throughout, a squared channel gap overflows int16
    ecarts = (donnees[..., None, :3].astype(np.int32)
              - palette[None, None, :, :].astype(np.int32))
    index = np.argmin((ecarts ** 2).sum(axis=3), axis=2)
    index = np.where(opaque, index, -1)

    return palette, index


def agrandir(masque, amont, flou):
    """Upsample, then blur away the staircase left by the source pixel grid."""
    image = Image.fromarray((masque * 255).astype(np.uint8), mode='L')
    grand = image.resize((masque.shape[1] * ECHELLE, masque.shape[0] * ECHELLE),
                         Image.BILINEAR)
    rayon = max(1, round(amont * ECHELLE * flou))
    return np.asarray(grand.filter(ImageFilter.BoxBlur(rayon))) >= 128


def eroder(masque, pas):
    """Erosion, used to tell a real fill from the fringe left by antialiasing."""
    sortie = masque
    for _ in range(pas):
        interieur = sortie.copy()
        interieur[1:, :] &= sortie[:-1, :]
        interieur[:-1, :] &= sortie[1:, :]
        interieur[:, 1:] &= sortie[:, :-1]
        interieur[:, :-1] &= sortie[:, 1:]
        sortie = interieur
    return sortie


def dilater(masque):
    sortie = masque.copy()
    sortie[1:, :] |= masque[:-1, :]
    sortie[:-1, :] |= masque[1:, :]
    sortie[:, 1:] |= masque[:, :-1]
    sortie[:, :-1] |= masque[:, 1:]
    return sortie


def nettoyer(masque, blob=BLOB_MIN):
    """Drops specks: flood fill each component, keep those above the blob floor."""
    hauteur, largeur = masque.shape
    vu = np.zeros_like(masque)
    garde = np.zeros_like(masque)

    for depart in zip(*np.nonzero(masque & ~vu)):
        if vu[depart]:
            continue
        pile, composante = [depart], []
        vu[depart] = True
        while pile:
            y, x = pile.pop()
            composante.append((y, x))
            for dy, dx in ((1, 0), (-1, 0), (0, 1), (0, -1)):
                v, u = y + dy, x + dx
                if 0 <= v < hauteur and 0 <= u < largeur and masque[v, u] and not vu[v, u]:
                    vu[v, u] = True
                    pile.append((v, u))
        if len(composante) >= blob:
            for y, x in composante:
                garde[y, x] = True
    return garde


def boucles(masque):
    """Closed contours of the mask, in doubled integer coordinates."""
    rembourre = np.zeros((masque.shape[0] + 2, masque.shape[1] + 2), dtype=bool)
    rembourre[1:-1, 1:-1] = masque

    hg = rembourre[:-1, :-1]
    hd = rembourre[:-1, 1:]
    bd = rembourre[1:, 1:]
    bg = rembourre[1:, :-1]
    cases = hg * 1 + hd * 2 + bd * 4 + bg * 8

    suivant = {}
    for (i, j) in zip(*np.nonzero((cases > 0) & (cases < 15))):
        milieux = {'T': (2 * j + 1, 2 * i), 'R': (2 * j + 2, 2 * i + 1),
                   'B': (2 * j + 1, 2 * i + 2), 'L': (2 * j, 2 * i + 1)}
        for depart, arrivee in SEGMENTS[int(cases[i, j])]:
            suivant[milieux[depart]] = milieux[arrivee]

    contours = []
    while suivant:
        depart = next(iter(suivant))
        chemin, point = [], depart
        while point in suivant:
            chemin.append(point)
            suite = suivant.pop(point)
            point = suite
            if point == depart:
                break
        if len(chemin) > 6:
            contours.append(chemin)
    return contours


def simplifier(points, tolerance):
    if len(points) < 4:
        return points

    garde = [False] * len(points)
    garde[0] = garde[-1] = True
    pile = [(0, len(points) - 1)]

    while pile:
        debut, fin = pile.pop()
        ax, ay = points[debut]
        bx, by = points[fin]
        longueur = math.hypot(bx - ax, by - ay)
        pire, index = tolerance, -1
        for k in range(debut + 1, fin):
            px, py = points[k]
            if longueur == 0:
                ecart = math.hypot(px - ax, py - ay)
            else:
                ecart = abs((bx - ax) * (ay - py) - (ax - px) * (by - ay)) / longueur
            if ecart > pire:
                pire, index = ecart, k
        if index != -1:
            garde[index] = True
            pile.append((debut, index))
            pile.append((index, fin))

    return [point for point, tenu in zip(points, garde) if tenu]


def courbes(points):
    """Catmull-Rom through the polyline, corners kept as straight joints."""
    nombre = len(points)
    if nombre < 3:
        return ''

    tangentes = []
    for i in range(nombre):
        avant = points[(i - 1) % nombre]
        apres = points[(i + 1) % nombre]
        courant = points[i]

        entrant = (courant[0] - avant[0], courant[1] - avant[1])
        sortant = (apres[0] - courant[0], apres[1] - courant[1])
        norme_e = math.hypot(*entrant) or 1
        norme_s = math.hypot(*sortant) or 1
        cosinus = (entrant[0] * sortant[0] + entrant[1] * sortant[1]) / (norme_e * norme_s)
        angle = math.degrees(math.acos(max(-1.0, min(1.0, cosinus))))

        if angle > ANGLE_COIN:
            tangentes.append((0.0, 0.0))
        else:
            tangentes.append(((apres[0] - avant[0]) * TENSION,
                              (apres[1] - avant[1]) * TENSION))

    morceaux = ['M %.2f,%.2f' % points[0]]
    for i in range(nombre):
        depart, arrivee = points[i], points[(i + 1) % nombre]
        td, ta = tangentes[i], tangentes[(i + 1) % nombre]
        morceaux.append('C %.2f,%.2f %.2f,%.2f %.2f,%.2f'
                        % (depart[0] + td[0] / 3, depart[1] + td[1] / 3,
                           arrivee[0] - ta[0] / 3, arrivee[1] - ta[1] / 3,
                           arrivee[0], arrivee[1]))
    morceaux.append('Z')
    return ' '.join(morceaux)


def vectoriser(source, amont=1, tolerance=TOLERANCE, blob=BLOB_MIN, flou=1 / 3,
               ecart=ECART_COULEUR, fond=()):
    """source: a path or an already loaded image; amont: how much it was upsampled.

    A sheet saved at a few dozen pixels needs a lighter hand than a clean one: its
    details are one pixel wide, and both the blur and the speck filter would eat them.
    """
    image = source if isinstance(source, Image.Image) else Image.open(source)
    palette, index = couleurs(image, ecart, fond)
    hauteur, largeur = index.shape

    couches = []
    for rang, couleur in enumerate(palette):
        masque = nettoyer(dilater(dilater(agrandir(index == rang, amont, flou))), blob)
        if not masque.any():
            continue

        # a shade close to the background is kept only where it forms a real area:
        # the teeth of a mouth are near-white like the sheet, its fringe is not
        if any(((couleur.astype(np.int32) - np.array(teinte, dtype=np.int32)) ** 2).sum()
               < ECART_FOND ** 2 for teinte in fond):
            coeur = eroder(masque, max(1, round(amont * ECHELLE * 0.7)))
            if coeur.sum() < 0.3 * masque.sum():
                continue

        chemins = []
        for contour in boucles(masque):
            points = [(x / (2 * ECHELLE), y / (2 * ECHELLE)) for x, y in contour]
            reduits = simplifier(points, tolerance * amont / 2)
            if len(reduits) >= 3:
                chemins.append(courbes(reduits))
        if chemins:
            couches.append('  <path style="fill:#%02x%02x%02x;fill-rule:evenodd" d="%s" />'
                           % (*couleur, ' '.join(chemins)))

    return ('<?xml version="1.0" encoding="UTF-8" standalone="no"?>\n'
            '<svg version="1.1" width="%d" height="%d" viewBox="0 0 %d %d"\n'
            '   xmlns="http://www.w3.org/2000/svg">\n%s\n</svg>\n'
            % (largeur, hauteur, largeur, hauteur, '\n'.join(couches)))


def main():
    if len(sys.argv) != 3:
        raise SystemExit(__doc__)
    Path(sys.argv[2]).write_text(vectoriser(sys.argv[1]), encoding='utf-8')
    print('%s -> %s' % (sys.argv[1], sys.argv[2]))


if __name__ == '__main__':
    main()
