<?php
/**
 * AMIGO! The Retro Collector – Bootstrap
 *
 * Wird von index.php und install.php eingebunden. Lädt Autoloader und Hilfsfunktionen.
 */
declare(strict_types=1);

if (PHP_VERSION_ID < 80100) {
    http_response_code(500);
    exit('AMIGO! benötigt mindestens PHP 8.1.');
}

define('AMIGO', true);
define('AMIGO_ROOT', dirname(__DIR__));
define('AMIGO_VERSION', '2.0.0');

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'Amigo\\')) {
        return;
    }
    $file = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, 6)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require __DIR__ . '/helpers.php';
