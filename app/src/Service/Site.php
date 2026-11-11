<?php
declare(strict_types=1);

namespace Amigo\Service;

use Amigo\Core\App;
use Amigo\Core\Settings;

/**
 * Öffentliche Website: Seiten, FAQ, News, Links/Partner und deren Bilder.
 * Ein-/ausschaltbar im Adminbereich (Settings site_enabled / news_enabled).
 * Impressum und Datenschutz sind unabhängig davon erreichbar, sobald sie aktiviert sind.
 */
final class Site
{
    /** slug => [Menütitel, gehört zur Website (true) oder Rechtliches (false)] */
    public const PAGES = [
        'home'    => ['Start', true],
        'install' => ['Installation', true],
        'faq'     => ['FAQ', true],
        'imprint' => ['Impressum', false],
        'privacy' => ['Datenschutz', false],
    ];

    public const BANNER_W = 160;
    public const BANNER_H = 50;
    public const IMAGE_MAX_BYTES = 5 * 1024 * 1024;
    public const BANNER_MAX_BYTES = 512 * 1024;
    private const IMAGE_MAX_EDGE = 1600;
    private const MAX_PIXELS = 40_000_000;

    public static function enabled(): bool
    {
        return Settings::bool(App::db(), 'site_enabled');
    }

    public static function newsEnabled(): bool
    {
        return self::enabled() && Settings::bool(App::db(), 'news_enabled');
    }

    public static function page(string $slug): ?array
    {
        return isset(self::PAGES[$slug]) ? App::db()->one('SELECT * FROM {site_pages} WHERE slug = ?', [$slug]) : null;
    }

    /** Darf die Seite öffentlich angezeigt werden? */
    public static function visible(?array $page): bool
    {
        if (!$page || !(int)$page['enabled']) {
            return false;
        }
        return !self::PAGES[$page['slug']][1] || self::enabled();
    }

    /** @return array<string, array> slug => Seite, in fester Reihenfolge */
    public static function pages(): array
    {
        $rows = [];
        foreach (App::db()->all('SELECT * FROM {site_pages}') as $r) {
            $rows[$r['slug']] = $r;
        }
        return array_filter(array_map(static fn ($slug) => $rows[$slug] ?? null, array_combine(array_keys(self::PAGES), array_keys(self::PAGES))));
    }

    /** Menüpunkte der öffentlichen Website: [url, Titel, Schlüssel] */
    public static function nav(): array
    {
        $items = [];
        foreach (self::pages() as $slug => $p) {
            if (self::PAGES[$slug][1] && self::visible($p)) {
                $items[] = [$slug === 'home' ? url('site') : url('page', ['p' => $slug]), $p['title'], $slug];
            }
        }
        if (self::newsEnabled()) {
            array_splice($items, min(1, count($items)), 0, [[url('news'), 'News', 'news']]);
        }
        return $items;
    }

    /** Impressum/Datenschutz für die Fußzeile (überall, auch im Login und in der App). */
    public static function legalLinks(): array
    {
        static $links = null;
        if ($links === null) {
            $links = [];
            try {
                foreach (App::db()->all("SELECT slug, title FROM {site_pages} WHERE slug IN ('imprint', 'privacy') AND enabled = 1 ORDER BY slug") as $p) {
                    $links[] = [url('page', ['p' => $p['slug']]), $p['title']];
                }
            } catch (\PDOException) {
                // Tabelle fehlt noch (Migration läuft gerade)
            }
        }
        return $links;
    }

    public static function faq(bool $onlyEnabled = true): array
    {
        return App::db()->all('SELECT * FROM {site_faq}' . ($onlyEnabled ? ' WHERE enabled = 1' : '') . ' ORDER BY sort, id');
    }

    public static function links(bool $onlyEnabled = true): array
    {
        return App::db()->all('SELECT * FROM {site_links}' . ($onlyEnabled ? ' WHERE enabled = 1' : '') . ' ORDER BY sort, id');
    }

    public static function news(int $limit, int $offset = 0): array
    {
        return App::db()->all(
            'SELECT * FROM {site_news} WHERE published = 1 AND published_at <= ? ORDER BY published_at DESC, id DESC LIMIT ' . $limit . ' OFFSET ' . $offset,
            [\Amigo\Core\Database::now()]
        );
    }

    public static function newsCount(): int
    {
        return (int)App::db()->value('SELECT COUNT(*) FROM {site_news} WHERE published = 1 AND published_at <= ?', [\Amigo\Core\Database::now()]);
    }

    /** Verschiebt einen Eintrag (FAQ/Link) um eine Position nach oben/unten. */
    public static function move(string $table, int $id, int $dir): void
    {
        if (!in_array($table, ['site_faq', 'site_links'], true)) {
            return;
        }
        $db = App::db();
        $ids = array_map('intval', array_column($db->all('SELECT id FROM {' . $table . '} ORDER BY sort, id'), 'id'));
        $pos = array_search($id, $ids, true);
        $swap = $pos === false ? false : $pos + $dir;
        if ($swap === false || $swap < 0 || $swap >= count($ids)) {
            return;
        }
        [$ids[$pos], $ids[$swap]] = [$ids[$swap], $ids[$pos]];
        $db->transaction(function () use ($db, $table, $ids) {
            foreach ($ids as $i => $rowId) {
                $db->update($table, ['sort' => ($i + 1) * 10], ['id' => $rowId]);
            }
        });
    }

    public static function nextSort(string $table): int
    {
        return (int)App::db()->value('SELECT COALESCE(MAX(sort), 0) + 10 FROM {' . ($table === 'site_faq' ? 'site_faq' : 'site_links') . '}');
    }

    // ------------------------------------------------------------------ Dateien

    public static function dir(): string
    {
        return App::dataDir() . '/site';
    }

    public static function file(string $name): ?string
    {
        if (!preg_match('/^[a-f0-9]{32}\.(jpg|png|gif)$/', $name)) {
            return null;
        }
        $path = self::dir() . '/' . $name;
        return is_file($path) ? $path : null;
    }

    public static function fileUrl(string $name): string
    {
        return url('site/file', ['f' => $name]);
    }

    public static function removeFile(?string $name): void
    {
        if ($name && ($path = self::file($name))) {
            @unlink($path);
        }
    }

    /**
     * Bild aus dem Editor: wird neu berechnet (max. 1600 px, EXIF weg).
     * PNG/GIF bleiben PNG (Transparenz), Fotos werden JPEG.
     */
    public static function storeImage(array $upload): string
    {
        [$tmp, $mime, $w, $h] = self::checkUpload($upload, self::IMAGE_MAX_BYTES);
        $src = self::load($tmp, $mime);
        $scale = min(1, self::IMAGE_MAX_EDGE / max($w, $h));
        $nw = max(1, (int)round($w * $scale));
        $nh = max(1, (int)round($h * $scale));
        $png = in_array($mime, ['image/png', 'image/gif'], true);
        $dst = imagecreatetruecolor($nw, $nh);
        if ($png) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        } else {
            imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        }
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($src);
        return self::save($dst, $png ? 'png' : 'jpg');
    }

    /**
     * Partner-Banner im Format 160×50. Animierte GIFs in genau dieser Größe bleiben
     * unverändert (Animation bleibt erhalten), alles andere wird passend zugeschnitten.
     */
    public static function storeBanner(array $upload): string
    {
        [$tmp, $mime, $w, $h] = self::checkUpload($upload, self::BANNER_MAX_BYTES);
        if ($mime === 'image/gif' && $w === self::BANNER_W && $h === self::BANNER_H && @imagecreatefromgif($tmp)) {
            $name = bin2hex(random_bytes(16)) . '.gif';
            self::ensureDir();
            if (!move_uploaded_file($tmp, self::dir() . '/' . $name)) {
                throw new \RuntimeException('Banner konnte nicht gespeichert werden.');
            }
            @chmod(self::dir() . '/' . $name, 0640);
            return $name;
        }
        $src = self::load($tmp, $mime);
        // Mittig auf 160:50 zuschneiden, dann skalieren
        $ratio = self::BANNER_W / self::BANNER_H;
        if ($w / $h > $ratio) {
            $cw = (int)round($h * $ratio);
            $ch = $h;
        } else {
            $cw = $w;
            $ch = (int)round($w / $ratio);
        }
        $sx = intdiv($w - $cw, 2);
        $sy = intdiv($h - $ch, 2);
        $dst = imagecreatetruecolor(self::BANNER_W, self::BANNER_H);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        if ($cw <= self::BANNER_W) {
            imagecopyresized($dst, $src, 0, 0, $sx, $sy, self::BANNER_W, self::BANNER_H, $cw, $ch); // Pixel-Art bleibt scharf
        } else {
            imagecopyresampled($dst, $src, 0, 0, $sx, $sy, self::BANNER_W, self::BANNER_H, $cw, $ch);
        }
        imagedestroy($src);
        return self::save($dst, 'png');
    }

    /** @return array{0:string,1:string,2:int,3:int} tmp, mime, breite, höhe */
    private static function checkUpload(array $upload, int $maxBytes): array
    {
        $err = $upload['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($err !== UPLOAD_ERR_OK) {
            throw new \RuntimeException(match ($err) {
                UPLOAD_ERR_NO_FILE => 'Bitte ein Bild auswählen.',
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Das Bild ist zu groß.',
                default => 'Upload fehlgeschlagen.',
            });
        }
        $tmp = (string)$upload['tmp_name'];
        if (!is_uploaded_file($tmp)) {
            throw new \RuntimeException('Ungültiger Upload.');
        }
        if (filesize($tmp) > $maxBytes) {
            throw new \RuntimeException('Das Bild ist zu groß (max. ' . round($maxBytes / 1024 / 1024, 1) . ' MB).');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        if (!in_array($mime, ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true)) {
            throw new \RuntimeException('Nur PNG, JPEG, GIF oder WebP erlaubt.');
        }
        $info = @getimagesize($tmp);
        if (!$info || $info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > self::MAX_PIXELS) {
            throw new \RuntimeException('Das Bild ist beschädigt oder hat zu viele Pixel.');
        }
        return [$tmp, $mime, (int)$info[0], (int)$info[1]];
    }

    private static function load(string $tmp, string $mime): \GdImage
    {
        $src = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($tmp),
            'image/png'  => @imagecreatefrompng($tmp),
            'image/gif'  => @imagecreatefromgif($tmp),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmp) : false,
        };
        if (!$src) {
            throw new \RuntimeException('Das Bild konnte nicht gelesen werden.');
        }
        if (!imageistruecolor($src)) {
            imagepalettetotruecolor($src);
        }
        return $src;
    }

    private static function save(\GdImage $img, string $ext): string
    {
        self::ensureDir();
        $name = bin2hex(random_bytes(16)) . '.' . $ext;
        $path = self::dir() . '/' . $name;
        if ($ext === 'jpg') {
            imageinterlace($img, true);
        }
        $ok = $ext === 'png' ? imagepng($img, $path, 9) : imagejpeg($img, $path, 85);
        imagedestroy($img);
        if (!$ok) {
            throw new \RuntimeException('Das Bild konnte nicht gespeichert werden.');
        }
        @chmod($path, 0640);
        return $name;
    }

    private static function ensureDir(): void
    {
        $dir = self::dir();
        if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new \RuntimeException('Verzeichnis für Website-Bilder kann nicht angelegt werden.');
        }
    }
}
