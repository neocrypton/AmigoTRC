<?php
declare(strict_types=1);

namespace Amigo\Core;

/** CSRF-Schutz: Token pro Session + Prüfung des Origin-Headers. */
final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function rotate(): void
    {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }

    public static function check(): bool
    {
        $sent = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!is_string($sent) || empty($_SESSION['_csrf']) || !hash_equals($_SESSION['_csrf'], $sent)) {
            return false;
        }
        $origin = $_SERVER['HTTP_ORIGIN'] ?? null;
        if ($origin !== null && $origin !== 'null') {
            $host = parse_url($origin, PHP_URL_HOST);
            $port = parse_url($origin, PHP_URL_PORT);
            $originHost = $host . ($port ? ':' . $port : '');
            if (strcasecmp($originHost, (string)($_SERVER['HTTP_HOST'] ?? '')) !== 0) {
                return false;
            }
        }
        return true;
    }
}
