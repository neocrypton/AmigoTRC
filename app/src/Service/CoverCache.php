<?php
declare(strict_types=1);

namespace Amigo\Service;

use Amigo\Core\App;

/**
 * Lokaler Cache für Cover und Plattform-Icons von TheGamesDB.
 *
 * Der Server lädt jedes Bild einmal vom TheGamesDB-CDN, prüft und rechnet es neu
 * und liefert es danach selbst aus. Fehlt eine kleine Variante auf dem CDN,
 * wird auf eine größere ausgewichen und verkleinert. Browser der Nutzer
 * kontaktieren dadurch keinen fremden Server.
 */
final class CoverCache
{
    /** Erlaubte Pfade relativ zu .../images/ (keine beliebigen URLs → kein SSRF). */
    private const PATH = '#^(thumb|small|cropped_center_thumb|medium|large|original)/[A-Za-z0-9_\-]+(/[A-Za-z0-9_\-]+)*\.(jpe?g|png|gif|webp)$#';

    /** Zielbreite je Größenstufe; Ausweich-Reihenfolge, wenn eine Stufe fehlt. */
    private const SIZES = ['thumb' => 300, 'small' => 300, 'cropped_center_thumb' => 300, 'medium' => 800, 'large' => 1200, 'original' => 1200];
    private const FALLBACK = ['medium', 'large', 'original'];
    private const MISS_TTL = 86400;
    private const MAX_BYTES = 8 * 1024 * 1024;

    public static function cdnBase(): string
    {
        return rtrim((string)App::config('tgdb_cdn', 'https://cdn.thegamesdb.net/images/'), '/') . '/';
    }

    /** Wandelt eine gespeicherte CDN-URL in einen relativen Pfad um (oder null). */
    public static function pathFromUrl(?string $url): ?string
    {
        if (!$url) {
            return null;
        }
        foreach (['https://cdn.thegamesdb.net/images/', self::cdnBase()] as $base) {
            if (str_starts_with($url, $base)) {
                $path = substr($url, strlen($base));
                return self::validPath($path) ? $path : null;
            }
        }
        return null;
    }

    public static function validPath(string $path): bool
    {
        return (bool)preg_match(self::PATH, $path) && !str_contains($path, '..');
    }

    /**
     * Liefert den Pfad der lokalen Datei, lädt sie bei Bedarf.
     * @return array{0: string, 1: string}|null [Dateipfad, MIME-Typ]
     */
    public static function get(string $path, callable $mayFetch): ?array
    {
        if (!self::validPath($path)) {
            return null;
        }
        $dir = App::dataDir() . '/covers';
        if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
            return null;
        }
        $png = str_ends_with(strtolower($path), '.png');
        $file = $dir . '/' . sha1($path) . ($png ? '.png' : '.jpg');
        if (is_file($file)) {
            return [$file, $png ? 'image/png' : 'image/jpeg'];
        }
        $miss = $dir . '/' . sha1($path) . '.miss';
        if (is_file($miss) && time() - (int)filemtime($miss) < self::MISS_TTL) {
            return null;
        }
        if (!$mayFetch()) {
            return null;
        }

        [$size, $rest] = explode('/', $path, 2);
        $candidates = array_unique([$size, ...self::FALLBACK]);
        foreach ($candidates as $variant) {
            $bytes = self::download(self::cdnBase() . $variant . '/' . $rest);
            if ($bytes !== null && self::store($bytes, $file, self::SIZES[$size], $png)) {
                @unlink($miss);
                return [$file, $png ? 'image/png' : 'image/jpeg'];
            }
        }
        @touch($miss);
        return null;
    }

    private static function download(string $url): ?string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS | CURLPROTO_HTTP,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_USERAGENT      => 'AMIGO-RetroCollector/' . AMIGO_VERSION,
                CURLOPT_MAXFILESIZE    => self::MAX_BYTES,
            ]);
            $body = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
            return ($body !== false && $status === 200 && strlen((string)$body) <= self::MAX_BYTES) ? (string)$body : null;
        }
        $ctx = stream_context_create(['http' => ['timeout' => 15, 'follow_location' => 0, 'ignore_errors' => true]]);
        $body = @file_get_contents($url, false, $ctx, 0, self::MAX_BYTES + 1);
        $ok = false;
        foreach ($http_response_header ?? [] as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) {
                $ok = $m[1] === '200';
            }
        }
        return ($ok && $body !== false && strlen($body) <= self::MAX_BYTES) ? $body : null;
    }

    /** Prüft, verkleinert und speichert das Bild neu (entfernt Metadaten, verhindert untergeschobene Dateien). */
    private static function store(string $bytes, string $file, int $maxWidth, bool $png): bool
    {
        $info = @getimagesizefromstring($bytes);
        if (!$info || $info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > 40_000_000
            || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true)) {
            return false;
        }
        $src = @imagecreatefromstring($bytes);
        if (!$src) {
            return false;
        }
        $w = imagesx($src);
        $h = imagesy($src);
        $scale = min(1, $maxWidth / $w);
        $nw = max(1, (int)round($w * $scale));
        $nh = max(1, (int)round($h * $scale));
        $dst = imagecreatetruecolor($nw, $nh);
        if ($png) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        }
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if ($png) {
            $ok = imagepng($dst, $tmp, 6);
        } else {
            imageinterlace($dst, true);
            $ok = imagejpeg($dst, $tmp, 82);
        }
        imagedestroy($src);
        imagedestroy($dst);
        return $ok && @rename($tmp, $file);
    }
}
