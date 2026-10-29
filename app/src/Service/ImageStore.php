<?php
declare(strict_types=1);

namespace Amigo\Service;

use Amigo\Core\App;

/**
 * Sicherer Bild-Upload.
 *
 * - Prüft Upload-Status, Größe, echten MIME-Typ (finfo) und Bildmaße
 * - Lädt das Bild mit GD und speichert es NEU als JPEG/WebP
 *   (entfernt EXIF/GPS-Daten und macht eingeschmuggelten Code unbrauchbar)
 * - Zufällige Dateinamen, Ablage außerhalb öffentlich erreichbarer Pfade,
 *   Auslieferung nur über einen Controller mit Besitzer-Prüfung
 */
final class ImageStore
{
    private const MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    private const MAX_PIXELS = 40_000_000;
    public const SIZES = ['full' => 1600, 'thumb' => 480];

    public static function dir(int $userId): string
    {
        $dir = App::dataDir() . '/uploads/' . $userId;
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new \RuntimeException('Upload-Verzeichnis kann nicht angelegt werden.');
        }
        return $dir;
    }

    public static function maxBytes(): int
    {
        return (int)App::config('upload_max_mb', 10) * 1024 * 1024;
    }

    /** Normalisiert $_FILES['x'] bei multiple-Uploads zu einer Liste. */
    public static function normalize(?array $files): array
    {
        if (!$files || !isset($files['name'])) {
            return [];
        }
        if (!is_array($files['name'])) {
            return [$files];
        }
        $list = [];
        foreach ($files['name'] as $i => $_) {
            $list[] = [
                'name'     => $files['name'][$i],
                'type'     => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error'    => $files['error'][$i],
                'size'     => $files['size'][$i],
            ];
        }
        return $list;
    }

    /**
     * Speichert ein hochgeladenes Bild.
     * @return array{file:string,width:int,height:int,bytes:int}
     */
    public static function store(array $upload, int $userId): array
    {
        if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException(match ($upload['error'] ?? 0) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Datei ist zu groß.',
                UPLOAD_ERR_PARTIAL => 'Upload wurde unterbrochen.',
                default => 'Upload fehlgeschlagen.',
            });
        }
        $tmp = (string)$upload['tmp_name'];
        if (!is_uploaded_file($tmp)) {
            throw new \RuntimeException('Ungültiger Upload.');
        }
        if (filesize($tmp) > self::maxBytes()) {
            throw new \RuntimeException('Datei ist zu groß.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        if (!in_array($mime, self::MIMES, true)) {
            throw new \RuntimeException('Nur JPEG, PNG, WebP oder GIF erlaubt.');
        }
        $info = @getimagesize($tmp);
        if (!$info || $info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > self::MAX_PIXELS) {
            throw new \RuntimeException('Bild ist beschädigt oder zu groß.');
        }
        self::ensureMemory($info[0] * $info[1]);

        $src = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($tmp),
            'image/png'  => @imagecreatefrompng($tmp),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmp) : false,
            'image/gif'  => @imagecreatefromgif($tmp),
        };
        if (!$src) {
            throw new \RuntimeException('Bild konnte nicht gelesen werden.');
        }
        if ($mime === 'image/jpeg') {
            $src = self::applyExifOrientation($src, $tmp);
        }

        $name = bin2hex(random_bytes(16));
        $dir = self::dir($userId);
        $full = self::resize($src, self::SIZES['full']);
        $thumb = self::resize($src, self::SIZES['thumb']);
        imagedestroy($src);

        self::save($full, $dir . '/' . $name . '.jpg');
        self::save($thumb, $dir . '/' . $name . '_t.jpg');
        $w = imagesx($full);
        $h = imagesy($full);
        imagedestroy($full);
        imagedestroy($thumb);

        return ['file' => $name, 'width' => $w, 'height' => $h, 'bytes' => (int)filesize($dir . '/' . $name . '.jpg')];
    }

    public static function path(int $userId, string $file, bool $thumb): ?string
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $file)) {
            return null;
        }
        $path = App::dataDir() . '/uploads/' . $userId . '/' . $file . ($thumb ? '_t' : '') . '.jpg';
        return is_file($path) ? $path : null;
    }

    public static function remove(int $userId, string $file): void
    {
        foreach ([false, true] as $thumb) {
            $p = self::path($userId, $file, $thumb);
            if ($p) {
                @unlink($p);
            }
        }
    }

    /** GD braucht ca. 5 Byte pro Pixel; bei knappem memory_limit lieber sauber abbrechen. */
    private static function ensureMemory(int $pixels): void
    {
        $needed = $pixels * 5 + 3 * self::SIZES['full'] ** 2 * 4 + memory_get_usage();
        $limit = self::bytes((string)ini_get('memory_limit'));
        if ($limit > 0 && $needed > $limit) {
            @ini_set('memory_limit', (string)min($needed + 32 * 1024 * 1024, 512 * 1024 * 1024));
            $limit = self::bytes((string)ini_get('memory_limit'));
            if ($limit > 0 && $needed > $limit) {
                throw new \RuntimeException('Bild hat zu viele Pixel für diesen Server – bitte verkleinern.');
            }
        }
    }

    private static function bytes(string $v): int
    {
        $v = trim($v);
        if ($v === '' || $v === '-1') {
            return -1;
        }
        $n = (int)$v;
        return match (strtolower(substr($v, -1))) {
            'g' => $n * 1024 ** 3,
            'm' => $n * 1024 ** 2,
            'k' => $n * 1024,
            default => $n,
        };
    }

    private static function resize(\GdImage $src, int $max): \GdImage
    {
        $w = imagesx($src);
        $h = imagesy($src);
        $scale = min(1, $max / max($w, $h));
        $nw = max(1, (int)round($w * $scale));
        $nh = max(1, (int)round($h * $scale));
        $dst = imagecreatetruecolor($nw, $nh);
        // Transparenz auf dunklen Hintergrund legen (JPEG kennt kein Alpha).
        imagefill($dst, 0, 0, imagecolorallocate($dst, 24, 20, 40));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        return $dst;
    }

    private static function save(\GdImage $img, string $path): void
    {
        imageinterlace($img, true); // progressive JPEG: lädt bei langsamer Verbindung schrittweise
        if (!imagejpeg($img, $path, 82)) {
            throw new \RuntimeException('Bild konnte nicht gespeichert werden.');
        }
        @chmod($path, 0640);
    }

    private static function applyExifOrientation(\GdImage $img, string $file): \GdImage
    {
        if (!function_exists('exif_read_data')) {
            return $img;
        }
        $exif = @exif_read_data($file);
        $o = (int)($exif['Orientation'] ?? 1);
        $rotated = match ($o) {
            3 => imagerotate($img, 180, 0),
            6 => imagerotate($img, -90, 0),
            8 => imagerotate($img, 90, 0),
            default => null,
        };
        if ($rotated) {
            imagedestroy($img);
            return $rotated;
        }
        return $img;
    }
}
