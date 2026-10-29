<?php
/**
 * AMIGO! The Retro Collector – Installer
 *
 * Läuft nur, solange config/config.php nicht existiert. Zum Schutz vor fremden
 * Besuchern muss der Inhalt von config/INSTALL_TOKEN.txt eingegeben werden
 * (die Datei kann nur lesen, wer Zugriff auf den Webspace hat).
 */
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

use Amigo\Core\App;
use Amigo\Core\Auth;
use Amigo\Core\Crypto;
use Amigo\Core\Csrf;
use Amigo\Core\Database;
use Amigo\Core\Schema;
use Amigo\Core\Settings;
use Amigo\Core\View;

App::sendSecurityHeaders();
header('Cache-Control: no-store');
session_name('amigo_install');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict', 'secure' => App::isHttps()]);
session_start();

if (App::isInstalled()) {
    http_response_code(403);
    View::render('errors/error', [
        'title' => 'Installiert', 'code' => 'OK',
        'message' => 'AMIGO! ist bereits installiert. Für eine Neuinstallation config/config.php entfernen.',
    ], 'layout_plain');
    exit;
}

$tokenFile = AMIGO_ROOT . '/config/INSTALL_TOKEN.txt';
if (!is_file($tokenFile) && is_writable(dirname($tokenFile))) {
    file_put_contents($tokenFile, bin2hex(random_bytes(12)) . "\n", LOCK_EX);
    @chmod($tokenFile, 0640);
}

$drivers = PDO::getAvailableDrivers();
$requirements = [
    ['PHP ≥ 8.1', PHP_VERSION, PHP_VERSION_ID >= 80100],
    ['PDO SQLite', in_array('sqlite', $drivers, true) ? 'ja' : 'nein', in_array('sqlite', $drivers, true) || in_array('mysql', $drivers, true)],
    ['PDO MySQL', in_array('mysql', $drivers, true) ? 'ja' : 'nein', in_array('sqlite', $drivers, true) || in_array('mysql', $drivers, true)],
    ['GD (Bildverarbeitung)', extension_loaded('gd') ? 'ja' : 'nein', extension_loaded('gd')],
    ['fileinfo', extension_loaded('fileinfo') ? 'ja' : 'nein', extension_loaded('fileinfo')],
    ['mbstring', extension_loaded('mbstring') ? 'ja' : 'nein', extension_loaded('mbstring')],
    ['libsodium / OpenSSL', function_exists('sodium_crypto_secretbox') ? 'libsodium' : (extension_loaded('openssl') ? 'OpenSSL' : 'fehlt'), function_exists('sodium_crypto_secretbox') || extension_loaded('openssl')],
    ['HTTP-Client (curl)', function_exists('curl_init') ? 'curl' : (ini_get('allow_url_fopen') ? 'allow_url_fopen' : 'fehlt'), true],
    ['config/ beschreibbar', is_writable(AMIGO_ROOT . '/config') ? 'ja' : 'nein', is_writable(AMIGO_ROOT . '/config')],
    ['HTTPS', App::isHttps() ? 'ja' : 'nein – dringend empfohlen', true],
];
$requirementsOk = !in_array(false, array_column($requirements, 2), true);

$defaults = [
    'site_name'  => 'AMIGO!',
    'driver'     => in_array('sqlite', $drivers, true) ? 'sqlite' : 'mysql',
    'data_dir'   => is_writable(dirname(AMIGO_ROOT)) ? dirname(AMIGO_ROOT) . '/amigo-data' : AMIGO_ROOT . '/data',
    'db_host'    => 'localhost',
    'db_port'    => '3306',
    'db_name'    => '',
    'db_user'    => '',
    'db_prefix'  => 'amigo_',
    'username'   => 'admin',
    'email'      => '',
    'tgdb_key'   => '',
    'timezone'   => 'Europe/Berlin',
];
$form = $defaults;
$errors = [];
$done = false;

if (is_post()) {
    foreach (array_keys($defaults) as $k) {
        $form[$k] = input($k, 255);
    }
    $token = input('token', 64);
    $realToken = is_file($tokenFile) ? trim((string)file_get_contents($tokenFile)) : '';

    if (!Csrf::check()) {
        $errors[] = 'Formular abgelaufen – bitte erneut absenden.';
    } elseif ($realToken === '' || !hash_equals($realToken, $token)) {
        $errors[] = 'Installations-Token falsch. Den Inhalt findest du in config/INSTALL_TOKEN.txt auf deinem Webspace.';
    }
    if (!$requirementsOk) {
        $errors[] = 'Nicht alle Voraussetzungen sind erfüllt.';
    }
    if (!in_array($form['driver'], ['sqlite', 'mysql'], true) || !in_array($form['driver'], $drivers, true)) {
        $errors[] = 'Bitte einen verfügbaren Datenbanktyp wählen.';
    }
    if (!in_array($form['timezone'], timezone_identifiers_list(), true)) {
        $errors[] = 'Unbekannte Zeitzone.';
    }
    if (!preg_match('/^[a-zA-Z0-9_]{0,20}$/', $form['db_prefix'])) {
        $errors[] = 'Tabellen-Präfix: nur Buchstaben, Ziffern und _ (max. 20).';
    }
    $errors = array_merge($errors, validateAdmin($form['username'], $form['email'], (string)($_POST['password'] ?? ''), (string)($_POST['password2'] ?? '')));

    $dataDir = rtrim($form['data_dir'], '/');
    if (!$errors) {
        if ($dataDir === '' || !str_starts_with($dataDir, '/') && !preg_match('#^[A-Za-z]:[\\\\/]#', $dataDir)) {
            $errors[] = 'Bitte einen absoluten Pfad für das Datenverzeichnis angeben.';
        } elseif (!is_dir($dataDir) && !@mkdir($dataDir, 0750, true)) {
            $errors[] = 'Datenverzeichnis kann nicht angelegt werden: ' . $dataDir;
        } elseif (!is_writable($dataDir)) {
            $errors[] = 'Datenverzeichnis ist nicht beschreibbar: ' . $dataDir;
        }
    }

    if (!$errors) {
        $dataDir = realpath($dataDir) ?: $dataDir;
        foreach (['uploads', 'sessions', 'logs'] as $sub) {
            if (!is_dir("$dataDir/$sub")) {
                @mkdir("$dataDir/$sub", 0750, true);
            }
        }
        $deny = "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order deny,allow\n    Deny from all\n</IfModule>\n";
        if (!is_file("$dataDir/.htaccess")) {
            file_put_contents("$dataDir/.htaccess", $deny);
        }
        file_put_contents("$dataDir/probe.txt", "AMIGO-PROBE – wenn du das im Browser siehst, ist das Datenverzeichnis ungeschützt!\n");

        $db = $form['driver'] === 'sqlite'
            ? ['driver' => 'sqlite', 'path' => $dataDir . '/amigo.sqlite']
            : ['driver' => 'mysql', 'host' => $form['db_host'], 'port' => (int)$form['db_port'] ?: 3306,
               'name' => $form['db_name'], 'user' => $form['db_user'], 'pass' => (string)($_POST['db_pass'] ?? ''),
               'prefix' => $form['db_prefix']];

        $config = [
            'site_name'        => $form['site_name'] ?: 'AMIGO!',
            'timezone'         => $form['timezone'],
            'debug'            => false,
            'force_https'      => App::isHttps(),
            'trust_proxy'      => false,
            'session_lifetime' => 7200,
            'upload_max_mb'    => 10,
            'data_dir'         => $dataDir,
            'app_key'          => Crypto::generateKey(),
            'tgdb_api_key'     => $form['tgdb_key'],
            'db'               => $db,
        ];

        try {
            App::setConfig($config);
            $pdo = App::db();
            if (Schema::currentVersion($pdo) > 0) {
                throw new RuntimeException('In dieser Datenbank existiert bereits eine AMIGO!-Installation mit diesem Präfix.');
            }
            Schema::migrate($pdo);
            $pdo->insert('users', [
                'username'      => $form['username'],
                'email'         => $form['email'] ?: null,
                'password_hash' => Auth::hash((string)$_POST['password']),
                'role'          => 'admin',
                'created_at'    => Database::now(),
            ]);
            Settings::set($pdo, 'registration_open', '0');
            Settings::set($pdo, 'require_2fa', '0');

            $php = "<?php\n// AMIGO! Konfiguration – erzeugt am " . date('Y-m-d H:i') . " durch install.php.\n"
                . "// Diese Datei enthält Zugangsdaten und den Verschlüsselungsschlüssel: nicht weitergeben!\n"
                . 'return ' . var_export($config, true) . ";\n";
            if (file_put_contents(App::configPath(), $php, LOCK_EX) === false) {
                throw new RuntimeException('config/config.php konnte nicht geschrieben werden.');
            }
            @chmod(App::configPath(), 0640);
            @unlink($tokenFile);
            $done = true;
        } catch (PDOException $e) {
            // Keine Zugangsdaten in der Fehlermeldung ausgeben.
            $errors[] = 'Datenbankverbindung/-einrichtung fehlgeschlagen (Code ' . e((string)$e->getCode()) . '). Bitte Zugangsdaten prüfen.';
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}

View::render('install', [
    'title'          => 'Installation',
    'form'           => $form,
    'errors'         => $errors,
    'done'           => $done,
    'drivers'        => $drivers,
    'requirements'   => $requirements,
    'requirementsOk' => $requirementsOk,
    'tokenExists'    => is_file($tokenFile),
    'probe'          => $done ? probe_path() : null,
], 'layout_plain');

function validateAdmin(string $username, string $email, string $password, string $password2): array
{
    $errors = [];
    if (!preg_match('/^[A-Za-z0-9_.-]{3,40}$/', $username)) {
        $errors[] = 'Admin-Benutzername: 3–40 Zeichen, nur Buchstaben, Ziffern, Punkt, Binde- und Unterstrich.';
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Die E-Mail-Adresse ist ungültig.';
    }
    if ($problem = Auth::passwordProblem($password, $username)) {
        $errors[] = $problem;
    }
    if (!hash_equals($password, $password2)) {
        $errors[] = 'Die Passwörter stimmen nicht überein.';
    }
    return $errors;
}
