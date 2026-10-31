#!/usr/bin/env python3
"""Erzeugt assets/img/badges.svg aus den Pixel-Rastern unten.

Aufruf: python3 tools/build-badges.py
Jeder Badge: 16x16-Kachel mit Hintergrundfarbe, Figur zentriert.
Zeichen im Raster: '.' = leer, Großbuchstaben = Farbe aus PALETTE ('A' = Hauptfarbe des Badges).
"""
import os

PALETTE = {
    'K': '#1b1b2f', 'W': '#f4f4f4', 'R': '#e43b44', 'O': '#f77622', 'Y': '#feae34',
    'G': '#63c74d', 'B': '#0099db', 'C': '#2ce8f5', 'P': '#b55088', 'S': '#9badb7',
    'D': '#5a6988', 'N': '#8b5a3c',
}

# (id, Name, Hintergrund, Hauptfarbe A, Raster)
BADGES = [
    ('invader', 'Invader', '#1a1c2c', '#38b764', """
..A......A..
...A....A...
..AAAAAAAA..
.AA.AAAA.AA.
AAAAAAAAAAAA
A.AAAAAAAA.A
A.A......A.A
...AA..AA..."""),
    ('ghost', 'Geist', '#29366f', '#ff77a8', """
....AAAA....
..AAAAAAAA..
.AAAAAAAAAA.
.AWWAAAWWAA.
.AWBAAAWBAA.
AAAAAAAAAAAA
AAAAAAAAAAAA
AAAAAAAAAAAA
AA.AA..AA.AA
A...A..A...A"""),
    ('mushroom', 'Pilz', '#41a6f6', '#e43b44', """
....AAAA....
..AAWWAAAA..
.AAWWWWAAWA.
.AAAWWAAAAA.
AAAAAAAAWWAA
AWWAAAAAWWAA
AWWAAAAAAAAA
..WWWWWWWW..
..WWKWWKWW..
..WWKWWKWW..
..WWWWWWWW..
...WWWWWW..."""),
    ('heart', 'Herz', '#3b1f4b', '#e43b44', """
.AA...AA.
AAAA.AAAA
AWAAAAAAA
AAAAAAAAA
.AAAAAAA.
..AAAAA..
...AAA...
....A...."""),
    ('star', 'Stern', '#1a1c2c', '#feae34', """
.....A.....
....AAA....
....AAA....
AAAAAAAAAAA
.AAAAKAKAA.
..AAAAAAA..
..AAAAAAA..
.AAAA.AAAA.
.AAA...AAA.
.A.......A."""),
    ('coin', 'Münze', '#5d275d', '#feae34', """
..AAAAAA..
.AAWAAAAA.
AAWAOOAAAA
AAWAOAAAAA
AAAAOAAAAA
AAAAOAAAAA
AAAAOAAAAA
AAAOOOAAAA
.AAAAAAAO.
..AAAAAA.."""),
    ('sword', 'Schwert', '#333c57', '#f4f4f4', """
..........AA
.........AAA
........AAA.
.......AAA..
......AAA...
..B..AAA....
...BAAA.....
....BA......
...BOB......
..OB..B.....
.OO.........
OO.........."""),
    ('potion', 'Trank', '#1a1c2c', '#b55088', """
....NNNN....
....WNNW....
.....WW.....
....W..W....
...W....W...
..W.AAAA.W..
..WAAAAAAW..
..WAAWAAAW..
..WAAAAAAW..
...WAAAAW...
....WWWW...."""),
    ('skull', 'Schädel', '#566c86', '#f4f4f4', """
...AAAAAA...
..AAAAAAAA..
.AAAAAAAAAA.
.AKKAAAAKKA.
.AKKAAAAKKA.
.AAAAKKAAAA.
..AAAAAAAA..
...A.A.A.A..
...AAAAAAA.."""),
    ('cat', 'Katze', '#73eff7', '#f77622', """
.A.......A.
.AA.....AA.
.AAAAAAAAA.
.AKAAAAAKA.
.AAAAPAAAA.
.AAAAAAAAA.
..AWWWWWA..
...AAAAA..."""),
    ('frog', 'Frosch', '#29366f', '#63c74d', """
..AA....AA..
.AWKA..AWKA.
.AWWAAAAWWA.
AAAAAAAAAAAA
ARRRRRRRRRRA
.AAAAAAAAAA.
..AA.AA.AA.."""),
    ('alien', 'Alien', '#1a1c2c', '#a7f070', """
....AAAA....
..AAAAAAAA..
.AAAAAAAAAA.
.AKKKAAKKKA.
.AKKKAAKKKA.
..AAAAAAAA..
...AAAAAA...
....AKKA....
.....AA....."""),
    ('robot', 'Roboter', '#3b5dc9', '#9badb7', """
.....R......
.....W......
..AAAAAAAA..
..ACCAACCA..
..ACCAACCA..
..AAAAAAAA..
..AAKKKKAA..
..AAAAAAAA..
.AAAAAAAAAA.
.A.AAAAAA.A.
...AA..AA..."""),
    ('knight', 'Ritter', '#5d275d', '#9badb7', """
...RRR......
....AAAA....
..AAAAAAAA..
.AAAAAAAAAA.
.AKKKKKKKKA.
.AAAAAAAAAA.
.AAKAKAKAAA.
.AAAAAAAAAA.
..AAAAAAAA.."""),
    ('wizard', 'Zauberer', '#1a1c2c', '#3b5dc9', """
......A.....
.....AA.....
.....AAA....
....AAYAA...
....AAAAA...
...AAAAAAA..
...AYAAAAA..
..AAAAAAAAA.
YYYYYYYYYYYY
.YYYYYYYYYY."""),
    ('gamepad', 'Gamepad', '#41a6f6', '#566c86', """
.AAAAAAAAAA.
AAAAAAAAAAAA
AAWAAAAAARAA
AWWWAAAARARA
AAWAAAAAARAA
AAAAAAAAAAAA
AAA......AAA"""),
    ('joystick', 'Joystick', '#38b764', '#e43b44', """
....AA....
...AAAA...
...AAAA...
....AA....
.....K....
.....K....
.....K....
..KKKKKKK.
.KKAKKKKKK
.KKKKKKKKK"""),
    ('cartridge', 'Modul', '#ef7d57', '#9badb7', """
.AAAAAAAAAA.
.AAAAAAAAAA.
.ABBBBBBBBA.
.ABWWWWWWBA.
.ABWRRRRWBA.
.ABWWWWWWBA.
.ABBBBBBBBA.
.AAAAAAAAAA.
.AAKAKAKAKA.
..AAAAAAAA.."""),
    ('floppy', 'Diskette', '#feae34', '#3b5dc9', """
AAAAAAAAAA.
AASSSSSKAAA
AASSSSSKSAA
AASSSSSSSAA
AAAAAAAAAAA
AAWWWWWWWAA
AAWKKKKKWAA
AAWWWWWWWAA
AAWKKKKKWAA
AAWWWWWWWAA"""),
    ('cassette', 'Kassette', '#b13e53', '#333c57', """
AAAAAAAAAAAA
AWWWWWWWWWWA
AWKKWWWWKKWA
AWKSKWWKSKWA
AWKKWWWWKKWA
AWWWWWWWWWWA
AAASSSSSSAAA
AASSSSSSSSAA"""),
    ('bomb', 'Bombe', '#ffcd75', '#1a1c2c', """
.........Y.
........O.O
.......D...
......DD...
...AAAAA...
..AAAAAAA..
.AAWAAAAAA.
.AWAAAAAAA.
.AAAAAAAAA.
.AAAAAAAAA.
..AAAAAAA..
...AAAAA..."""),
    ('key', 'Schlüssel', '#5d275d', '#feae34', """
.AAA........
AA.AA.......
AA.AAAAAAAAA
AA.AA....A.A
.AAA.....A.A"""),
    ('crown', 'Krone', '#b13e53', '#feae34', """
A....A....A
AA..AAA..AA
AAAAAAAAAAA
ARAABAAAGAA
AAAAAAAAAAA
.AAAAAAAAA."""),
    ('ufo', 'UFO', '#1a1c2c', '#9badb7', """
....CCCC....
...CCCCCC...
..AAAAAAAA..
AAAAAAAAAAAA
AYAAYAAYAAYA
.AAAAAAAAAA.
...Y..Y..Y.."""),
    ('rocket', 'Rakete', '#29366f', '#f4f4f4', """
.....A.....
....AAA....
....AAA....
...AACAA...
...AAAAA...
...AAAAA...
..RAAAAAR..
.RRAAAAARR.
.R..OYO..R.
.....O....."""),
    ('chomper', 'Mampfer', '#1a1c2c', '#feae34', """
...AAAAA...
.AAAAAAAAA.
AAAAAKAAAA.
AAAAAAAA...
AAAAAA.....
AAAAAAAA...
AAAAAAAAAA.
.AAAAAAAAA.
...AAAAA..."""),
    ('cherry', 'Kirschen', '#a7f070', '#e43b44', """
.......G...
......GG...
.....G..G..
....G....G.
...G.....G.
.AAA....AAA
AAWAA..AWAA
AAAAA..AAAA
.AAA....AAA"""),
    ('slime', 'Schleim', '#333c57', '#41a6f6', """
....AAAA....
..AAAAAAAA..
.AAWAAAAAAA.
.AAKAAAKAAA.
AAAAAAAAAAAA
AAAAAKKAAAAA
.AAAAAAAAAA."""),
    ('bat', 'Fledermaus', '#ffcd75', '#1a1c2c', """
A....A.A....A
AA...AAA...AA
AAAAARARAAAAA
.AAAAAAAAAAA.
..AA.AAA.AA..
.......A....."""),
    ('dino', 'Dino', '#73eff7', '#38b764', """
...AAAAAA...
..AAAAAAAAA.
..AAKAAAAAAA
..AAAAAAAAAA
..AAAAAAW.W.
..AAAAAAAAAA
...AAAAA....
..AAAAAA....
.AAAAAAAA..."""),
]


def symbol(bid, name, bg, main, art):
    rows = [r for r in art.strip('\n').split('\n')]
    h, w = len(rows), max(len(r) for r in rows)
    assert w <= 14 and h <= 14, bid
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
            color = main if c == 'A' else PALETTE[c]
            paths.setdefault(color, []).append(f'M{ox + x} {oy + y}h{run}v1h-{run}z')
            x += run
    body = ''.join(f'<path fill="{c}" d="{"".join(d)}"/>' for c, d in paths.items())
    return (f'<symbol id="{bid}" viewBox="0 0 16 16"><title>{name}</title>'
            f'<rect width="16" height="16" fill="{bg}"/>{body}</symbol>')


if __name__ == '__main__':
    assert len(BADGES) == 30 and len({b[0] for b in BADGES}) == 30
    root = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    svg = ('<svg xmlns="http://www.w3.org/2000/svg" shape-rendering="crispEdges">\n'
           + '\n'.join(symbol(*b) for b in BADGES) + '\n</svg>\n')
    with open(os.path.join(root, 'assets', 'img', 'badges.svg'), 'w') as f:
        f.write(svg)
    # Liste für PHP (Reihenfolge = Auswahl im Konto)
    with open(os.path.join(root, 'app', 'src', 'Core', 'Badges.php'), 'w') as f:
        f.write('<?php\n// Automatisch erzeugt von tools/build-badges.py – nicht von Hand bearbeiten.\n'
                'declare(strict_types=1);\n\nnamespace Amigo\\Core;\n\nfinal class Badges\n{\n    public const ALL = [\n'
                + ''.join(f"        '{b[0]}' => '{b[1]}',\n" for b in BADGES) + '    ];\n}\n')
    print('ok', len(svg), 'bytes')
