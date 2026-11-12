<?php
declare(strict_types=1);

namespace Amigo\Service;

use Amigo\Core\App;
use Amigo\Core\Database;

/**
 * Gemeinsamer Zubehör-Katalog (Controller, Memory Cards, Kabel …).
 *
 * Jeder Nutzer kann Einträge anlegen und verwenden. Das erste Foto, das jemand zu einem
 * Eintrag hochlädt (sofern nicht abgewählt), wird als Standardbild in den Katalog kopiert
 * (data/accessories/). Eigene Fotos eines Nutzers ersetzen es nur für dessen Eintrag.
 */
final class AccessoryCatalog
{
    public static function dir(): string
    {
        $dir = App::dataDir() . '/accessories';
        if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new \RuntimeException('Verzeichnis für Katalog-Bilder kann nicht angelegt werden.');
        }
        return $dir;
    }

    public static function find(?int $id): ?array
    {
        return $id ? App::db()->one(
            'SELECT a.*, p.name AS platform_name FROM {accessories} a LEFT JOIN {platforms} p ON p.id = a.platform_id WHERE a.id = ?',
            [$id]
        ) : null;
    }

    /** Suche für die Live-Vorschläge (Name, optional nur eine Plattform). */
    public static function search(string $q, ?int $platformId, int $limit = 20): array
    {
        $params = [Database::like($q)];
        $sql = "SELECT a.id, a.name, a.manufacturer, a.image, a.platform_id, p.name AS platform_name,
                       (SELECT COUNT(*) FROM {items} i WHERE i.accessory_id = a.id) AS uses
                FROM {accessories} a LEFT JOIN {platforms} p ON p.id = a.platform_id
                WHERE a.name LIKE ? ESCAPE '!'";
        if ($platformId) {
            $sql .= ' AND (a.platform_id = ? OR a.platform_id IS NULL)';
            $params[] = $platformId;
        }
        $sql .= ' ORDER BY uses DESC, a.name LIMIT ' . max(1, min(50, $limit));
        return App::db()->all($sql, $params);
    }

    /** Vorhandenen Eintrag (gleicher Name + Plattform, ohne Groß/Klein) finden oder neu anlegen. */
    public static function findOrCreate(string $name, ?int $platformId, ?string $manufacturer, int $userId): int
    {
        $db = App::db();
        $name = mb_substr(trim(preg_replace('/\s+/u', ' ', $name)), 0, 150);
        $id = $platformId
            ? $db->value('SELECT id FROM {accessories} WHERE LOWER(name) = LOWER(?) AND platform_id = ?', [$name, $platformId])
            : $db->value('SELECT id FROM {accessories} WHERE LOWER(name) = LOWER(?) AND platform_id IS NULL', [$name]);
        if ($id) {
            return (int)$id;
        }
        $now = Database::now();
        return $db->insert('accessories', [
            'name'         => $name,
            'platform_id'  => $platformId,
            'manufacturer' => $manufacturer ? mb_substr($manufacturer, 0, 150) : null,
            'created_by'   => $userId,
            'created_at'   => $now,
            'updated_at'   => $now,
        ]);
    }

    public static function imagePath(?string $file, bool $thumb): ?string
    {
        if (!$file || !preg_match('/^[a-f0-9]{32}$/', $file)) {
            return null;
        }
        $path = App::dataDir() . '/accessories/' . $file . ($thumb ? '_t' : '') . '.jpg';
        return is_file($path) ? $path : null;
    }

    public static function imageUrl(array $accessory, bool $thumb = true): ?string
    {
        if (empty($accessory['image'])) {
            return null;
        }
        return url('accessory/image', ['id' => (int)$accessory['id'], 'v' => substr((string)$accessory['image'], 0, 8)] + ($thumb ? ['s' => 't'] : []));
    }

    /**
     * Setzt das Standardbild aus einem Foto des Nutzers, falls der Eintrag noch keins hat.
     * Die Bilddateien werden kopiert (Foto bleibt privat beim Nutzer, die Kopie gehört dem Katalog).
     */
    public static function adoptPhoto(int $accessoryId, int $userId, string $photoFile): bool
    {
        $db = App::db();
        $acc = $db->one('SELECT id, image FROM {accessories} WHERE id = ?', [$accessoryId]);
        if (!$acc || $acc['image']) {
            return false;
        }
        $full = ImageStore::path($userId, $photoFile, false);
        $thumb = ImageStore::path($userId, $photoFile, true);
        if (!$full || !$thumb) {
            return false;
        }
        $name = bin2hex(random_bytes(16));
        $dir = self::dir();
        if (!@copy($full, $dir . '/' . $name . '.jpg') || !@copy($thumb, $dir . '/' . $name . '_t.jpg')) {
            @unlink($dir . '/' . $name . '.jpg');
            return false;
        }
        // nur setzen, wenn zwischenzeitlich niemand anderes ein Bild gesetzt hat
        $db->run('UPDATE {accessories} SET image = ?, image_user_id = ?, updated_at = ? WHERE id = ? AND image IS NULL',
            [$name, $userId, Database::now(), $accessoryId]);
        if ($db->value('SELECT image FROM {accessories} WHERE id = ?', [$accessoryId]) !== $name) {
            self::removeFiles($name);
            return false;
        }
        return true;
    }

    public static function removeImage(int $id): void
    {
        $db = App::db();
        $file = $db->value('SELECT image FROM {accessories} WHERE id = ?', [$id]);
        $db->update('accessories', ['image' => null, 'image_user_id' => null, 'updated_at' => Database::now()], ['id' => $id]);
        if ($file) {
            self::removeFiles((string)$file);
        }
    }

    /** Löscht einen Katalog-Eintrag; Sammlungs-Einträge bleiben (nur die Verknüpfung entfällt). */
    public static function delete(int $id): void
    {
        $db = App::db();
        $file = $db->value('SELECT image FROM {accessories} WHERE id = ?', [$id]);
        $db->transaction(function (Database $db) use ($id) {
            $db->run('UPDATE {items} SET accessory_id = NULL WHERE accessory_id = ?', [$id]);
            $db->delete('accessories', ['id' => $id]);
        });
        if ($file) {
            self::removeFiles((string)$file);
        }
    }

    private static function removeFiles(string $file): void
    {
        foreach ([false, true] as $thumb) {
            $p = self::imagePath($file, $thumb);
            if ($p) {
                @unlink($p);
            }
        }
    }
}
