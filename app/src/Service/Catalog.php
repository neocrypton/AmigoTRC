<?php
declare(strict_types=1);

namespace Amigo\Service;

use Amigo\Core\App;
use Amigo\Core\Crypto;
use Amigo\Core\Database;
use Amigo\Core\Settings;

/** Lokaler Katalog (Plattformen + Spiele), befüllt manuell oder aus TheGamesDB. */
final class Catalog
{
    public static function tgdb(): TheGamesDb
    {
        return new TheGamesDb(self::apiKey(), App::db());
    }

    /** API-Key aus dem Adminbereich (verschlüsselt gespeichert), sonst aus config/config.php. */
    public static function apiKey(): string
    {
        $stored = Settings::get(App::db(), 'tgdb_api_key');
        if ($stored !== null && $stored !== '') {
            return (string)Crypto::decrypt($stored);
        }
        return (string)App::config('tgdb_api_key', '');
    }

    public static function apiKeySource(): string
    {
        $stored = Settings::get(App::db(), 'tgdb_api_key');
        if ($stored !== null && $stored !== '') {
            return 'admin';
        }
        return App::config('tgdb_api_key', '') !== '' ? 'config' : 'none';
    }

    public static function saveApiKey(?string $key): void
    {
        Settings::set(App::db(), 'tgdb_api_key', $key === null || $key === '' ? null : Crypto::encrypt($key));
    }

    public static function validApiKey(string $key): bool
    {
        return (bool)preg_match('/^[A-Za-z0-9]{16,128}$/', $key);
    }

    public static function platforms(): array
    {
        return App::db()->all('SELECT id, name, tgdb_id FROM {platforms} ORDER BY name');
    }

    public static function platformExists(?int $id): bool
    {
        return $id !== null && (int)App::db()->value('SELECT COUNT(*) FROM {platforms} WHERE id = ?', [$id]) === 1;
    }

    /** Legt eine Plattform aus TheGamesDB-Daten an bzw. aktualisiert sie. Gibt die lokale ID zurück. */
    public static function upsertPlatform(array $p): int
    {
        $db = App::db();
        $row = [
            'name'         => mb_substr($p['name'], 0, 150),
            'alias'        => $p['alias'] ? mb_substr($p['alias'], 0, 150) : null,
            'manufacturer' => $p['manufacturer'] ? mb_substr($p['manufacturer'], 0, 150) : null,
            'developer'    => $p['developer'] ? mb_substr($p['developer'], 0, 150) : null,
            'media'        => $p['media'] ? mb_substr($p['media'], 0, 100) : null,
            'cpu'          => $p['cpu'],
            'memory'       => $p['memory'],
            'overview'     => $p['overview'],
            'icon'         => $p['icon'],
        ];
        $id = $db->value('SELECT id FROM {platforms} WHERE tgdb_id = ?', [$p['tgdb_id']]);
        if ($id) {
            $db->update('platforms', $row, ['id' => (int)$id]);
            return (int)$id;
        }
        return $db->insert('platforms', $row + ['tgdb_id' => $p['tgdb_id'], 'created_at' => Database::now()]);
    }

    public static function platformByTgdb(int $tgdbId): ?int
    {
        $id = App::db()->value('SELECT id FROM {platforms} WHERE tgdb_id = ?', [$tgdbId]);
        if ($id) {
            return (int)$id;
        }
        $p = self::tgdb()->platform($tgdbId);
        return $p ? self::upsertPlatform($p) : null;
    }

    /** Übernimmt ein Spiel aus TheGamesDB in den lokalen Katalog (inkl. Plattform). */
    public static function importGame(int $tgdbId): ?array
    {
        $db = App::db();
        $existing = $db->one('SELECT * FROM {games} WHERE tgdb_id = ?', [$tgdbId]);
        if ($existing) {
            return $existing;
        }
        $g = self::tgdb()->game($tgdbId);
        if (!$g || $g['title'] === '') {
            return null;
        }
        $platformId = $g['platform'] ? self::upsertPlatform($g['platform']) : self::platformByTgdb($g['platform_tgdb_id']);
        $now = Database::now();
        $id = $db->insert('games', [
            'tgdb_id'      => $g['tgdb_id'],
            'platform_id'  => $platformId,
            'title'        => mb_substr($g['title'], 0, 255),
            'release_date' => $g['release_date'],
            'players'      => $g['players'],
            'developers'   => $g['developers'],
            'publishers'   => $g['publishers'],
            'genres'       => $g['genres'],
            'overview'     => $g['overview'],
            'boxart_thumb' => $g['boxart_thumb'],
            'boxart_large' => $g['boxart_large'],
            'created_at'   => $now,
            'updated_at'   => $now,
        ]);
        return $db->one('SELECT * FROM {games} WHERE id = ?', [$id]);
    }

    /**
     * Ermittelt anhand der Formularfelder (tgdb_game_id / game_id) das verknüpfte Spiel.
     * Gibt [game|null, hinweis|null] zurück. Probleme mit TheGamesDB blockieren das
     * Speichern nicht – der Eintrag wird dann einfach ohne Verknüpfung angelegt.
     */
    public static function resolveGameFromInput(): array
    {
        $tgdbId = input_int('tgdb_game_id', 1);
        if ($tgdbId) {
            try {
                $game = self::importGame($tgdbId);
                return [$game, $game ? null : 'Das Spiel wurde bei TheGamesDB nicht gefunden – Eintrag ohne Verknüpfung.'];
            } catch (\Throwable) {
                return [null, 'TheGamesDB ist gerade nicht erreichbar – Eintrag ohne Verknüpfung.'];
            }
        }
        $gameId = input_int('game_id', 1);
        if ($gameId) {
            return [App::db()->one('SELECT * FROM {games} WHERE id = ?', [$gameId]), null];
        }
        return [null, null];
    }
}
