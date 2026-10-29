<?php
declare(strict_types=1);

namespace Amigo\Core;

/** Einfacher Schlüssel/Wert-Speicher für Laufzeit-Einstellungen. */
final class Settings
{
    private static array $cache = [];

    public static function get(Database $db, string $key, ?string $default = null): ?string
    {
        if (!array_key_exists($key, self::$cache)) {
            self::$cache[$key] = $db->value('SELECT svalue FROM {settings} WHERE skey = ?', [$key]);
        }
        return self::$cache[$key] ?? $default;
    }

    public static function bool(Database $db, string $key): bool
    {
        return self::get($db, $key, '0') === '1';
    }

    public static function set(Database $db, string $key, ?string $value): void
    {
        $exists = $db->value('SELECT COUNT(*) FROM {settings} WHERE skey = ?', [$key]);
        if ((int)$exists > 0) {
            $db->update('settings', ['svalue' => $value], ['skey' => $key]);
        } else {
            $db->insert('settings', ['skey' => $key, 'svalue' => $value]);
        }
        self::$cache[$key] = $value;
    }
}
