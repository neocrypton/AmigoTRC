<?php
declare(strict_types=1);

namespace Amigo\Service;

use Amigo\Core\App;
use Amigo\Core\Badges;

/**
 * Avatare: einer der 30 Pixel-Badges (assets/img/badges.svg) oder ein eigenes
 * Bild. Eigene Bilder werden immer quadratisch auf 128×128 px neu berechnet
 * (PNG, Transparenz bleibt erhalten); kleine Pixel-Art wird ohne Weichzeichnen
 * vergrößert. Gespeichert in users.avatar als "b:<badge>" bzw. "u:<datei>".
 */
final class Avatar
{
    public const SIZE = 128;
    public const MAX_BYTES = 512 * 1024;
    private const MIN_EDGE = 8;
    private const MAX_EDGE = 2048;

    public static function dir(): string
    {
        return App::dataDir() . '/avatars';
    }

    /** Fester Standard-Badge pro Nutzer, falls nichts gewählt wurde. */
    public static function defaultBadge(int $userId): string
    {
        $keys = array_keys(Badges::ALL);
        return $keys[($userId - 1) % count($keys)];
    }

    /** @return array{type: string, value: string} type = badge | upload */
    public static function resolve(array $user): array
    {
        $raw = (string)($user['avatar'] ?? '');
        if (str_starts_with($raw, 'b:') && isset(Badges::ALL[substr($raw, 2)])) {
            return ['type' => 'badge', 'value' => substr($raw, 2)];
        }
        if (str_starts_with($raw, 'u:') && preg_match('/^[a-f0-9]{32}$/', substr($raw, 2))) {
            return ['type' => 'upload', 'value' => substr($raw, 2)];
        }
        return ['type' => 'badge', 'value' => self::defaultBadge((int)($user['id'] ?? 1))];
    }

    public static function file(string $name): ?string
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $name)) {
            return null;
        }
        $path = self::dir() . '/' . $name . '.png';
        return is_file($path) ? $path : null;
    }

    /**
     * Prüft und speichert ein hochgeladenes Avatarbild. Gibt den Dateinamen zurück.
     * @throws \RuntimeException mit verständlicher Meldung
     */
    public static function store(array $upload): string
    {
        if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException(($upload['error'] ?? 0) === UPLOAD_ERR_NO_FILE ? 'Bitte ein Bild auswählen.' : 'Upload fehlgeschlagen.');
        }
        $tmp = (string)$upload['tmp_name'];
        if (!is_uploaded_file($tmp)) {
            throw new \RuntimeException('Ungültiger Upload.');
        }
        if (filesize($tmp) > self::MAX_BYTES) {
            throw new \RuntimeException('Das Bild ist zu groß (max. 512 KB).');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        if (!in_array($mime, ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true)) {
            throw new \RuntimeException('Nur PNG, JPEG, GIF oder WebP erlaubt.');
        }
        $info = @getimagesize($tmp);
        if (!$info || min($info[0], $info[1]) < self::MIN_EDGE || max($info[0], $info[1]) > self::MAX_EDGE) {
            throw new \RuntimeException('Das Bild muss zwischen 8 und 2048 Pixel groß sein.');
        }
        $src = @imagecreatefromstring((string)file_get_contents($tmp));
        if (!$src) {
            throw new \RuntimeException('Das Bild konnte nicht gelesen werden.');
        }

        // Mittig quadratisch zuschneiden
        $w = imagesx($src);
        $h = imagesy($src);
        $side = min($w, $h);
        $sx = intdiv($w - $side, 2);
        $sy = intdiv($h - $side, 2);

        $dst = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        if ($side <= 64) {
            // Pixel-Art: harte Kanten statt Weichzeichnen
            imagecopyresized($dst, $src, 0, 0, $sx, $sy, self::SIZE, self::SIZE, $side, $side);
        } else {
            imagecopyresampled($dst, $src, 0, 0, $sx, $sy, self::SIZE, self::SIZE, $side, $side);
        }
        imagedestroy($src);

        $dir = self::dir();
        if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new \RuntimeException('Avatar-Verzeichnis kann nicht angelegt werden.');
        }
        $name = bin2hex(random_bytes(16));
        $ok = imagepng($dst, $dir . '/' . $name . '.png', 9);
        imagedestroy($dst);
        if (!$ok) {
            throw new \RuntimeException('Das Bild konnte nicht gespeichert werden.');
        }
        @chmod($dir . '/' . $name . '.png', 0640);
        return $name;
    }

    /** Entfernt die Bilddatei eines Nutzers (falls er ein eigenes Bild hatte). */
    public static function removeFile(array $user): void
    {
        $a = self::resolve($user);
        if ($a['type'] === 'upload' && ($path = self::file($a['value']))) {
            @unlink($path);
        }
    }
}
