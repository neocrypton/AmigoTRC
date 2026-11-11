<?php
declare(strict_types=1);

namespace Amigo\Service;

/**
 * HTML-Filter für Website-Texte aus dem CKEditor (Whitelist).
 *
 * Erlaubt nur bekannte Tags/Attribute, prüft Link-Ziele und Inline-Styles,
 * Bilder nur aus dem eigenen Website-Speicher (index.php?r=site/file…).
 * Unbekannte Tags werden entpackt (Inhalt bleibt), gefährliche samt Inhalt entfernt.
 * Wird beim Speichern UND bei der Ausgabe angewendet.
 */
final class Html
{
    /** Komplett entfernen (inkl. Inhalt). */
    private const DROP = ['script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'noscript', 'template',
        'svg', 'math', 'form', 'textarea', 'select', 'button', 'link', 'meta', 'base', 'title', 'head', 'audio', 'video', 'canvas'];

    /** Erlaubte Tags => zusätzliche Attribute (class, style, id sind überall erlaubt). */
    private const TAGS = [
        'p' => [], 'br' => [], 'hr' => [], 'div' => [], 'span' => [],
        'h2' => [], 'h3' => [], 'h4' => [], 'h5' => [], 'h6' => [],
        'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 's' => [], 'del' => [], 'ins' => [],
        'sub' => [], 'sup' => [], 'code' => [], 'pre' => [], 'kbd' => [], 'mark' => [], 'small' => [],
        'blockquote' => [], 'ul' => [], 'ol' => ['start', 'reversed'], 'li' => [],
        'a' => ['href', 'target', 'title'], 'img' => ['src', 'alt', 'width', 'height'],
        'figure' => [], 'figcaption' => [], 'oembed' => ['url'],
        'table' => [], 'caption' => [], 'colgroup' => [], 'col' => ['span'], 'thead' => [], 'tbody' => [], 'tfoot' => [],
        'tr' => [], 'th' => ['colspan', 'rowspan', 'scope'], 'td' => ['colspan', 'rowspan'],
        'label' => [], 'input' => ['type', 'checked', 'disabled'],
    ];

    private const CSS = ['color', 'background-color', 'font-size', 'font-family', 'font-weight', 'font-style', 'text-align',
        'text-decoration', 'line-height', 'margin-left', 'margin-right', 'padding', 'padding-left', 'width', 'height', 'max-width',
        'min-width', 'float', 'vertical-align', 'list-style-type', 'aspect-ratio', 'border', 'border-color', 'border-style',
        'border-width', 'border-top', 'border-right', 'border-bottom', 'border-left', 'border-collapse', 'border-spacing'];

    public static function clean(string $html): string
    {
        return self::process($html, false);
    }

    /** Für die Ausgabe: zusätzlich eingebettete Medien (oembed) als einfachen Link zeigen. */
    public static function render(string $html): string
    {
        return self::process($html, true);
    }

    /** Reiner Text (z. B. für Meta-Beschreibung / Anrisstexte). */
    public static function text(string $html, int $max = 200): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace('<', ' <', $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
        return mb_strlen($text) > $max ? rtrim(mb_substr($text, 0, $max - 1)) . '…' : $text;
    }

    private static function process(string $html, bool $render): string
    {
        if (trim($html) === '') {
            return '';
        }
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML('<html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body><div id="amigo-root">'
            . $html . '</div></body></html>', LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $root = $doc->getElementById('amigo-root');
        if (!$root) {
            return '';
        }
        self::walk($root, $doc, $render);
        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }
        return trim($out);
    }

    private static function walk(\DOMNode $parent, \DOMDocument $doc, bool $render): void
    {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if ($node instanceof \DOMText) {
                continue; // inkl. CDATA (Unterklasse) – wird beim Speichern escaped
            }
            if (!$node instanceof \DOMElement) {
                $parent->removeChild($node); // Kommentare, Verarbeitungsanweisungen …
                continue;
            }
            $tag = strtolower($node->nodeName);
            if (in_array($tag, self::DROP, true)) {
                $parent->removeChild($node);
                continue;
            }
            if (!isset(self::TAGS[$tag])) {
                self::walk($node, $doc, $render);
                while ($node->firstChild) {
                    $parent->insertBefore($node->firstChild, $node);
                }
                $parent->removeChild($node);
                continue;
            }
            if (!self::attributes($node, $tag)) {
                $parent->removeChild($node);
                continue;
            }
            if ($tag === 'oembed' && $render) {
                $url = $node->getAttribute('url');
                $a = $doc->createElement('a');
                $a->setAttribute('href', $url);
                $a->setAttribute('class', 'media-link');
                $a->setAttribute('target', '_blank');
                $a->setAttribute('rel', 'noopener noreferrer');
                $a->appendChild($doc->createTextNode('▶ Medium ansehen: ' . (parse_url($url, PHP_URL_HOST) ?: $url)));
                $parent->replaceChild($a, $node);
                continue;
            }
            self::walk($node, $doc, $render);
        }
    }

    /** Filtert die Attribute eines Elements. false = Element komplett verwerfen. */
    private static function attributes(\DOMElement $el, string $tag): bool
    {
        $allowed = self::TAGS[$tag];
        foreach (iterator_to_array($el->attributes) as $attr) {
            $name = strtolower($attr->nodeName);
            $value = $attr->nodeValue ?? '';
            $keep = null;
            if ($name === 'class') {
                $classes = array_filter(preg_split('/\s+/', $value) ?: [], static fn ($c) => (bool)preg_match('/^[A-Za-z][\w-]{0,60}$/', $c));
                $keep = $classes ? implode(' ', $classes) : null;
            } elseif ($name === 'style') {
                $keep = self::style($value);
            } elseif ($name === 'id') {
                $keep = preg_match('/^[A-Za-z][\w-]{0,60}$/', $value) && $value !== 'amigo-root' ? $value : null;
            } elseif (in_array($name, $allowed, true)) {
                $keep = match ($name) {
                    'href'   => self::url($value),
                    'url'    => self::url($value, false),
                    'src'    => self::ownImage($value),
                    'target' => $value === '_blank' ? '_blank' : null,
                    'type'   => strtolower($value) === 'checkbox' ? 'checkbox' : null,
                    'width', 'height', 'colspan', 'rowspan', 'start', 'span' => preg_match('/^\d{1,5}$/', trim($value)) ? trim($value) : null,
                    'scope'  => in_array($value, ['row', 'col', 'rowgroup', 'colgroup'], true) ? $value : null,
                    'checked', 'disabled', 'reversed' => $name,
                    default  => mb_substr($value, 0, 500), // alt, title
                };
            }
            if ($keep === null || $keep === '') {
                $el->removeAttribute($attr->nodeName);
            } elseif ($keep !== $value || $attr->nodeName !== $name) {
                $el->removeAttribute($attr->nodeName);
                $el->setAttribute($name, $keep);
            }
        }
        return match ($tag) {
            'img'    => $el->hasAttribute('src'),
            'oembed' => $el->hasAttribute('url'),
            'input'  => self::checkbox($el),
            'a'      => self::link($el),
            default  => true,
        };
    }

    private static function checkbox(\DOMElement $el): bool
    {
        if ($el->getAttribute('type') !== 'checkbox') {
            return false;
        }
        $el->setAttribute('disabled', 'disabled');
        return true;
    }

    private static function link(\DOMElement $el): bool
    {
        $href = $el->getAttribute('href');
        if ($el->getAttribute('target') === '_blank' || preg_match('#^(https?:)?//#i', $href)) {
            $el->setAttribute('rel', 'noopener noreferrer');
        }
        return true;
    }

    /** Erlaubte Link-Ziele: http(s), mailto, tel und relative Adressen/Anker. */
    public static function url(string $url, bool $relative = true): ?string
    {
        $url = trim($url);
        // HTML5-Entities (z. B. &colon;) kennt der Parser nicht – für die Prüfung auflösen.
        $probe = preg_replace('/[\x00-\x20\x7F]+/', '', html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '';
        if ($probe === '' || mb_strlen($url) > 2000) {
            return null;
        }
        if (str_contains($probe, '&')) {
            $probe = preg_replace('/[\x00-\x20\x7F]+/', '', html_entity_decode($probe, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '';
        }
        if (preg_match('/^([a-z][a-z0-9+.\-]*):/i', $probe, $m)) {
            $ok = $relative ? ['http', 'https', 'mailto', 'tel'] : ['http', 'https'];
            return in_array(strtolower($m[1]), $ok, true) ? $url : null;
        }
        return $relative ? $url : null;
    }

    /** Bilder nur aus dem eigenen Website-Speicher. */
    private static function ownImage(string $src): ?string
    {
        $src = trim($src);
        return preg_match('#^index\.php\?r=site/file&f=[a-f0-9]{32}\.(jpg|png|gif)$#', $src) ? $src : null;
    }

    private static function style(string $style): ?string
    {
        $out = [];
        foreach (explode(';', $style) as $decl) {
            if (!str_contains($decl, ':')) {
                continue;
            }
            [$prop, $value] = array_map('trim', explode(':', $decl, 2));
            $prop = strtolower($prop);
            if (!in_array($prop, self::CSS, true) || $value === '' || strlen($value) > 200) {
                continue;
            }
            if (!preg_match('/^[#\w\s.,%()\'"+\/-]+$/u', $value) || preg_match('/url\s*\(|expression|javascript|image-set|@/i', $value)) {
                continue;
            }
            $out[] = $prop . ':' . $value;
        }
        return $out ? implode(';', $out) : null;
    }
}
