<?php
declare(strict_types=1);

namespace Amigo\Core;

/**
 * Datenbankschema für MySQL/MariaDB und SQLite inkl. einfacher Versionierung.
 *
 * Platzhalter in den DDL-Strings:
 *   %ID%   Primärschlüssel mit Autoinkrement
 *   %INT%  Ganzzahl für Fremdschlüssel / Zähler
 *   %BIG%  große Ganzzahl
 *   %TEXT% langer Text
 *   %DT%   Datum + Uhrzeit (UTC, 'Y-m-d H:i:s')
 *   %OPT%  Tabellenoptionen (nur MySQL)
 */
final class Schema
{
    public const VERSION = 3;

    public static function migrate(Database $db): void
    {
        $current = self::currentVersion($db);
        if ($current >= self::VERSION) {
            return;
        }
        $steps = [1 => self::v1(), 2 => self::v2(), 3 => self::v3()];
        foreach ($steps as $version => $statements) {
            if ($current < $version) {
                foreach ($statements as $ddl) {
                    $db->pdo()->exec(self::translate($db, $ddl));
                }
                Settings::set($db, 'schema_version', (string)$version);
            }
        }
        Settings::set($db, 'schema_version', (string)self::VERSION);
    }

    public static function currentVersion(Database $db): int
    {
        try {
            $v = $db->value("SELECT svalue FROM {settings} WHERE skey = 'schema_version'");
            return (int)$v;
        } catch (\PDOException) {
            return 0;
        }
    }

    private static function translate(Database $db, string $ddl): string
    {
        $mysql = $db->driver === 'mysql';
        $map = [
            '%ID%'   => $mysql ? 'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT',
            '%INT%'  => $mysql ? 'INT UNSIGNED' : 'INTEGER',
            '%BIG%'  => $mysql ? 'BIGINT' : 'INTEGER',
            '%TEXT%' => $mysql ? 'MEDIUMTEXT' : 'TEXT',
            '%DT%'   => $mysql ? 'DATETIME' : 'TEXT',
            '%OPT%'  => $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '',
        ];
        $ddl = strtr($ddl, $map);
        // Index-Namen sind in SQLite datenbankweit eindeutig -> mit Präfix versehen.
        $ddl = preg_replace_callback('/\[([a-z_]+)\]/', fn ($m) => $db->quoteIdent($db->prefix() . $m[1]), $ddl);
        return $db->sql($ddl);
    }

    /** v3: Konto-Aktivierung (E-Mail-Bestätigung / Admin-Freischaltung). */
    private static function v3(): array
    {
        return [
            'ALTER TABLE {users} ADD COLUMN pending VARCHAR(10) NULL',
            'ALTER TABLE {users} ADD COLUMN email_verified_at %DT% NULL',
            'ALTER TABLE {users} ADD COLUMN verify_token VARCHAR(64) NULL',
            'ALTER TABLE {users} ADD COLUMN verify_expires %BIG% NULL',
        ];
    }

    /** v2: eigener TheGamesDB-Key pro Nutzer, öffentliche Sammlung/Durchgespielt-Liste. */
    private static function v2(): array
    {
        return [
            'ALTER TABLE {users} ADD COLUMN tgdb_api_key TEXT NULL',
            'ALTER TABLE {users} ADD COLUMN tgdb_allowance %INT% NULL',
            'ALTER TABLE {users} ADD COLUMN collection_public SMALLINT NOT NULL DEFAULT 0',
            'ALTER TABLE {users} ADD COLUMN plays_public SMALLINT NOT NULL DEFAULT 0',
        ];
    }

    /** @return string[] */
    private static function v1(): array
    {
        return [
            "CREATE TABLE {settings} (
                skey VARCHAR(64) NOT NULL PRIMARY KEY,
                svalue %TEXT% NULL
            )%OPT%",

            "CREATE TABLE {users} (
                id %ID%,
                username VARCHAR(40) NOT NULL,
                email VARCHAR(190) NULL,
                password_hash VARCHAR(255) NOT NULL,
                role VARCHAR(10) NOT NULL DEFAULT 'user',
                is_active SMALLINT NOT NULL DEFAULT 1,
                totp_secret TEXT NULL,
                totp_enabled SMALLINT NOT NULL DEFAULT 0,
                totp_last_step %BIG% NOT NULL DEFAULT 0,
                recovery_codes TEXT NULL,
                created_at %DT% NOT NULL,
                last_login_at %DT% NULL,
                UNIQUE (username),
                UNIQUE (email)
            )%OPT%",

            "CREATE TABLE {platforms} (
                id %ID%,
                tgdb_id %INT% NULL,
                name VARCHAR(150) NOT NULL,
                alias VARCHAR(150) NULL,
                manufacturer VARCHAR(150) NULL,
                developer VARCHAR(150) NULL,
                media VARCHAR(100) NULL,
                cpu VARCHAR(255) NULL,
                memory VARCHAR(255) NULL,
                overview %TEXT% NULL,
                icon VARCHAR(255) NULL,
                created_at %DT% NOT NULL,
                UNIQUE (tgdb_id)
            )%OPT%",
            "CREATE INDEX [platforms_name] ON {platforms} (name)",

            "CREATE TABLE {games} (
                id %ID%,
                tgdb_id %INT% NULL,
                platform_id %INT% NULL,
                title VARCHAR(255) NOT NULL,
                release_date VARCHAR(10) NULL,
                players SMALLINT NULL,
                developers VARCHAR(255) NULL,
                publishers VARCHAR(255) NULL,
                genres VARCHAR(255) NULL,
                overview %TEXT% NULL,
                boxart_thumb VARCHAR(255) NULL,
                boxart_large VARCHAR(255) NULL,
                created_at %DT% NOT NULL,
                updated_at %DT% NOT NULL,
                UNIQUE (tgdb_id),
                FOREIGN KEY (platform_id) REFERENCES {platforms} (id) ON DELETE SET NULL
            )%OPT%",
            "CREATE INDEX [games_title] ON {games} (title)",

            "CREATE TABLE {items} (
                id %ID%,
                user_id %INT% NOT NULL,
                kind VARCHAR(12) NOT NULL DEFAULT 'game',
                game_id %INT% NULL,
                platform_id %INT% NULL,
                title VARCHAR(255) NOT NULL,
                region VARCHAR(12) NULL,
                completeness VARCHAR(12) NULL,
                cond_overall SMALLINT NULL,
                cond_media SMALLINT NULL,
                cond_box SMALLINT NULL,
                cond_manual SMALLINT NULL,
                condition_notes %TEXT% NULL,
                purchase_date DATE NULL,
                price_cents %INT% NULL,
                purchase_place VARCHAR(150) NULL,
                notes %TEXT% NULL,
                created_at %DT% NOT NULL,
                updated_at %DT% NOT NULL,
                FOREIGN KEY (user_id) REFERENCES {users} (id) ON DELETE CASCADE,
                FOREIGN KEY (game_id) REFERENCES {games} (id) ON DELETE SET NULL,
                FOREIGN KEY (platform_id) REFERENCES {platforms} (id) ON DELETE SET NULL
            )%OPT%",
            "CREATE INDEX [items_user_platform] ON {items} (user_id, platform_id)",
            "CREATE INDEX [items_user_title] ON {items} (user_id, title)",

            "CREATE TABLE {photos} (
                id %ID%,
                item_id %INT% NOT NULL,
                user_id %INT% NOT NULL,
                file VARCHAR(64) NOT NULL,
                width SMALLINT NOT NULL,
                height SMALLINT NOT NULL,
                bytes %INT% NOT NULL,
                caption VARCHAR(500) NULL,
                sort SMALLINT NOT NULL DEFAULT 0,
                created_at %DT% NOT NULL,
                FOREIGN KEY (item_id) REFERENCES {items} (id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES {users} (id) ON DELETE CASCADE
            )%OPT%",
            "CREATE INDEX [photos_item] ON {photos} (item_id, sort)",

            "CREATE TABLE {plays} (
                id %ID%,
                user_id %INT% NOT NULL,
                game_id %INT% NULL,
                platform_id %INT% NULL,
                title VARCHAR(255) NOT NULL,
                finished_on DATE NULL,
                playtime_minutes %INT% NULL,
                score %BIG% NULL,
                rating SMALLINT NULL,
                review %TEXT% NULL,
                created_at %DT% NOT NULL,
                updated_at %DT% NOT NULL,
                FOREIGN KEY (user_id) REFERENCES {users} (id) ON DELETE CASCADE,
                FOREIGN KEY (game_id) REFERENCES {games} (id) ON DELETE SET NULL,
                FOREIGN KEY (platform_id) REFERENCES {platforms} (id) ON DELETE SET NULL
            )%OPT%",
            "CREATE INDEX [plays_user_date] ON {plays} (user_id, finished_on)",

            "CREATE TABLE {attempts} (
                id %ID%,
                bucket VARCHAR(100) NOT NULL,
                created_at %BIG% NOT NULL
            )%OPT%",
            "CREATE INDEX [attempts_bucket] ON {attempts} (bucket, created_at)",

            "CREATE TABLE {api_cache} (
                ckey VARCHAR(64) NOT NULL PRIMARY KEY,
                payload %TEXT% NOT NULL,
                expires_at %BIG% NOT NULL
            )%OPT%",
        ];
    }
}
