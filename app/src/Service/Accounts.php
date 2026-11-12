<?php
declare(strict_types=1);

namespace Amigo\Service;

use Amigo\Core\App;
use Amigo\Core\Crypto;
use Amigo\Core\Database;
use Amigo\Core\Settings;

/**
 * Aktivierung neuer Konten: sofort, per E-Mail-Bestätigung, durch den Admin
 * oder beides nacheinander. Wartende Konten sind gesperrt (is_active = 0) und
 * tragen in `pending` den nächsten Schritt ('email' oder 'admin').
 */
final class Accounts
{
    public const MODES = [
        'email_admin' => 'E-Mail bestätigen, danach Freischaltung durch Admin',
        'admin'       => 'Freischaltung durch Admin',
        'email'       => 'E-Mail-Bestätigung durch den Nutzer',
        'none'        => 'Sofort aktiv (nicht empfohlen)',
    ];
    private const TOKEN_TTL = 172800; // 48 Stunden
    private const STALE_DAYS = 7;

    public static function mode(): string
    {
        $mode = (string)Settings::get(App::db(), 'activation_mode', 'admin');
        return array_key_exists($mode, self::MODES) ? $mode : 'admin';
    }

    public static function needsEmail(string $mode): bool
    {
        return $mode === 'email' || $mode === 'email_admin';
    }

    public static function emailRequired(): bool
    {
        return self::needsEmail(self::mode()) || Settings::bool(App::db(), 'require_email');
    }

    /** Öffentliche Adresse der Seite für Links in E-Mails (nicht aus dem Host-Header einer Anfrage). */
    public static function baseUrl(): string
    {
        $stored = (string)Settings::get(App::db(), 'base_url', '');
        return $stored !== '' ? rtrim($stored, '/') . '/' : self::guessBaseUrl();
    }

    public static function guessBaseUrl(): string
    {
        $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
        return (App::isHttps() ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $dir . '/';
    }

    /** Status für ein neu registriertes Konto. */
    public static function initialState(): array
    {
        return match (self::mode()) {
            'none'  => ['is_active' => 1, 'pending' => null],
            'admin' => ['is_active' => 0, 'pending' => 'admin'],
            default => ['is_active' => 0, 'pending' => 'email'],
        };
    }

    /** Erzeugt einen Bestätigungslink und verschickt ihn. */
    public static function sendVerification(array $user): void
    {
        $token = bin2hex(random_bytes(32));
        App::db()->update('users', [
            'verify_token'   => Crypto::hmac('verify', $token),
            'verify_expires' => time() + self::TOKEN_TTL,
        ], ['id' => $user['id']]);
        $link = self::baseUrl() . 'index.php?' . http_build_query(['r' => 'verify', 'u' => (int)$user['id'], 't' => $token]);
        $site = (string)App::config('site_name', 'AMIGO!');
        Mailer::send((string)$user['email'], $site . ': E-Mail-Adresse bestätigen', implode("\n", [
            'Hallo ' . $user['username'] . ',',
            '',
            'bitte bestätige deine E-Mail-Adresse mit einem Klick auf diesen Link:',
            '',
            $link,
            '',
            'Der Link ist 48 Stunden gültig. Wenn du dich nicht registriert hast, ignoriere diese Mail einfach.',
            '',
            '– ' . $site,
        ]));
    }

    /**
     * Prüft einen Bestätigungslink. Gibt den aktualisierten Nutzer zurück oder null.
     */
    public static function verify(int $userId, string $token): ?array
    {
        $db = App::db();
        $user = $db->one('SELECT * FROM {users} WHERE id = ?', [$userId]);
        if (!$user || !$user['verify_token'] || (int)$user['verify_expires'] < time()
            || !preg_match('/^[a-f0-9]{64}$/', $token)
            || !hash_equals((string)$user['verify_token'], Crypto::hmac('verify', $token))) {
            return null;
        }
        $update = ['email_verified_at' => Database::now(), 'verify_token' => null, 'verify_expires' => null];
        if ($user['pending'] === 'email') {
            if (self::mode() === 'email_admin') {
                $update['pending'] = 'admin';
            } else {
                $update['pending'] = null;
                $update['is_active'] = 1;
            }
        }
        $db->update('users', $update, ['id' => $userId]);
        $user = $db->one('SELECT * FROM {users} WHERE id = ?', [$userId]);
        if ($user['pending'] === 'admin') {
            self::notifyAdmins($user);
        }
        return $user;
    }

    public static function approve(array $user): void
    {
        App::db()->update('users', ['is_active' => 1, 'pending' => null], ['id' => $user['id']]);
        if (!empty($user['email']) && Mailer::configured()) {
            $site = (string)App::config('site_name', 'AMIGO!');
            try {
                Mailer::send((string)$user['email'], $site . ': Dein Konto ist freigeschaltet', implode("\n", [
                    'Hallo ' . $user['username'] . ',',
                    '',
                    'dein Konto wurde freigeschaltet. Du kannst dich jetzt einloggen:',
                    '',
                    self::baseUrl() . 'index.php?r=login',
                    '',
                    'Viel Spaß beim Sammeln!',
                    '– ' . $site,
                ]));
            } catch (\Throwable) {
                // Freischaltung gilt trotzdem.
            }
        }
    }

    /** Informiert alle Admins mit E-Mail-Adresse über ein Konto, das auf Freischaltung wartet. */
    public static function notifyAdmins(array $user): void
    {
        if (!Mailer::configured()) {
            return;
        }
        $site = (string)App::config('site_name', 'AMIGO!');
        $admins = App::db()->all("SELECT email FROM {users} WHERE role = 'admin' AND is_active = 1 AND email IS NOT NULL");
        foreach ($admins as $a) {
            try {
                Mailer::send((string)$a['email'], $site . ': Neues Konto wartet auf Freischaltung', implode("\n", [
                    'Neue Registrierung: ' . $user['username'] . ($user['email'] ? ' <' . $user['email'] . '>' : ''),
                    '',
                    'Freischalten oder ablehnen unter:',
                    self::baseUrl() . 'index.php?r=admin/users',
                ]));
            } catch (\Throwable) {
                // Admin sieht wartende Konten auch in der Benutzerverwaltung.
            }
        }
    }

    public static function pendingCount(): int
    {
        return (int)App::db()->value("SELECT COUNT(*) FROM {users} WHERE pending = 'admin'");
    }

    /** Entfernt nie bestätigte Konten nach einigen Tagen (gibt Benutzernamen wieder frei). */
    public static function purgeStale(): void
    {
        $db = App::db();
        $limit = gmdate('Y-m-d H:i:s', time() - self::STALE_DAYS * 86400);
        foreach ($db->all("SELECT id FROM {users} WHERE pending = 'email' AND created_at < ?", [$limit]) as $u) {
            $db->delete('users', ['id' => $u['id']]);
        }
    }

    // ------------------------------------------------------------------ Konto löschen

    public const DELETE_TTL = 3600; // Löschlink gilt 1 Stunde

    /** Wäre $user der letzte aktive Admin? (dann nicht löschbar) */
    public static function isLastAdmin(array $user): bool
    {
        return $user['role'] === 'admin'
            && (int)App::db()->value("SELECT COUNT(*) FROM {users} WHERE role = 'admin' AND is_active = 1 AND id <> ?", [$user['id']]) === 0;
    }

    /** Erzeugt den Löschlink und schickt ihn an die bestätigte E-Mail-Adresse. */
    public static function sendDeleteLink(array $user): void
    {
        $token = bin2hex(random_bytes(32));
        App::db()->update('users', [
            'delete_token'   => Crypto::hmac('delete', $token),
            'delete_expires' => time() + self::DELETE_TTL,
        ], ['id' => $user['id']]);
        $link = self::baseUrl() . 'index.php?' . str_replace('%2F', '/', http_build_query(['r' => 'account/delete/confirm', 'u' => (int)$user['id'], 't' => $token]));
        $site = (string)App::config('site_name', 'AMIGO!');
        Mailer::send((string)$user['email'], $site . ': Konto löschen – Bestätigung', implode("\n", [
            'Hallo ' . $user['username'] . ',',
            '',
            'du hast die Löschung deines Kontos angefordert. Um sie zu bestätigen, öffne diesen Link',
            'und gib dort dein aktuelles Passwort' . ((int)$user['totp_enabled'] ? ' und deinen 2FA-Code' : '') . ' ein:',
            '',
            $link,
            '',
            'Der Link ist 1 Stunde gültig. Gelöscht werden dein Konto, deine Sammlung, deine',
            'Durchgespielt-Liste und alle von dir hochgeladenen Bilder. Das kann nicht rückgängig gemacht werden.',
            '',
            'Wenn du das nicht warst, ignoriere diese Mail und ändere am besten dein Passwort.',
            '',
            '– ' . $site,
        ]));
    }

    /** Prüft einen Löschlink. Gibt den Nutzer zurück oder null. */
    public static function checkDeleteToken(int $userId, string $token): ?array
    {
        $user = App::db()->one('SELECT * FROM {users} WHERE id = ? AND is_active = 1', [$userId]);
        if (!$user || !$user['delete_token'] || (int)$user['delete_expires'] < time()
            || !preg_match('/^[a-f0-9]{64}$/', $token)
            || !hash_equals((string)$user['delete_token'], Crypto::hmac('delete', $token))) {
            return null;
        }
        return $user;
    }

    /**
     * Löscht ein Konto mit allen persönlichen Daten: Sammlung, Fotos, Durchgespielt-Liste,
     * Avatar, Freundschaften, Likes, Blockierungen (per Fremdschlüssel), eigene Standardbilder
     * im Zubehör-Katalog. Die Katalog-Einträge selbst bleiben erhalten; Nachrichten in
     * Unterhaltungen anderer erscheinen als „gelöschter Nutzer“.
     */
    public static function deleteUser(array $user): void
    {
        $db = App::db();
        $uid = (int)$user['id'];
        Avatar::removeFile($user);
        foreach ($db->all('SELECT id FROM {accessories} WHERE image_user_id = ?', [$uid]) as $a) {
            AccessoryCatalog::removeImage((int)$a['id']);
        }
        $dir = App::dataDir() . '/uploads/' . $uid;
        foreach (glob($dir . '/*.jpg') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($dir);
        $db->delete('users', ['id' => $uid]);
        Messages::cleanup(); // Unterhaltungen ohne verbliebene Teilnehmer
    }
}
