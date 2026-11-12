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
    public const VERSION = 13;

    public static function migrate(Database $db): void
    {
        $current = self::currentVersion($db);
        if ($current >= self::VERSION) {
            return;
        }
        $steps = [1 => self::v1(), 2 => self::v2(), 3 => self::v3(), 4 => self::v4(), 5 => self::v5(), 6 => self::v6(), 7 => self::v7(), 8 => self::v8(), 9 => [], 10 => self::v10(), 11 => self::v11(), 12 => self::v12(), 13 => self::v13()];
        foreach ($steps as $version => $statements) {
            if ($current < $version) {
                foreach ($statements as $ddl) {
                    $db->pdo()->exec(self::translate($db, $ddl));
                }
                if ($version === 8) {
                    \Amigo\Service\SiteDefaults::install($db);
                }
                if ($version === 9) {
                    // reCAPTCHA wurde durch den eigenen Bot-Schutz ersetzt: Texte auffrischen, alte Schlüssel entfernen
                    \Amigo\Service\SiteDefaults::refreshV9($db);
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

    /** v8: öffentliche Website (Seiten, FAQ, News, Links/Partner) – Standardinhalte über SiteDefaults. */
    private static function v8(): array
    {
        return [
            "CREATE TABLE {site_pages} (
                id %ID%,
                slug VARCHAR(30) NOT NULL UNIQUE,
                title VARCHAR(150) NOT NULL,
                body %TEXT% NOT NULL,
                enabled SMALLINT NOT NULL DEFAULT 1,
                updated_at %DT% NULL
            )%OPT%",
            "CREATE TABLE {site_faq} (
                id %ID%,
                question VARCHAR(255) NOT NULL,
                answer %TEXT% NOT NULL,
                sort %INT% NOT NULL DEFAULT 0,
                enabled SMALLINT NOT NULL DEFAULT 1
            )%OPT%",
            "CREATE TABLE {site_news} (
                id %ID%,
                title VARCHAR(200) NOT NULL,
                body %TEXT% NOT NULL,
                published SMALLINT NOT NULL DEFAULT 0,
                published_at %DT% NOT NULL,
                author_id %INT% NULL,
                created_at %DT% NOT NULL,
                updated_at %DT% NULL,
                FOREIGN KEY (author_id) REFERENCES {users} (id) ON DELETE SET NULL
            )%OPT%",
            "CREATE INDEX [site_news_pub] ON {site_news} (published, published_at)",
            "CREATE TABLE {site_links} (
                id %ID%,
                name VARCHAR(100) NOT NULL,
                url VARCHAR(500) NOT NULL,
                banner VARCHAR(40) NULL,
                sort %INT% NOT NULL DEFAULT 0,
                enabled SMALLINT NOT NULL DEFAULT 1
            )%OPT%",
        ];
    }

    /**
     * v13: Freunde, Blockieren, Likes für Sammlungen, Online-Status, Konto selbst löschen.
     * friendships: eine Zeile je Paar (requester → addressee), status pending|accepted.
     */
    private static function v13(): array
    {
        return [
            'ALTER TABLE {users} ADD COLUMN allow_friend_requests SMALLINT NOT NULL DEFAULT 0',
            'ALTER TABLE {users} ADD COLUMN friends_public SMALLINT NOT NULL DEFAULT 0',
            'ALTER TABLE {users} ADD COLUMN allow_likes SMALLINT NOT NULL DEFAULT 1',
            'ALTER TABLE {users} ADD COLUMN last_seen_at %BIG% NULL',
            'ALTER TABLE {users} ADD COLUMN delete_token VARCHAR(64) NULL',
            'ALTER TABLE {users} ADD COLUMN delete_expires %BIG% NULL',
            "CREATE TABLE {friendships} (
                requester_id %INT% NOT NULL,
                addressee_id %INT% NOT NULL,
                status VARCHAR(10) NOT NULL DEFAULT 'pending',
                created_at %DT% NOT NULL,
                accepted_at %DT% NULL,
                PRIMARY KEY (requester_id, addressee_id),
                FOREIGN KEY (requester_id) REFERENCES {users} (id) ON DELETE CASCADE,
                FOREIGN KEY (addressee_id) REFERENCES {users} (id) ON DELETE CASCADE
            )%OPT%",
            "CREATE INDEX [friendships_addressee] ON {friendships} (addressee_id, status)",
            "CREATE TABLE {user_blocks} (
                user_id %INT% NOT NULL,
                blocked_id %INT% NOT NULL,
                created_at %DT% NOT NULL,
                PRIMARY KEY (user_id, blocked_id),
                FOREIGN KEY (user_id) REFERENCES {users} (id) ON DELETE CASCADE,
                FOREIGN KEY (blocked_id) REFERENCES {users} (id) ON DELETE CASCADE
            )%OPT%",
            "CREATE INDEX [user_blocks_blocked] ON {user_blocks} (blocked_id)",
            "CREATE TABLE {collection_likes} (
                owner_id %INT% NOT NULL,
                user_id %INT% NOT NULL,
                created_at %DT% NOT NULL,
                PRIMARY KEY (owner_id, user_id),
                FOREIGN KEY (owner_id) REFERENCES {users} (id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES {users} (id) ON DELETE CASCADE
            )%OPT%",
            "CREATE INDEX [collection_likes_user] ON {collection_likes} (user_id)",
        ];
    }

    /**
     * v12: Konsolen über Plattformen (Bilder von TheGamesDB) und gemeinsamer Zubehör-Katalog.
     * items.accessory_id ohne Fremdschlüssel (SQLite kann ihn nicht nachträglich anlegen); beim
     * Löschen eines Katalog-Eintrags setzt AccessoryCatalog::delete() die Verweise auf NULL.
     */
    private static function v12(): array
    {
        return [
            'ALTER TABLE {platforms} ADD COLUMN console_img VARCHAR(255) NULL',
            'ALTER TABLE {platforms} ADD COLUMN controller_img VARCHAR(255) NULL',
            "CREATE TABLE {accessories} (
                id %ID%,
                name VARCHAR(150) NOT NULL,
                platform_id %INT% NULL,
                manufacturer VARCHAR(150) NULL,
                image VARCHAR(40) NULL,
                image_user_id %INT% NULL,
                created_by %INT% NULL,
                created_at %DT% NOT NULL,
                updated_at %DT% NOT NULL,
                FOREIGN KEY (platform_id) REFERENCES {platforms} (id) ON DELETE SET NULL,
                FOREIGN KEY (image_user_id) REFERENCES {users} (id) ON DELETE SET NULL,
                FOREIGN KEY (created_by) REFERENCES {users} (id) ON DELETE SET NULL
            )%OPT%",
            "CREATE INDEX [accessories_name] ON {accessories} (name)",
            'ALTER TABLE {items} ADD COLUMN accessory_id %INT% NULL',
            "CREATE INDEX [items_accessory] ON {items} (accessory_id)",
        ];
    }

    /** v11: zuletzt genutztes Farbschema je Nutzer (für die Admin-Statistik). */
    private static function v11(): array
    {
        return [
            'ALTER TABLE {users} ADD COLUMN theme VARCHAR(10) NULL',
        ];
    }

    /** v10: Durchgespielt-Datum exakt (Tag) oder ungefähr (nur Monat). NULL = exakt. */
    private static function v10(): array
    {
        return [
            'ALTER TABLE {plays} ADD COLUMN finished_precision VARCHAR(5) NULL',
        ];
    }

    /** v7: E-Mail-Benachrichtigung bei neuen PNs (Einstellung + „schon benachrichtigt bis Nachricht X“). */
    private static function v7(): array
    {
        return [
            'ALTER TABLE {users} ADD COLUMN pm_notify SMALLINT NOT NULL DEFAULT 1',
            'ALTER TABLE {conversation_users} ADD COLUMN notified_id %INT% NOT NULL DEFAULT 0',
        ];
    }

    /** v6: Private Nachrichten (Unterhaltungen mit bis zu 10 Teilnehmern). */
    private static function v6(): array
    {
        return [
            "CREATE TABLE {conversations} (
                id %ID%,
                subject VARCHAR(150) NOT NULL,
                creator_id %INT% NULL,
                created_at %DT% NOT NULL,
                last_message_at %DT% NOT NULL,
                last_message_id %INT% NOT NULL DEFAULT 0,
                FOREIGN KEY (creator_id) REFERENCES {users} (id) ON DELETE SET NULL
            )%OPT%",
            "CREATE TABLE {conversation_users} (
                conversation_id %INT% NOT NULL,
                user_id %INT% NOT NULL,
                status VARCHAR(10) NOT NULL DEFAULT 'active',
                last_read_id %INT% NOT NULL DEFAULT 0,
                joined_at %DT% NOT NULL,
                PRIMARY KEY (conversation_id, user_id),
                FOREIGN KEY (conversation_id) REFERENCES {conversations} (id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES {users} (id) ON DELETE CASCADE
            )%OPT%",
            "CREATE INDEX [conv_users_user] ON {conversation_users} (user_id, status)",
            "CREATE TABLE {messages} (
                id %ID%,
                conversation_id %INT% NOT NULL,
                user_id %INT% NULL,
                body %TEXT% NOT NULL,
                created_at %DT% NOT NULL,
                FOREIGN KEY (conversation_id) REFERENCES {conversations} (id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES {users} (id) ON DELETE SET NULL
            )%OPT%",
            "CREATE INDEX [messages_conv] ON {messages} (conversation_id, id)",
        ];
    }

    /** v5: Status der automatischen TheGamesDB-Zuordnung ('review' = prüfen, 'none' = nichts gefunden). */
    private static function v5(): array
    {
        return [
            'ALTER TABLE {items} ADD COLUMN scrape_status VARCHAR(10) NULL',
            'ALTER TABLE {plays} ADD COLUMN scrape_status VARCHAR(10) NULL',
        ];
    }

    /** v4: Avatar (Pixel-Badge oder eigenes Bild). */
    private static function v4(): array
    {
        return ['ALTER TABLE {users} ADD COLUMN avatar VARCHAR(48) NULL'];
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
