#!/usr/bin/env python3
"""Set a word in lettering from the glyph sheet.

Glyphs and ornaments come measured by planche_index.py: data-boite, data-ancre, data-cadre.
Letters are threaded on a circle, or on a line when the opening is 0; anchor = middle of
the box, on the baseline. logo.svg comes from the same sheet.

    ./scripts/lettrage.py Gallery -a 24 --decor    # negative angle = hollow arc
    ./scripts/lettrage.py Gallery -a 0             # straight, no ornament
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
INTERLETTRE = 4.0      # gap between two letters, in sheet units
ESPACE = 150.0         # advance of a word space

# ornament, position (fraction of half opening), height above baseline (cap height),
# scale, tilt
SEMIS = [
    ('fleur2', -1.03,  1.36, 0.315, -14),
    ('coeur',   0.23,  1.24, 0.272,  14),
    ('fleur1',  0.94, -0.02, 0.234,  10),
    ('fleur3', -0.84, -0.22, 0.214,  -6),
]
MOUSTACHE = 0.5        # scale of the two side bursts
DEGAGEMENT = 120.0     # gap between the last letter and the burst, in sheet units

# word, file, arc opening in degrees (negative = hollow arc)
MENU = [
    ('Home', 'home', 0), ('Gallery', 'gallery', 0), ('Photobooth', 'photobooth', 0),
    ('Friends', 'friends', 0), ('Preferences', 'preferences', 0), ('Profile', 'profile', 0),
    ('Admin', 'admin', 0), ('Login', 'login', 0), ('Sign up', 'signup', 0),
    ('Logout', 'logout', 0),
]
TITRES = [                 # same opening as the logo: titles take its shape
    ('Gallery', 'gallery'), ('Photobooth', 'photobooth'), ('Friends', 'friends'),
    ('Preferences', 'preferences'), ('Profile', 'profile'), ('Admin', 'admin'),
    ('Login', 'login'), ('Sign up', 'signup'),
    ('Lost password', 'lost-password'), ('New password', 'new-password'),
    ('403', '403'), ('404', '404'),   # logout renders no page
]
OUVERTURE_TITRE = 29.87

# one curve each: < 0 hollow, > 0 bulged
ACCUEIL = [
    ('Gallery', 'gallery', 24), ('Photobooth', 'photobooth', 14), ('Friends', 'friends', -16),
    ('Preferences', 'preferences', 13), ('Profile', 'profile', -22), ('Admin', 'admin', 18),
    ('Login', 'login', 20), ('Sign up', 'signup', -14), ('Logout', 'logout', 15),
]


def planche():
    """{character: glyph}, {name: ornament} read from the indexed sheet."""
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
        raise SystemExit('sheet not indexed: run scripts/planche_index.py')
    return glyphes, decors


def groupe(pose, cadre, paths, tete=''):
    return ('  <g%s transform="%s">\n    <g transform="%s">\n%s\n    </g>\n  </g>'
            % (tete, pose, cadre, '\n'.join('      ' + p for p in paths)))


def composer(mot, ouverture, glyphes, decors=None):
    """SVG of the word: arc of |ouverture| degrees, or a straight line when it is zero."""
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
        """Put the anchor on the path, the letter turned by the tangent."""
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
    """Animation class: one per kind of ornament, matching ornaments.css."""
    if motif in ('gauche', 'droite'):
        return 'whisker whisker-' + ('left' if motif == 'gauche' else 'right')
    if motif.startswith('fleur'):
        return 'flower'
    return {'coeur': 'heart'}.get(motif, motif)


def semer(mot, longueur, rayon, creux, glyphes, decors):
    """Side bursts on the midline of the body, then flowers and heart around the word."""
    capitale = max(glyphes[c]['boite'][3] for c in mot if c.isupper()) if any(
        c.isupper() for c in mot) else glyphes[mot[0]]['boite'][3]
    corps_x = min(glyphes[c]['boite'][3] for c in mot if c.islower()) if any(
        c.islower() for c in mot) else capitale / 2

    sens = -1 if creux else 1

    def poser(motif, distance, hauteur, echelle, inclinaison, tangente=False):
        """distance: along the path; hauteur: above the baseline.

        The animation class goes on a wrapper without transform: CSS sets it
        transform-box: fill-box, which would shift the placement in a shared group.
        """
        r = rayon + hauteur
        angle = math.degrees(distance / r)
        redresse = '' if tangente else ' rotate(%.4f)' % (-sens * angle)
        pose = groupe(
            'rotate(%.4f) translate(0,%.4f)%s rotate(%.4f) scale(%.6f) translate(%.4f,%.4f)'
            % (sens * angle, sens * -r, redresse, inclinaison, echelle,
               *[-v for v in decors[motif]['ancre']]),
            decors[motif]['cadre'], decors[motif]['paths'])
        return '  <g class="ornament %s">\n%s\n  </g>' % (famille(motif), pose)

    largeur_eclat = decors['gauche']['boite'][2] * MOUSTACHE
    ecart = longueur / 2 + largeur_eclat / 2 + DEGAGEMENT

    poses = [poser('gauche', -ecart, corps_x / 2, MOUSTACHE, 0, tangente=True),
             poser('droite', ecart, corps_x / 2, MOUSTACHE, 0, tangente=True)]
    poses += [poser(motif, part * longueur / 2, hauteur * capitale, echelle, inclinaison)
              for motif, part, hauteur, echelle, inclinaison in SEMIS]
    return '\n'.join(poses)


def ajuster(svg, echelle, marge=8.0):
    """Crop the viewBox to the drawing and set the pixel size."""
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
                        help="arc opening in degrees; 0 for a straight word")
    parser.add_argument('-e', '--echelle', type=float, default=5.5,
                        help='sheet units per pixel')
    parser.add_argument('-o', '--out', help='output file, relative to images/elements')
    parser.add_argument('--decor', action='store_true', help='whiskers, flowers and heart')
    parser.add_argument('--menu', action='store_true', help='menu entries, straight and bare')
    parser.add_argument('--titres', action='store_true', help='page titles, arched and ornamented')
    parser.add_argument('--accueil', action='store_true', help="home links, arched and bare")
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
        parser.error('give a word, --menu or --titres')


if __name__ == '__main__':
    main()
