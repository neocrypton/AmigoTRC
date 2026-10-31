<?php
declare(strict_types=1);

namespace Amigo\Core;

/**
 * Pixel-Smilies (assets/img/smilies.svg, erzeugt von tools/build-smilies.py).
 * Kürzel enthalten bewusst keine HTML-Sonderzeichen, damit sie nach dem Escapen ersetzt werden können.
 */
final class Smilies
{
    /** Kürzel => [Symbol-ID, Bezeichnung]; das erste Kürzel je Symbol wird in der Leiste angeboten. */
    public const CODES = [
        ':)'         => ['smile', 'Lächeln'],
        ':D'         => ['grin', 'Grinsen'],
        ';)'         => ['wink', 'Zwinkern'],
        ':('         => ['sad', 'Traurig'],
        ':P'         => ['tongue', 'Zunge'],
        ':O'         => ['wow', 'Staunen'],
        ':|'         => ['neutral', 'Neutral'],
        ':cool:'     => ['cool', 'Cool'],
        ':cry:'      => ['cry', 'Weinen'],
        ':angry:'    => ['angry', 'Wütend'],
        ':love:'     => ['love', 'Verliebt'],
        ':lol:'      => ['lol', 'Lachen'],
        ':zzz:'      => ['sleep', 'Müde'],
        ':gamepad:'  => ['gamepad', 'Gamepad'],
        ':up:'       => ['thumbsup', 'Daumen hoch'],
        ':1up:'      => ['1up', '1UP'],
        // Alternativen
        ':-)'        => ['smile', 'Lächeln'],
        ':-D'        => ['grin', 'Grinsen'],
        ';-)'        => ['wink', 'Zwinkern'],
        ':-('        => ['sad', 'Traurig'],
        ':p'         => ['tongue', 'Zunge'],
        ':-P'        => ['tongue', 'Zunge'],
        ':o'         => ['wow', 'Staunen'],
    ];

    /** Für die Auswahlleiste: je Symbol ein Kürzel. */
    public static function palette(): array
    {
        $out = [];
        foreach (self::CODES as $code => [$id, $label]) {
            $out[$id] ??= [$code, $label];
        }
        return $out;
    }

    /**
     * Klartext → sicheres HTML: Text wird escaped, Kürzel werden zu Grafiken, Zeilenumbrüche zu <br>.
     * Der Text wird vorher an den Kürzeln zerlegt, damit z. B. „&quot;)“ nach dem Escapen nicht als „;)“ gilt.
     */
    public static function render(string $text): string
    {
        static $pattern = null, $svg = [];
        if ($pattern === null) {
            $codes = array_keys(self::CODES);
            usort($codes, fn ($a, $b) => strlen($b) <=> strlen($a));
            $pattern = '/(' . implode('|', array_map(fn ($c) => preg_quote($c, '/'), $codes)) . ')/';
            $sprite = asset('img/smilies.svg');
            foreach (self::CODES as $code => [$id, $label]) {
                $svg[$code] = '<svg class="smiley" width="16" height="16" role="img" aria-label="' . e($label) . '"><use href="'
                    . e($sprite) . '#' . $id . '"></use></svg>';
            }
        }
        $out = '';
        foreach (preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE) as $i => $part) {
            $out .= $i % 2 === 1 ? $svg[$part] : nl2br(e($part), false);
        }
        return $out;
    }
}
