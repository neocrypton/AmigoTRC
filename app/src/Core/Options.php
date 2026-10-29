<?php
declare(strict_types=1);

namespace Amigo\Core;

/** Feste Auswahllisten (Whitelist für Formulareingaben). */
final class Options
{
    public const KINDS = [
        'game'      => 'Spiel',
        'console'   => 'Konsole / Computer',
        'accessory' => 'Zubehör / Controller',
        'other'     => 'Sonstiges',
    ];

    public const REGIONS = [
        'PAL'    => 'PAL (EU)',
        'PAL-DE' => 'PAL (DE)',
        'NTSC-U' => 'NTSC-U (USA)',
        'NTSC-J' => 'NTSC-J (Japan)',
        'NTSC-K' => 'NTSC-K (Korea)',
        'FREE'   => 'Regionfrei',
        'OTHER'  => 'Andere',
    ];

    public const COMPLETENESS = [
        'sealed' => 'Versiegelt (NEW)',
        'cib'    => 'Komplett (CIB)',
        'boxed'  => 'Mit Verpackung, ohne Anleitung',
        'manual' => 'Mit Anleitung, ohne Verpackung',
        'loose'  => 'Lose (nur Modul/Disc/Gerät)',
    ];

    /** Zustandsskala, null = Teil nicht vorhanden. */
    public const CONDITIONS = [
        5 => 'Neuwertig',
        4 => 'Sehr gut',
        3 => 'Gut',
        2 => 'Gebraucht',
        1 => 'Mangelhaft',
        0 => 'Defekt',
    ];

    public const THEMES = [
        'crt' => 'CRT Neon',
        'dmg' => 'Game Boy',
        'c64' => 'C64',
    ];
}
