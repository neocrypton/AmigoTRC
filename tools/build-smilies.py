#!/usr/bin/env python3
"""Erzeugt assets/img/smilies.svg (16 Pixel-Smilies, je 16x16).

Aufruf: python3 tools/build-smilies.py
Gesichter bestehen aus einer Grundform (FACE, Rand 'E') plus Overlay; '.' im Overlay = Grundform bleibt.
Die Kürzel stehen in app/src/Core/Smilies.php (gleiche IDs).
"""
import os

PALETTE = {'K': '#1a1c2c', 'Y': '#feae34', 'O': '#f77622', 'W': '#f4f4f4', 'R': '#e43b44', 'B': '#41a6f6',
           'G': '#38b764', 'S': '#9badb7', 'D': '#566c86', 'P': '#ff77a8', 'N': '#8b5a3c', 'C': '#73eff7', 'E': '#a3461c'}

FACE = """
.....EEEEEE.....
...EEYYYYYYEE...
..EYYYYYYYYYYE..
.EYYYYYYYYYYYYE.
.EYYYYYYYYYYYYE.
EYYYYYYYYYYYYYYE
EYYYYYYYYYYYYYYE
EYYYYYYYYYYYYYYE
EYYYYYYYYYYYYYYE
EYYYYYYYYYYYYYYE
EYYYYYYYYYYYYYYE
.EYYYYYYYYYYYYE.
.EYYYYYYYYYYYYE.
..EYYYYYYYYYYE..
...EEYYYYYYEE...
.....EEEEEE....."""

EYES = {5: '.....K....K.....', 6: '.....K....K.....'}
SMILE = {10: '....K......K....', 11: '.....KKKKKK.....'}
SAD = {10: '.....KKKKKK.....', 11: '....K......K....'}


def face(rows=None, base='Y'):
    """Gesicht aus Grundform + Zeilen-Overlays {Zeile: '16 Zeichen'}."""
    grid = [list(r.replace('Y', base)) for r in FACE.strip('\n').split('\n')]
    for y, row in (rows or {}).items():
        for x, c in enumerate(row):
            if c != '.':
                grid[y][x] = c
    return [''.join(r) for r in grid]


def merge(*parts):
    out = {}
    for p in parts:
        out.update(p)
    return out


SMILIES = [
    ('smile', 'Lächeln', face(merge(EYES, SMILE))),
    ('grin', 'Grinsen', face(merge(EYES, {9: '....KKKKKKKK....', 10: '....KWWWWWWK....', 11: '.....KKKKKK.....'}))),
    ('wink', 'Zwinkern', face(merge({5: '..........K.....', 6: '....KKK...K.....'}, SMILE))),
    ('sad', 'Traurig', face(merge(EYES, SAD))),
    ('tongue', 'Zunge', face(merge(EYES, {10: '....KKKKKKKK....', 11: '.......RRR......', 12: '.......RR.......'}))),
    ('wow', 'Staunen', face(merge(EYES, {9: '.......KK.......', 10: '......K..K......', 11: '......K..K......', 12: '.......KK.......'}))),
    ('neutral', 'Neutral', face(merge(EYES, {10: '.....KKKKKK.....'}))),
    ('cool', 'Cool', face(merge({4: '..KKKKKKKKKKKK..', 5: '..KKKKK..KKKKK..', 6: '...KKK....KKK...'}, SMILE))),
    ('cry', 'Weinen', face(merge(EYES, {7: '.....B....B.....', 8: '.....B....B.....', 9: '....B......B....'}, SAD))),
    ('angry', 'Wütend', face(merge({3: '....K......K....', 4: '.....K....K.....', 5: '.....K....K.....', 6: '.....K....K.....',
                                     10: '.....KKKKKK.....', 11: '....K......K....'}), base='O')),
    ('love', 'Verliebt', face(merge({4: '...R.R....R.R...', 5: '...RRR....RRR...', 6: '....R......R....'}, SMILE))),
    ('lol', 'Lachen', face(merge({5: '....K......K....', 6: '...K.K....K.K...', 7: '.B............B.', 8: '.B............B.',
                                   9: '....KKKKKKKK....', 10: '....KWWWWWWK....', 11: '.....KRRRRK.....', 12: '......KKKK......'}))),
    ('sleep', 'Müde', face(merge({0: '...........BBBB.', 1: '.............B..', 2: '............B...', 3: '...........BBBB.',
                                   6: '....KK....KK....', 10: '.......KK.......'}))),
    ('gamepad', 'Gamepad', """
................
................
................
..KKKKKKKKKKKK..
.KDDDDDDDDDDDDK.
KDDWDDDDDDDRDDDK
KDWWWDDDDDRDRDDK
KDDWDDSSDDDRDDDK
KDDDDDDDDDDDDDDK
KDDDDKKKKKKDDDDK
.KDDK......KDDK.
..KK........KK..""".strip('\n').split('\n')),
    ('thumbsup', 'Daumen hoch', """
......KK........
.....KYYK.......
.....KYYK.......
....KYYYK.......
....KYYYKKKKKK..
KKK.KYYYYYYYYYK.
KBBKYYYYYKKKKK..
KBBKYYYYYYYYYYK.
KBBKYYYYYKKKKK..
KBBKYYYYYYYYYK..
KBBKYYYYYKKKK...
KBBKYYYYYYYYK...
KKK.KKKKKKKK....""".strip('\n').split('\n')),
    ('1up', '1UP-Pilz', """
.....KKKKKK.....
...KKGGWWGGKK...
..KWWGGWWGGWWK..
.KWWWGGGGGGWWWK.
.KGGGGWWWWGGGGK.
KGGGGWWWWWWGGGGK
KWWGGWWWWWWGGWWK
KWWWGGWWWWGGWWWK
KKKKKKKKKKKKKKKK
..KWWKWWWWKWWK..
..KWWKWWWWKWWK..
..KWWWWWWWWWWK..
...KWWWWWWWWK...
....KKKKKKKK....""".strip('\n').split('\n')),
]


def symbol(sid, name, rows):
    h, w = len(rows), max(len(r) for r in rows)
    ox, oy = (16 - w) // 2, (16 - h) // 2
    paths = {}
    for y, row in enumerate(rows):
        x = 0
        while x < len(row):
            c = row[x]
            if c == '.':
                x += 1
                continue
            run = 1
            while x + run < len(row) and row[x + run] == c:
                run += 1
            paths.setdefault(PALETTE[c], []).append(f'M{ox + x} {oy + y}h{run}v1h-{run}z')
            x += run
    body = ''.join(f'<path fill="{c}" d="{"".join(d)}"/>' for c, d in paths.items())
    return f'<symbol id="{sid}" viewBox="0 0 16 16"><title>{name}</title>{body}</symbol>'


if __name__ == '__main__':
    assert len(SMILIES) == 16
    root = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    svg = ('<svg xmlns="http://www.w3.org/2000/svg" shape-rendering="crispEdges">\n'
           + '\n'.join(symbol(*s) for s in SMILIES) + '\n</svg>\n')
    with open(os.path.join(root, 'assets', 'img', 'smilies.svg'), 'w') as f:
        f.write(svg)
    print('ok', len(svg), 'bytes')
