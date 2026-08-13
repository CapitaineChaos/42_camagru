#!/usr/bin/env python3
"""Index the glyph sheet: one named, measured group per glyph.

Run after every edit of planche.svg. Each glyph is wrapped in
<g id="glyphe-..." data-char data-boite data-ancre>; the rendering is unchanged.

Splitting: a black path opens a glyph, the white highlights that follow belong to it.
Bodies drawn apart (dot of the i, accents) are matched by horizontal overlap.
Reading order of a section gives the character.
"""

import re
import subprocess
import sys
import tempfile
from pathlib import Path

RACINE = Path(__file__).resolve().parent.parent
PLANCHE = RACINE / 'camagru/public/images/elements/planche.svg'

ALPHABETS = {
    'majuscules': 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
    'minuscules': 'abcdefghijklmnopqrstuvwxyz',
    'majuscules-accents': 'ÀÂÄÆÇÉÈÊËÎÏÔÖŒÙÛÜŸ',
    'minuscules-accents': 'àâäæçéèêëîïôöœùûüÿ',
    'chiffres': '0123456789',
}

INTERLIGNE = 100    # vertical gap beyond which two glyphs belong to different rows


def mesurer(source):
    """bbox of every path, numbered in file order."""
    compteur = iter(range(1, 10 ** 6))
    numerote = re.sub(r'<path', lambda m: '<path id="m%d"' % next(compteur), source)
    with tempfile.NamedTemporaryFile('w', suffix='.svg', encoding='utf-8', delete=False) as f:
        f.write(numerote)
        chemin = f.name
    mesures = subprocess.run(['inkscape', '--query-all', chemin],
                             capture_output=True, text=True, check=True).stdout
    Path(chemin).unlink()

    boites = {}
    for ligne in mesures.splitlines():
        champs = ligne.split(',')
        if re.fullmatch(r'm\d+', champs[0]):
            boites[int(champs[0][1:])] = [float(v) for v in champs[1:]]
    return boites


def decouper(bloc, boites, depart):
    """Glyphs of a section: bodies merged, highlights attached, bbox.

    Matching is geometric: path order does not follow reading order (a highlight of the
    Ÿ comes after the body of the Ö).
    """
    formes, reflets, rang = [], [], depart
    for m in re.finditer(r'<path.*?/>', bloc, re.S):
        rang += 1
        (reflets if 'fill:#ffffff' in m.group(0) else formes).append(
            {'paths': [m.group(0)], 'bb': boites[rang]})

    if not formes:
        return [], rang

    # a diacritic is far smaller than its letter; the median fails here, a section of
    # accents holds more diacritics than letters
    seuil = 0.5 * max(f['bb'][3] for f in formes)
    corps = [f for f in formes if f['bb'][3] >= seuil]

    for detail in [f for f in formes if f not in corps]:
        glyphe = plus_proche(detail['bb'], corps, chevauchement=True)
        if glyphe:
            glyphe['paths'] += detail['paths']
            glyphe['bb'] = union(glyphe['bb'], detail['bb'])

    for reflet in reflets:                    # painted after the bodies, so appended last
        glyphe = plus_proche(reflet['bb'], corps)
        glyphe['paths'] += reflet['paths']
    return corps, rang


def centre(boite):
    return boite[0] + boite[2] / 2, boite[1] + boite[3] / 2


def plus_proche(boite, corps, chevauchement=False):
    """Glyph a shape belongs to: the one containing it, else the nearest."""
    x, y = centre(boite)
    dedans = [c for c in corps if c['bb'][0] <= x <= c['bb'][0] + c['bb'][2]
              and c['bb'][1] <= y <= c['bb'][1] + c['bb'][3]]
    if dedans:
        return dedans[0]
    if chevauchement:
        colonne = [c for c in corps if min(boite[0] + boite[2], c['bb'][0] + c['bb'][2])
                   - max(boite[0], c['bb'][0]) > 0]
        if not colonne:
            return None
        return min(colonne, key=lambda c: abs(c['bb'][1] - boite[1]))
    return min(corps, key=lambda c: (centre(c['bb'])[0] - x) ** 2 + (centre(c['bb'])[1] - y) ** 2)


def cadre_de(bloc):
    """Transforms carried by the groups of the section, ahead of its paths."""
    entete = bloc[:bloc.index('<path')]
    return ' '.join(re.findall(r'transform="([^"]*)"', entete))


def union(a, b):
    x, y = min(a[0], b[0]), min(a[1], b[1])
    return [x, y, max(a[0] + a[2], b[0] + b[2]) - x, max(a[1] + a[3], b[1] + b[3]) - y]


def rangees(glyphes):
    """Group by row and set the anchor: middle of the box, on the baseline."""
    glyphes.sort(key=lambda g: g['bb'][1])
    lignes = [[glyphes[0]]]
    for g in glyphes[1:]:
        if g['bb'][1] < lignes[-1][-1]['bb'][1] + INTERLIGNE:
            lignes[-1].append(g)
        else:
            lignes.append([g])

    ordonnes = []
    for ligne in lignes:
        ligne.sort(key=lambda g: g['bb'][0])
        bas = sorted(g['bb'][1] + g['bb'][3] for g in ligne)
        base = bas[len(bas) // 2]           # descenders must not drag the baseline down
        for g in ligne:
            g['ancre'] = (g['bb'][0] + g['bb'][2] / 2, base)
            ordonnes.append(g)
    return ordonnes


def indexer():
    source = PLANCHE.read_text(encoding='utf-8')
    source = re.sub(r'[ \t]*<g id="(?:glyphe|decor)-[^"]*"[^>]*>\n'
                    r'|[ \t]*</g><!-- (?:glyphe|decor) -->\n', '', source)

    boites = mesurer(source)
    sections = [(m.group(1), m.start()) for m in
                re.finditer(r'<g id="([a-z0-9\-]+)" transform="[^"]*">', source)]

    rang, morceaux, curseur, total = 0, [], 0, 0
    for i, (nom, debut) in enumerate(sections):
        fin = sections[i + 1][1] if i + 1 < len(sections) else len(source)
        bloc = source[debut:fin]
        glyphes, rang = decouper(bloc, boites, rang)
        if nom not in ALPHABETS:
            # an ornament section holds a single motif: every path of it belongs there
            paths = re.findall(r'<path.*?/>', bloc, re.S)
            if not paths:
                continue
            boite = glyphes[0]['bb']
            for g in glyphes[1:]:
                boite = union(boite, g['bb'])
            morceaux.append(source[curseur:debut + bloc.index('<path')])
            morceaux.append(
                '<g id="decor-%s" data-decor="%s" data-boite="%s" data-ancre="%.4f,%.4f"'
                ' data-cadre="%s">\n%s\n</g><!-- decor -->\n' % (
                    nom, nom, ','.join('%.4f' % v for v in boite),
                    boite[0] + boite[2] / 2, boite[1] + boite[3] / 2, cadre_de(bloc),
                    '\n'.join('  ' + p for p in paths)))
            total += len(paths)
            curseur = debut + bloc.rindex('/>') + 2
            continue

        glyphes = rangees(glyphes)
        if len(glyphes) != len(ALPHABETS[nom]):
            sys.exit('%s: %d glyphs for %d expected characters'
                     % (nom, len(glyphes), len(ALPHABETS[nom])))

        # box and anchor are in document coordinates, paths in local ones:
        # data-cadre carries the transform tying them together
        cadre = cadre_de(bloc)

        # rewritten in one block: the paths of a glyph lie scattered in the source
        morceaux.append(source[curseur:debut + bloc.index('<path')])
        for caractere, glyphe in zip(ALPHABETS[nom], glyphes):
            morceaux.append(
                '<g id="glyphe-%s" data-char="%s" data-boite="%s" data-ancre="%.4f,%.4f"'
                ' data-cadre="%s">\n%s\n</g><!-- glyphe -->\n' % (
                    identifiant(caractere), echapper(caractere),
                    ','.join('%.4f' % v for v in glyphe['bb']), *glyphe['ancre'], cadre,
                    '\n'.join('  ' + p for p in glyphe['paths'])))
            total += len(glyphe['paths'])
        curseur = debut + bloc.rindex('/>') + 2

    morceaux.append(source[curseur:])
    resultat = ''.join(morceaux)

    if resultat.count('<path') != source.count('<path'):
        sys.exit('lost paths: %d instead of %d'
                 % (resultat.count('<path'), source.count('<path')))

    PLANCHE.write_text(resultat, encoding='utf-8')
    print('%s: %d glyphs indexed (%d paths)' % (PLANCHE.relative_to(RACINE),
                                                 resultat.count('<g id="glyphe-'), total))


def identifiant(caractere):
    """Glyph identifier: readable and free of accents inside the id attribute."""
    accents = {'À': 'A-grave', 'Â': 'A-circonflexe', 'Ä': 'A-trema', 'Æ': 'AE', 'Ç': 'C-cedille',
               'É': 'E-aigu', 'È': 'E-grave', 'Ê': 'E-circonflexe', 'Ë': 'E-trema',
               'Î': 'I-circonflexe', 'Ï': 'I-trema', 'Ô': 'O-circonflexe', 'Ö': 'O-trema',
               'Œ': 'OE', 'Ù': 'U-grave', 'Û': 'U-circonflexe', 'Ü': 'U-trema', 'Ÿ': 'Y-trema'}
    if caractere in accents:
        return 'maj-' + accents[caractere]
    if caractere.upper() in accents:
        return 'min-' + accents[caractere.upper()].lower()
    if caractere.isupper():
        return 'maj-' + caractere
    if caractere.islower():
        return 'min-' + caractere
    return 'chiffre-' + caractere


def echapper(caractere):
    return {'&': '&amp;', '<': '&lt;', '"': '&quot;'}.get(caractere, caractere)


if __name__ == '__main__':
    indexer()
