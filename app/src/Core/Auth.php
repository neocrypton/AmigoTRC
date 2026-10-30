<?php
declare(strict_types=1);

namespace Amigo\Core;

/** Anmeldung, Sitzungen, Passwort-Hashing, Recovery-Codes. */
final class Auth
{
    private static ?array $user = null;
    private static bool $loaded = false;

    public static function user(): ?array
    {
        if (self::$loaded) {
            return self::$user;
        }
        self::$loaded = true;

        $uid = $_SESSION['uid'] ?? null;
        if (!is_int($uid)) {
            return null;
        }
        $idle = (int)App::config('session_lifetime', 7200);
        if (time() - (int)($_SESSION['seen'] ?? 0) > $idle) {
            self::logout();
            return null;
        }
        $user = App::db()->one('SELECT * FROM {users} WHERE id = ? AND is_active = 1', [$uid]);
        // Passwortwechsel an anderer Stelle beendet diese Sitzung.
        if (!$user || !hash_equals((string)($_SESSION['pwv'] ?? ''), self::passwordVersion($user))) {
            self::logout();
            return null;
        }
        $_SESSION['seen'] = time();
        return self::$user = $user;
    }

    public static function id(): ?int
    {
        return self::user()['id'] ?? null;
    }

    public static function isAdmin(): bool
    {
        return (self::user()['role'] ?? '') === 'admin';
    }

    /**
     * Grund des letzten fehlgeschlagenen attempt(): invalid | pending_email | pending_admin | blocked.
     * Die Detail-Gründe gibt es nur bei korrektem Passwort (keine Kontoermittlung durch Fremde).
     */
    public static string $failure = 'invalid';
    public static ?int $failedUserId = null;

    /** Prüft Benutzername + Passwort. Gibt den Benutzer zurück oder null. */
    public static function attempt(string $username, string $password): ?array
    {
        self::$failure = 'invalid';
        self::$failedUserId = null;
        $user = App::db()->one('SELECT * FROM {users} WHERE LOWER(username) = LOWER(?)', [$username]);
        if (!$user) {
            // Gleiche Rechenzeit wie bei existierenden Konten (kein User-Enumeration-Timing).
            password_verify($password, self::dummyHash());
            return null;
        }
        if (!password_verify($password, $user['password_hash'])) {
            return null;
        }
        if ((int)$user['is_active'] !== 1) {
            self::$failure = match ($user['pending'] ?? null) {
                'email' => 'pending_email',
                'admin' => 'pending_admin',
                default => 'blocked',
            };
            self::$failedUserId = (int)$user['id'];
            return null;
        }
        if (password_needs_rehash($user['password_hash'], self::algo(), self::options())) {
            App::db()->update('users', ['password_hash' => self::hash($password)], ['id' => $user['id']]);
            $user = App::db()->one('SELECT * FROM {users} WHERE id = ?', [$user['id']]);
        }
        return $user;
    }

    /** Hash mit dem aktuellen Algorithmus, einmalig erzeugt und in den Settings abgelegt. */
    private static function dummyHash(): string
    {
        $db = App::db();
        $hash = Settings::get($db, 'dummy_hash');
        if ($hash === null || password_needs_rehash($hash, self::algo(), self::options())) {
            $hash = self::hash(bin2hex(random_bytes(16)));
            Settings::set($db, 'dummy_hash', $hash);
        }
        return $hash;
    }

    public static function login(array $user): void
    {
        session_regenerate_id(true);
        unset($_SESSION['2fa_pending']);
        $_SESSION['uid'] = (int)$user['id'];
        $_SESSION['seen'] = time();
        $_SESSION['pwv'] = self::passwordVersion($user);
        Csrf::rotate();
        App::db()->update('users', ['last_login_at' => Database::now()], ['id' => $user['id']]);
        self::$loaded = false;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        self::$user = null;
        self::$loaded = true;
    }

    /** Nach Passwortwechsel die eigene Sitzung gültig halten. */
    public static function refreshPasswordVersion(): void
    {
        $user = App::db()->one('SELECT * FROM {users} WHERE id = ?', [self::id()]);
        $_SESSION['pwv'] = self::passwordVersion($user);
        session_regenerate_id(true);
    }

    private static function passwordVersion(array $user): string
    {
        return substr(Crypto::hmac('pwv', $user['password_hash']), 0, 16);
    }

    public static function algo(): string|int|null
    {
        return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
    }

    public static function hash(string $password): string
    {
        return password_hash($password, self::algo(), self::options());
    }

    private static function options(): array
    {
        return self::algo() === PASSWORD_BCRYPT ? ['cost' => 12] : [];
    }

    /** Liefert eine Fehlermeldung oder null, wenn das Passwort den Regeln entspricht. */
    public static function passwordProblem(string $password, string $username = ''): ?string
    {
        $len = mb_strlen($password);
        if ($len < 10) {
            return 'Das Passwort muss mindestens 10 Zeichen lang sein.';
        }
        if ($len > 200) {
            return 'Das Passwort ist zu lang (max. 200 Zeichen).';
        }
        if ($username !== '' && stripos($password, $username) !== false) {
            return 'Das Passwort darf den Benutzernamen nicht enthalten.';
        }
        if (count(array_unique(mb_str_split($password))) < 5) {
            return 'Das Passwort ist zu einfach.';
        }
        return null;
    }

    /** @return array{0: string[], 1: string} [Klartext-Codes, JSON der Hashes] */
    public static function generateRecoveryCodes(int $count = 10): array
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $codes = [];
        $hashes = [];
        for ($i = 0; $i < $count; $i++) {
            $raw = '';
            for ($j = 0; $j < 10; $j++) {
                $raw .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $codes[] = substr($raw, 0, 5) . '-' . substr($raw, 5);
            $hashes[] = Crypto::hmac('recovery', $raw);
        }
        return [$codes, json_encode($hashes)];
    }

    /** Verbraucht einen Recovery-Code, wenn er passt. */
    public static function useRecoveryCode(array $user, string $code): bool
    {
        $raw = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));
        if (strlen($raw) !== 10) {
            return false;
        }
        $hash = Crypto::hmac('recovery', $raw);
        $hashes = json_decode((string)$user['recovery_codes'], true) ?: [];
        foreach ($hashes as $i => $stored) {
            if (hash_equals($stored, $hash)) {
                unset($hashes[$i]);
                App::db()->update('users', ['recovery_codes' => json_encode(array_values($hashes))], ['id' => $user['id']]);
                return true;
            }
        }
        return false;
    }
}
