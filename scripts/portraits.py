#!/usr/bin/env python3
"""Draw the portraits scripts/seed.py feeds to the photobooth.

Each portrait is a bust facing the camera, framed 4:3 like the montage, so the
server-side cover crop takes nothing away. Alongside the JPEG the script writes
portraits.json: where the skull, the eyes and the face sit in each image, in
fractions of its width and height. seed.py reads that to drop a crown on a head
rather than in a corner.

    ./scripts/portraits.py            # the whole set
    ./scripts/portraits.py -n 24      # more of them
"""

import argparse
import json
import math
import random
from pathlib import Path

from PIL import Image, ImageDraw, ImageFilter

RACINE = Path(__file__).resolve().parent.parent
SORTIE = RACINE / 'assets/seed'

LARGEUR, HAUTEUR = 1200, 900
ECHELLE = 3        # drawn this much larger, then reduced: that is the antialiasing
QUALITE = 88

PEAUX = [
    ((247, 214, 189), (214, 168, 138)),
    ((235, 190, 158), (198, 145, 112)),
    ((205, 157, 118), (166, 118, 82)),
    ((161, 112, 78), (124, 82, 55)),
    ((110, 74, 52), (82, 53, 36)),
    ((252, 226, 210), (222, 184, 166)),
]

CHEVEUX = [
    (38, 32, 30), (72, 48, 34), (120, 78, 44), (168, 122, 62),
    (214, 176, 96), (196, 84, 48), (92, 60, 96), (48, 62, 92),
    (150, 148, 152), (32, 44, 58),
]

VETEMENTS = [
    (86, 108, 168), (168, 92, 104), (94, 148, 118), (196, 156, 82),
    (122, 96, 160), (72, 116, 140), (188, 118, 88), (108, 118, 128),
]

FONDS = [
    ((236, 228, 206), (198, 214, 196)),
    ((222, 232, 240), (186, 202, 226)),
    ((244, 226, 222), (226, 196, 202)),
    ((228, 236, 224), (192, 214, 190)),
    ((240, 232, 214), (214, 198, 226)),
    ((222, 226, 236), (198, 190, 214)),
]

COIFFES = ['calotte', 'frange', 'couettes', 'chignon', 'courts', 'boucles']


def degrade(taille, haut, bas):
    """Vertical wash, then a vignette: a flat fill reads as a placeholder."""
    largeur, hauteur = taille
    fond = Image.new('RGB', (1, hauteur))
    pinceau = ImageDraw.Draw(fond)
    for y in range(hauteur):
        part = y / max(hauteur - 1, 1)
        pinceau.point((0, y), tuple(
            round(haut[c] + (bas[c] - haut[c]) * part) for c in range(3)
        ))
    fond = fond.resize((largeur, hauteur), Image.BILINEAR)

    # full light at the centre, a third of it lost in the corners
    ombre = Image.new('L', (largeur, hauteur), 168)
    ImageDraw.Draw(ombre).ellipse(
        (-largeur * 0.12, -hauteur * 0.16, largeur * 1.12, hauteur * 1.16), fill=255
    )
    ombre = ombre.filter(ImageFilter.GaussianBlur(largeur * 0.05))

    return Image.composite(fond, Image.new('RGB', (largeur, hauteur), (92, 88, 96)), ombre)


def melange(couleur, vers, part):
    return tuple(round(couleur[c] + (vers[c] - couleur[c]) * part) for c in range(3))


def cheveux_derriere(pinceau, coiffe, tete, teinte):
    """The volume that shows around the face: drawn before the skin covers it."""
    cx, cy, rx, ry = tete

    if coiffe in ('calotte', 'frange', 'boucles'):
        pinceau.ellipse((cx - rx * 1.24, cy - ry * 1.3, cx + rx * 1.24, cy + ry * 0.85),
                        fill=teinte)
    elif coiffe == 'couettes':
        pinceau.ellipse((cx - rx * 1.16, cy - ry * 1.24, cx + rx * 1.16, cy + ry * 0.5),
                        fill=teinte)
        for cote in (-1, 1):
            pinceau.ellipse((cx + cote * rx * 1.5 - rx * 0.42, cy - ry * 0.18,
                             cx + cote * rx * 1.5 + rx * 0.42, cy + ry * 0.95),
                            fill=melange(teinte, (0, 0, 0), .08))
    elif coiffe == 'chignon':
        pinceau.ellipse((cx - rx * 0.46, cy - ry * 1.82, cx + rx * 0.46, cy - ry * 0.96),
                        fill=melange(teinte, (0, 0, 0), .1))
        pinceau.ellipse((cx - rx * 1.14, cy - ry * 1.16, cx + rx * 1.14, cy + ry * 0.4),
                        fill=teinte)
    else:
        pinceau.ellipse((cx - rx * 1.12, cy - ry * 1.18, cx + rx * 1.12, cy + ry * 0.3),
                        fill=teinte)


def cheveux_devant(pinceau, coiffe, tete, teinte):
    """The fringe, over the forehead."""
    cx, cy, rx, ry = tete
    clair = melange(teinte, (255, 255, 255), .12)

    if coiffe == 'frange':
        pinceau.chord((cx - rx * 1.06, cy - ry * 1.22, cx + rx * 1.06, cy + ry * 0.1),
                      180, 360, fill=teinte)
        pinceau.ellipse((cx - rx * 1.06, cy - ry * 0.62, cx - rx * 0.2, cy - ry * 0.18),
                        fill=teinte)
    elif coiffe == 'boucles':
        for i in range(9):
            angle = math.pi + math.pi * i / 8
            bx = cx + math.cos(angle) * rx * 1.02
            by = cy + math.sin(angle) * ry * 1.02
            rayon = rx * (0.3 if i % 2 else 0.24)
            pinceau.ellipse((bx - rayon, by - rayon, bx + rayon, by + rayon),
                            fill=teinte if i % 2 else clair)
    elif coiffe == 'courts':
        pinceau.chord((cx - rx * 1.04, cy - ry * 1.16, cx + rx * 1.04, cy - ry * 0.05),
                      180, 360, fill=teinte)
    else:
        pinceau.chord((cx - rx * 1.08, cy - ry * 1.2, cx + rx * 1.08, cy + ry * 0.02),
                      180, 335, fill=teinte)


def visage(pinceau, tete, peau, teinte_cheveux, humeur):
    cx, cy, rx, ry = tete
    clair, ombre = peau

    for cote in (-1, 1):
        pinceau.ellipse((cx + cote * rx * 0.98 - rx * 0.16, cy - ry * 0.02,
                         cx + cote * rx * 0.98 + rx * 0.16, cy + ry * 0.34), fill=ombre)

    pinceau.ellipse((cx - rx, cy - ry, cx + rx, cy + ry), fill=clair)

    oeil_y = cy - ry * 0.06
    ecart = rx * 0.42
    demi_l, demi_h = rx * 0.24, ry * 0.15

    for cote in (-1, 1):
        ox = cx + cote * ecart
        pinceau.ellipse((ox - demi_l, oeil_y - demi_h, ox + demi_l, oeil_y + demi_h),
                        fill=(252, 250, 248))
        iris = rx * 0.115
        pinceau.ellipse((ox - iris, oeil_y - iris, ox + iris, oeil_y + iris),
                        fill=melange(teinte_cheveux, (40, 90, 120), .45))
        pupille = rx * 0.055
        pinceau.ellipse((ox - pupille, oeil_y - pupille, ox + pupille, oeil_y + pupille),
                        fill=(28, 24, 26))
        eclat = rx * 0.032
        pinceau.ellipse((ox - iris * 0.45 - eclat, oeil_y - iris * 0.5 - eclat,
                         ox - iris * 0.45 + eclat, oeil_y - iris * 0.5 + eclat),
                        fill=(255, 255, 255))
        pinceau.arc((ox - demi_l, oeil_y - demi_h * 2.6, ox + demi_l, oeil_y + demi_h * 0.4),
                    195, 345, fill=melange(teinte_cheveux, (0, 0, 0), .2),
                    width=max(2, round(rx * 0.035)))

    pinceau.arc((cx - rx * 0.13, cy + ry * 0.08, cx + rx * 0.13, cy + ry * 0.36),
                200, 340, fill=ombre, width=max(2, round(rx * 0.03)))

    bouche_y = cy + ry * 0.52
    largeur = rx * (0.34 if humeur else 0.26)
    if humeur:
        pinceau.chord((cx - largeur, bouche_y - ry * 0.2, cx + largeur, bouche_y + ry * 0.24),
                      0, 180, fill=(174, 88, 88))
    else:
        pinceau.arc((cx - largeur, bouche_y - ry * 0.16, cx + largeur, bouche_y + ry * 0.18),
                    10, 170, fill=(158, 92, 88), width=max(3, round(rx * 0.045)))

    for cote in (-1, 1):
        pinceau.ellipse((cx + cote * rx * 0.62 - rx * 0.17, cy + ry * 0.24,
                         cx + cote * rx * 0.62 + rx * 0.17, cy + ry * 0.42),
                        fill=melange(clair, (226, 128, 128), .28))


def buste(pinceau, taille, tete, vetement, peau):
    largeur, hauteur = taille
    cx, cy, rx, ry = tete

    pinceau.polygon([(cx - rx * 0.34, cy + ry * 0.72), (cx + rx * 0.34, cy + ry * 0.72),
                     (cx + rx * 0.4, cy + ry * 1.3), (cx - rx * 0.4, cy + ry * 1.3)],
                    fill=peau[1])

    haut = cy + ry * 1.18
    pinceau.ellipse((cx - rx * 2.35, haut, cx + rx * 2.35, haut + (hauteur - haut) * 2.3),
                    fill=vetement)
    pinceau.chord((cx - rx * 0.62, haut - ry * 0.24, cx + rx * 0.62, haut + ry * 0.5),
                  0, 180, fill=melange(vetement, (255, 255, 255), .22))


def portrait(graine):
    hasard = random.Random(graine)
    taille = (LARGEUR * ECHELLE, HAUTEUR * ECHELLE)

    image = degrade(taille, *hasard.choice(FONDS))
    pinceau = ImageDraw.Draw(image)

    peau = hasard.choice(PEAUX)
    teinte = hasard.choice(CHEVEUX)
    coiffe = hasard.choice(COIFFES)

    cx = taille[0] * hasard.uniform(0.46, 0.54)
    cy = taille[1] * hasard.uniform(0.44, 0.5)
    rx = taille[0] * hasard.uniform(0.115, 0.135)
    ry = rx * hasard.uniform(1.18, 1.3)
    tete = (cx, cy, rx, ry)

    buste(pinceau, taille, tete, hasard.choice(VETEMENTS), peau)
    cheveux_derriere(pinceau, coiffe, tete, teinte)
    visage(pinceau, tete, peau, teinte, hasard.random() < .6)
    cheveux_devant(pinceau, coiffe, tete, teinte)

    image = image.resize((LARGEUR, HAUTEUR), Image.LANCZOS)

    # the skull sits above the head ellipse: hair adds to it
    return image, {
        'visage': [round(cx / taille[0], 4), round(cy / taille[1], 4)],
        'crane':  [round(cx / taille[0], 4), round((cy - ry * 1.16) / taille[1], 4)],
        'yeux':   [round(cx / taille[0], 4), round((cy - ry * 0.06) / taille[1], 4)],
        'tete':   [round(rx * 2 / taille[0], 4), round(ry * 2 / taille[1], 4)],
    }


def main():
    analyseur = argparse.ArgumentParser(description=__doc__)
    analyseur.add_argument('-n', '--nombre', type=int, default=15)
    analyseur.add_argument('-g', '--graine', type=int, default=42)
    options = analyseur.parse_args()

    SORTIE.mkdir(parents=True, exist_ok=True)
    for ancien in SORTIE.glob('portrait_*.jpg'):
        ancien.unlink()

    geometries = {}
    for numero in range(1, options.nombre + 1):
        image, geometrie = portrait(options.graine + numero)
        nom = f'portrait_{numero:02d}.jpg'
        image.save(SORTIE / nom, quality=QUALITE, optimize=True)
        geometries[nom] = geometrie
        print(f'  {nom}')

    (SORTIE / 'portraits.json').write_text(
        json.dumps(geometries, indent=2, sort_keys=True) + '\n'
    )
    print(f'{len(geometries)} portraits -> {SORTIE.relative_to(RACINE)}')


if __name__ == '__main__':
    main()
