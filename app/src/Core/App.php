<?php
declare(strict_types=1);

namespace Amigo\Core;

/** Zentrale Laufzeit: Konfiguration, Datenbank, Session, Sicherheits-Header, Fehlerbehandlung. */
final class App
{
    private static array $config = [];
    private static ?Database $db = null;

    public static function configPath(): string
    {
        return AMIGO_ROOT . '/config/config.php';
    }

    public static function isInstalled(): bool
    {
        return is_file(self::configPath());
    }

    public static function boot(): void
    {
        self::$config = require self::configPath();
        date_default_timezone_set((string)self::config('timezone', 'Europe/Berlin'));

        $debug = (bool)self::config('debug', false);
        error_reporting(E_ALL);
        ini_set('display_errors', $debug ? '1' : '0');
        ini_set('log_errors', '1');
        ini_set('error_log', self::dataDir() . '/logs/php-error.log');
        set_exception_handler([self::class, 'handleException']);

        if (self::config('force_https', false) && !self::isHttps()) {
            header('Location: https://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '/'), true, 301);
            exit;
        }

        self::sendSecurityHeaders();
        self::startSession();
        Schema::migrate(self::db());
    }

    public static function config(string $key, mixed $default = null): mixed
    {
        $value = self::$config;
        foreach (explode('.', $key) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }

    /** Nur für den Installer: Konfiguration ohne Datei setzen. */
    public static function setConfig(array $config): void
    {
        self::$config = $config;
        self::$db = null;
    }

    public static function db(): Database
    {
        return self::$db ??= Database::connect(self::config('db', []));
    }

    public static function dataDir(): string
    {
        return rtrim((string)self::config('data_dir', AMIGO_ROOT . '/data'), '/');
    }

    public static function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
            return true;
        }
        if ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443) {
            return true;
        }
        return self::config('trust_proxy', false)
            && strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }

    /** @param array<string, string[]> $extra zusätzliche Quellen je Direktive (z. B. für reCAPTCHA) */
    public static function sendSecurityHeaders(array $extra = []): void
    {
        $policy = [
            'default-src'     => ["'self'"],
            'img-src'         => ["'self'", 'data:', 'blob:'],
            'script-src'      => ["'self'"],
            'style-src'       => ["'self'"],
            'font-src'        => ["'self'"],
            'connect-src'     => ["'self'"],
            'frame-src'       => ["'none'"],
            'form-action'     => ["'self'"],
            'frame-ancestors' => ["'none'"],
            'base-uri'        => ["'none'"],
            'object-src'      => ["'none'"],
        ];
        foreach ($extra as $directive => $sources) {
            $current = array_diff($policy[$directive] ?? [], ["'none'"]);
            $policy[$directive] = array_values(array_unique([...$current, ...$sources]));
        }
        $csp = implode('; ', array_map(fn ($d, $s) => $d . ' ' . implode(' ', $s), array_keys($policy), $policy));
        header('Content-Security-Policy: ' . $csp);
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: same-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
        header('Cross-Origin-Opener-Policy: same-origin');
        if (self::isHttps()) {
            header('Strict-Transport-Security: max-age=31536000');
        }
        header_remove('X-Powered-By');
    }

    private static function startSession(): void
    {
        $sessionDir = self::dataDir() . '/sessions';
        if (is_dir($sessionDir) && is_writable($sessionDir)) {
            // Eigenes Verzeichnis statt des (auf Shared-Hosting oft geteilten) System-Temp-Ordners.
            session_save_path($sessionDir);
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.gc_maxlifetime', (string)(int)self::config('session_lifetime', 7200));

        $path = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/') . '/';
        session_name(self::isHttps() ? '__Host-amigo' : 'amigo');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => self::isHttps() ? '/' : $path,
            'secure'   => self::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    public static function handleException(\Throwable $e): void
    {
        $id = bin2hex(random_bytes(4));
        $logDir = self::dataDir() . '/logs';
        if (is_dir($logDir) && is_writable($logDir)) {
            @file_put_contents(
                $logDir . '/error.log',
                sprintf("[%s] #%s %s: %s in %s:%d\n%s\n\n", date('c'), $id, $e::class, $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString()),
                FILE_APPEND | LOCK_EX
            );
        }
        if (!headers_sent()) {
            http_response_code(500);
        }
        if (self::config('debug', false)) {
            echo '<pre>' . htmlspecialchars((string)$e, ENT_QUOTES, 'UTF-8') . '</pre>';
            return;
        }
        try {
            View::render('errors/error', [
                'title'   => 'GAME OVER',
                'code'    => 500,
                'message' => 'Da ist etwas schiefgelaufen. Fehler-ID: ' . $id,
            ]);
        } catch (\Throwable) {
            echo 'GAME OVER – interner Fehler (#' . $id . ').';
        }
    }
}
