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
    'D': '#5a6988', 'N': '#8b5a3c', 'E': '#a7f070', 'M': '#ffcd75', 'Q': '#ff77a8',
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
    # --- Serie 2 ---
    ('snake', 'Schlange', '#ffcd75', '#38b764', """
..AAAA......
.AKAAKA.....
.AAAAAA.....
..AARA......
....RR..AA..
...AAAAAAAA.
..AA.....AA.
..AAAAAAAAA.
.........AA.
.AAAAAAAAAA."""),
    ('penguin', 'Pinguin', '#73eff7', '#1a1c2c', """
....AAAA....
...AAAAAA...
..AAWAAWAA..
..AAAOOAAA..
.AAWWWWWWAA.
.AWWWWWWWWA.
AAWWWWWWWWAA
.AWWWWWWWWA.
.AAWWWWWWAA.
..OO....OO.."""),
    ('owl', 'Eule', '#29366f', '#8b5a3c', """
.A........A.
.AA......AA.
.AAAAAAAAAA.
AWWWAAAAWWWA
AWKWAAAAWKWA
AWWWAOOAWWWA
AAAAAAOAAAAA
AAMMMMMMMMAA
.AMMMMMMMMA.
..AAMMMMAA..
...O....O..."""),
    ('fish', 'Fisch', '#3b5dc9', '#f77622', """
.....AAA......
...AAAAAA...A.
..AWKAAAAA.AA.
.AAAAAAAAAAA..
.AAAAAAAAAAA..
..AAAAAAAA.AA.
...AAAAAA...A."""),
    ('octopus', 'Krake', '#1a1c2c', '#b55088', """
....AAAA....
..AAAAAAAA..
.AAAAAAAAAA.
.AWWAAAAWWA.
.AWKAAAAWKA.
.AAAAAAAAAA.
..AAAAAAAA..
.A.A.AA.A.A.
A..A.A..A..A"""),
    ('mouse', 'Maus', '#38b764', '#9badb7', """
.AA......AA.
AQQA....AQQA
AQQAAAAAAQQA
.AAAAAAAAAA.
..AKAAAAKA..
..AAAAAAAA..
...AAKKAA...
....AAAA...."""),
    ('bunny', 'Hase', '#b55088', '#f4f4f4', """
..AA..AA..
..AQ..QA..
..AQ..QA..
..AA..AA..
.AAAAAAAA.
.AKAAAAKA.
.AAAQQAAA.
..AAAAAA..
.AAAAAAAA.
.AAAAAAAA."""),
    ('pig', 'Schwein', '#a7f070', '#ff77a8', """
.A........A.
.AA......AA.
.AAAAAAAAAA.
.AKAAAAAAKA.
.AAAPPPPAAA.
.AAAPKKPAAA.
.AAAPPPPAAA.
..AAAAAAAA.."""),
    ('duck', 'Ente', '#41a6f6', '#feae34', """
....AAA.....
...AAAAA....
...AKAAAOO..
...AAAAOOO..
....AAA.....
.AAAAAAAA...
AAAAAAAAAA..
AAAWWAAAAA..
.AAAAAAAA..."""),
    ('bee', 'Biene', '#63c74d', '#feae34', """
...WW...WW..
..WWWW.WWWW.
...WW...WW..
..AAKKAAKK..
.AKAKKAAKKA.
AAAAKKAAKKAK
.AAAKKAAKKA.
..AAKKAAKK.."""),
    ('crab', 'Krabbe', '#ffcd75', '#e43b44', """
AA........AA
A.A......A.A
AA..W..W..AA
.A..K..K..A.
.AAAAAAAAAA.
..AAAAAAAA..
.A.A.AA.A.A.
A..A....A..A"""),
    ('snail', 'Schnecke', '#29366f', '#8b5a3c', """
.........E.E
.........E.E
...AAAA..EEE
..AOOOOA.EKE
.AOAAAOA.EEE
.AOAOOAA.EE.
.AOAAAAA.EE.
.AAAAAAA.EE.
EEEEEEEEEEE."""),
    ('trophy', 'Pokal', '#5d275d', '#feae34', """
AAAAAAAAAAAA
A.AAAAAAAA.A
A.AAAWAAAA.A
.AAAAWAAAAA.
..AAAAAAAA..
....AAAA....
.....AA.....
.....AA.....
....AAAA....
...NNNNNN...
...NNNNNN..."""),
    ('gem', 'Juwel', '#1a1c2c', '#2ce8f5', """
...AAAAAA...
..AWWAAAAA..
.AWAAAAAAAA.
AAAAAAAAAAAA
.AAABBBBAAA.
..AABBBBAA..
...AABBAA...
....AABA....
.....AA....."""),
    ('shield', 'Schild', '#566c86', '#3b5dc9', """
AAAAAAAAAA
AWWWWYYYYA
AWWWWYYYYA
AWWWWYYYYA
AYYYYWWWWA
AYYYYWWWWA
.AYYYWWWA.
..AYYWWA..
...AAAA..."""),
    ('axe', 'Axt', '#a7f070', '#9badb7', """
..AA......
.AAAN.....
AAAAAN....
AAAA.NN...
.AA...NN..
.......NN.
........NN"""),
    ('chest', 'Schatztruhe', '#3b5dc9', '#8b5a3c', """
..AAAAAAAA..
.AAAAAAAAAA.
.AYYYYYYYYA.
.AAAAYYAAAA.
.AAAAKKAAAA.
.AAAAYYAAAA.
.AAAAAAAAAA.
.AYYYYYYYYA."""),
    ('lightning', 'Blitz', '#29366f', '#feae34', """
.....AAAA
....AAAA.
...AAAA..
..AAAAAAA
.....AAA.
....AAA..
...AAA...
..AAA....
..AA.....
..A......"""),
    ('flame', 'Flamme', '#1a1c2c', '#e43b44', """
.....A.....
....AA.....
...AAA..A..
..AAAAA.AA.
..AAOAAAAA.
.AAAOOAAAA.
.AAOOYOAAA.
.AAOYYYOAA.
.AAOYWYOAA.
..AAOYOAA..
...AAAAA..."""),
    ('moon', 'Mond', '#1a1c2c', '#ffcd75', """
....AAAA...W
..AAAA......
.AAAA....W..
.AAA........
AAAA........
AAAA.....W..
AAAA........
.AAAA....A..
.AAAAA..AA..
..AAAAAAA...
....AAA....."""),
    ('tree', 'Baum', '#73eff7', '#38b764', """
.....AA.....
....AAAA....
...AAEAAA...
..AAAAAEAA..
...AAAAAA...
..AEAAAAAA..
.AAAAAAEAAA.
AAAAAAAAAAAA
.....NN.....
.....NN....."""),
    ('planet', 'Planet', '#1a1c2c', '#f77622', """
..........MM
....AAAA.MM.
..AAAAAAMM..
.AAAAAAMMA..
.AAAAAMMAA..
.AAAMMAAAA..
.AAMMAAAAA..
..MMAAAAA...
.MM.AAAA....
MM.........."""),
    ('pumpkin', 'Kürbis', '#5d275d', '#f77622', """
.....GG.....
....GG......
..AAAAAAAA..
.AAAAAAAAAA.
AAKKAAAAKKAA
AAKKAAAAKKAA
AAAAAKKAAAAA
AAKAAAAAAKAA
.AAKKKKKKAA.
..AAAAAAAA.."""),
    ('snowman', 'Schneemann', '#41a6f6', '#f4f4f4', """
....KKKK....
....KKKK....
...KKKKKK...
....AAAA....
...AKAAKA...
...AAOOAA...
....AAAA....
..AAAKAAAA..
.AAAAAAAAAA.
.AAAAKAAAAA.
.AAAAAAAAAA.
..AAAAAAAA.."""),
    ('burger', 'Burger', '#b13e53', '#feae34', """
...AAAAAA...
..AAWAAWAA..
.AAAAAAAAAA.
.GGGGGGGGGG.
.RRRRRRRRRR.
.NNNNNNNNNN.
.MMMMMMMMMM.
..AAAAAAAA.."""),
    ('icecream', 'Eis', '#73eff7', '#ff77a8', """
....AAA.....
...AAAAA....
..AAWAAAA...
..AAAAAAA...
..CCCCCCC...
..CCCCCCC...
...YYYYY....
...YNYNY....
....YYY.....
....YNY.....
.....Y......"""),
    ('arcade', 'Spielautomat', '#1a1c2c', '#b55088', """
..AAAAAAAA..
..AYYYYYYA..
..AAAAAAAA..
..AKKKKKKA..
..AKCCCCKA..
..AKCKKCKA..
..AKKKKKKA..
..AAAAAAAA..
.AAARAAWAAA.
.AAAAAAAAAA.
..AAAAAAAA..
..AAAAAAAA.."""),
    ('handheld', 'Handheld', '#e43b44', '#9badb7', """
.AAAAAAAAA.
.AKKKKKKKA.
.AKEEEEEKA.
.AKEEEEEKA.
.AKEEEEEKA.
.AKKKKKKKA.
.AAAAAAAAA.
.AKAAAAARA.
.KKKAAARAA.
.AKAAAAAAA.
.AAAADDAAA.
..AAAAAAA.."""),
    ('crt', 'Röhren-TV', '#ffcd75', '#8b5a3c', """
...K....K...
....K..K....
.....KK.....
.AAAAAAAAAA.
.AKKKKKKKSA.
.AKBBBBBKAA.
.AKBCBBBKSA.
.AKBBBBBKAA.
.AKKKKKKKSA.
.AAAAAAAAAA.
..A......A.."""),
    ('dice', 'Würfel', '#38b764', '#f4f4f4', """
.AAAAAAAAA.
AAAAAAAAAAA
AAKAAAAAKAA
AAAAAAAAAAA
AAAAAKAAAAA
AAAAAAAAAAA
AAKAAAAAKAA
AAAAAAAAAAA
.SSSSSSSSS."""),
    # --- Serie 3 ---
    ('fox', 'Fuchs', '#38b764', '#f77622', """
A..........A
AA........AA
AAA......AAA
AWAAAAAAAAWA
AAAAAAAAAAAA
AAKAAAAAAKAA
.AAAAAAAAAA.
.WWAAKKAAWW.
..WWWWWWWW..
....WWWW...."""),
    ('panda', 'Panda', '#a7f070', '#f4f4f4', """
.KK......KK.
KKKAAAAAAKKK
.KAAAAAAAAK.
.AKKAAAAKKA.
AAKWKAAKWKAA
AAKKAAAAKKAA
AAAAAKKAAAAA
.AAAAAAAAAA.
..AAAAAAAA.."""),
    ('turtle', 'Schildkröte', '#41a6f6', '#38b764', """
...AAAAA......
..ANAAANA.....
.AAANNNAAA.EE.
AANAAAAANAAEKE
AAAANNNAAAAEEE
.EEAAAAAAAEE..
.EE......EE..."""),
    ('whale', 'Wal', '#73eff7', '#3b5dc9', """
....W.W.......
.....W........
..AAAAAAA.....
.AAAAAAAAA..AA
AAKAAAAAAAAAA.
AAAAAAAAAAAA..
AWWWWWWWAAAA..
.AWWWWWWAAAAA.
..AAAAAAA...AA"""),
    ('chick', 'Küken', '#5d275d', '#feae34', """
....AAAA....
...AAAAAA...
..AAKAAKAA..
..AAAOOAAA..
.AAAAOOAAAA.
AAAAAAAAAAAA
.AAAAAAAAAA.
..AAAAAAAA..
...O....O...
..OO....OO.."""),
    ('bear', 'Bär', '#73eff7', '#8b5a3c', """
.AA......AA.
AMMA....AMMA
AAAAAAAAAAAA
.AAKAAAAKAA.
.AAAAMMAAAA.
.AAAMKKMAAA.
.AAAAMMAAAA.
..AAAAAAAA.."""),
    ('spider', 'Spinne', '#a7f070', '#1a1c2c', """
.A........A.
..A......A..
A..A.AA.A..A
.A..AAAA..A.
..AAAAAAAA..
AA.ARAARA.AA
..AAAAAAAA..
.A..AAAA..A.
A..A....A..A"""),
    ('butterfly', 'Schmetterling', '#73eff7', '#b55088', """
.AA......AA.
AAAA....AAAA
AYYAA.KAAYYA
AYYAAKKAAYYA
.AAAAKKAAAA.
..AAAKKAAA..
.AAAAKKAAAA.
AAYAA..AAYAA
.AAA....AAA."""),
    ('coffee', 'Kaffee', '#b13e53', '#f4f4f4', """
...S..S....
....S..S...
...S..S....
.AAAAAAAA..
.ANNNNNNA..
.AAAAAAAAAA
.AAAAAAAA.A
.AAAAAAAAAA
.AAAAAAAA..
..AAAAAA..."""),
    ('donut', 'Donut', '#41a6f6', '#ff77a8', """
...NNNNNN...
..NAAAAAAN..
.NAWAARAAAN.
NAAAA..AYAAN
NARA....AAAN
NAAA....AWAN
NAAYA..AAAAN
.NNAAAAAANN.
..NNNNNNNN..
...NNNNNN..."""),
    ('pizza', 'Pizza', '#38b764', '#feae34', """
NNNNNNNNNNNN
.AAAAAAAAAA.
.AARRAAAAAA.
..ARRAAARA..
..AAAAAAAA..
...AAARRA...
...AAARRA...
....AAAA....
....AAAA....
.....AA....."""),
    ('melon', 'Melone', '#ffcd75', '#38b764', """
RRRRRRRRRRRR
RRKRRRRRKRRR
.RRRRKRRRRR.
.RRRRRRRRRR.
..RKRRRRKR..
..WWWWWWWW..
...AAAAAA..."""),
    ('candy', 'Bonbon', '#29366f', '#e43b44', """
A..........A
AA..AAAA..AA
AAAAWAAWAAAA
AAAAAWAAWAAA
AA..AAAA..AA
A..........A"""),
    ('guitar', 'Gitarre', '#ffcd75', '#f77622', """
..........KK
.........KK.
........NN..
.......NN...
..AAA.NN....
.AAAAAN.....
AAAKKAAA....
AAAKKAAA....
.AAAAAA.....
..AAAA......"""),
    ('headphones', 'Kopfhörer', '#ffcd75', '#566c86', """
...AAAAAA...
..A......A..
.A........A.
.A........A.
RRA......ARR
RRA......ARR
RRA......ARR
.R........R."""),
    ('bulb', 'Glühbirne', '#1a1c2c', '#feae34', """
....AAAA....
..AAAAAAAA..
.AAAWAAAAAA.
.AAWAAAAAAA.
.AAAAAAAAAA.
..AAAAAAAA..
...AAAAAA...
....SSSS....
....DDDD....
....SSSS....
.....DD....."""),
    ('magnet', 'Magnet', '#41a6f6', '#e43b44', """
..AAAAAAAA..
.AAAAAAAAAA.
AAAA....AAAA
AAA......AAA
AAA......AAA
AAA......AAA
WWW......WWW
WWW......WWW"""),
    ('hourglass', 'Sanduhr', '#3b5dc9', '#8b5a3c', """
AAAAAAAAAA
.A......A.
.AYYYYYYA.
..AYYYYA..
...AYYA...
....AA....
...A..A...
..A.YY.A..
.A.YYYY.A.
AAAAAAAAAA"""),
    ('anchor', 'Anker', '#29366f', '#9badb7', """
.....AA.....
....A..A....
.....AA.....
..AAAAAAAA..
.....AA.....
.....AA.....
A....AA....A
AA...AA...AA
.AA..AA..AA.
..AAAAAAAA..
....AAAA...."""),
    ('umbrella', 'Schirm', '#73eff7', '#b55088', """
.....AAAA.....
...AAAAAAAA...
..AAWAAAAAAA..
.AAAAAAAAAAAA.
AAAAAAAAAAAAAA
A..A..KK..A..A
......KK......
......KK......
......KK......
....K.KK......
....KKK......."""),
    ('bell', 'Glocke', '#b13e53', '#feae34', """
.....AA.....
....AAAA....
...AAAAAA...
..AAWAAAAA..
..AWAAAAAA..
..AAAAAAAA..
.AAAAAAAAAA.
AAAAAAAAAAAA
AAAAAAAAAAAA
.....OO....."""),
    ('note', 'Musik', '#feae34', '#1a1c2c', """
....AAAAAAAA
....AAAAAAAA
....A......A
....A......A
....A......A
....A......A
..AAA....AAA
.AAAA...AAAA
.AAA....AAA."""),
    ('clover', 'Kleeblatt', '#ffcd75', '#38b764', """
...AA..AA...
..AAAAAAAA..
..AAAAAAAA..
...AAAAAA...
.AA.AAAA.AA.
AAAAAAAAAAAA
AAAAAAAAAAAA
.AA.AAAA.AA.
......NN....
.......NN..."""),
    ('sun', 'Sonne', '#41a6f6', '#feae34', """
.....OO.....
.O...OO...O.
..O......O..
....AAAA....
...AAAAAA...
OO.AAAAAA.OO
OO.AAAAAA.OO
...AAAAAA...
....AAAA....
..O......O..
.O...OO...O.
.....OO....."""),
    ('cactus', 'Kaktus', '#ffcd75', '#38b764', """
.....AA.....
....AAAA....
....AAAA..A.
.A..AAAA.AA.
.AA.AAAA.AA.
.AA.AAAAAAA.
.AAAAAAAAA..
..AAAAAA....
....AAAA....
....AAAA....
..NNNNNNNN..
..NNNNNNNN..
...NNNNNN..."""),
    ('computer', 'Computer', '#3b5dc9', '#ffcd75', """
.AAAAAAAAAA.
.AKKKKKKKKA.
.AKBBBBBBKA.
.AKBGBBBBKA.
.AKBBBBBBKA.
.AKKKKKKKKA.
.AAAAAAAAAA.
...AAAAAA...
AAAAAAAAAAAA
AKAKAKAKAKAA
AAAAAAAAAAAA"""),
    ('cd', 'CD', '#5d275d', '#9badb7', """
...AAAAAA...
..AAWWAAAA..
.AAWAAAAAAA.
AAAAAKKAAAAA
AAAAK..KAAAA
AAAAK..KAAAA
AAAAAKKAAAAA
.AAAAAAACAA.
..AAAAACAA..
...AAAAAA..."""),
    ('blocks', 'Blöcke', '#1a1c2c', '#b55088', """
AAAAAAAAA...
AWAAWAAWA...
AAAAAAAAA...
...AAA......
...AWA...OOO
...AAA...OWO
YYYYYY...OOO
YWYYWY...OOO
YYYYYY...OWO
YYYYYYOOOOOO
YWYYWYOWOOWO
YYYYYYOOOOOO"""),
    ('spaceship', 'Raumschiff', '#1a1c2c', '#9badb7', """
.....AA.....
....AAAA....
....ABBA....
....ABBA....
...AAAAAA...
.R.AAAAAA.R.
.RAAAAAAAAR.
RRAAAAAAAARR
RR.AA..AA.RR
....O..O....
....Y..Y...."""),
    ('qblock', 'Fragezeichen-Block', '#41a6f6', '#feae34', """
NNNNNNNNNNNN
NAAAAAAAAAAN
NAKAAAAAAKAN
NAAAKKKKAAAN
NAAKKAAKKAAN
NAAAAAAKKAAN
NAAAAAKKAAAN
NAAAAKKAAAAN
NAAAAAAAAAAN
NAKAAKKAAKAN
NAAAAAAAAAAN
NNNNNNNNNNNN"""),
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
    assert len(BADGES) == 90 and len({b[0] for b in BADGES}) == 90
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
