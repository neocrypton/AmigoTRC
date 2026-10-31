<?php
/**
 * AMIGO! The Retro Collector – Front-Controller
 *
 * Alle Seiten laufen über index.php?r=<route>. Nur Routen aus der Tabelle unten
 * sind erreichbar; POST-Anfragen werden zentral per CSRF-Token geprüft.
 */
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

use Amigo\Controller as C;
use Amigo\Core\App;
use Amigo\Core\Auth;
use Amigo\Core\Csrf;
use Amigo\Core\Settings;
use Amigo\Core\View;

if (!App::isInstalled()) {
    header('Location: install.php');
    exit;
}

App::boot();

// route => [Controller, Methode, Zugriff: guest|auth|admin|public]
$routes = [
    'home'              => [C\DashboardController::class, 'index', 'auth'],
    'login'             => [C\AuthController::class, 'login', 'guest'],
    'login/2fa'         => [C\AuthController::class, 'twoFactor', 'public'],
    'logout'            => [C\AuthController::class, 'logout', 'public'],
    'verify'            => [C\AuthController::class, 'verify', 'public'],
    'verify/resend'     => [C\AuthController::class, 'resend', 'public'],
    'register'          => [C\AuthController::class, 'register', 'guest'],

    'collection'        => [C\CollectionController::class, 'index', 'auth'],
    'item'              => [C\CollectionController::class, 'show', 'auth'],
    'item/edit'         => [C\CollectionController::class, 'edit', 'auth'],
    'item/delete'       => [C\CollectionController::class, 'delete', 'auth'],
    'photo'             => [C\CollectionController::class, 'photo', 'auth'],

    'plays'             => [C\PlayController::class, 'index', 'auth'],
    'play/edit'         => [C\PlayController::class, 'edit', 'auth'],
    'play/delete'       => [C\PlayController::class, 'delete', 'auth'],

    'platforms'         => [C\PlatformController::class, 'index', 'auth'],
    'platform'          => [C\PlatformController::class, 'show', 'auth'],

    'community'         => [C\CommunityController::class, 'index', 'auth'],
    'u'                 => [C\CommunityController::class, 'profile', 'auth'],
    'u/collection'      => [C\CommunityController::class, 'collection', 'auth'],
    'u/item'            => [C\CommunityController::class, 'item', 'auth'],
    'u/plays'           => [C\CommunityController::class, 'plays', 'auth'],

    'cover'             => [C\CoverController::class, 'show', 'auth'],
    'api/games'         => [C\ApiController::class, 'games', 'auth'],
    'messages'          => [C\MessageController::class, 'index', 'auth'],
    'message/new'       => [C\MessageController::class, 'compose', 'auth'],
    'message'           => [C\MessageController::class, 'show', 'auth'],
    'message/reply'     => [C\MessageController::class, 'reply', 'auth'],
    'message/action'    => [C\MessageController::class, 'action', 'auth'],
    'api/users'         => [C\MessageController::class, 'users', 'auth'],
    'scrape'            => [C\ScrapeController::class, 'index', 'auth'],
    'scrape/run'        => [C\ScrapeController::class, 'run', 'auth'],
    'scrape/reset'      => [C\ScrapeController::class, 'reset', 'auth'],
    'scrape/item'       => [C\ScrapeController::class, 'item', 'auth'],
    'import'            => [C\ImportController::class, 'upload', 'auth'],
    'import/map'        => [C\ImportController::class, 'map', 'auth'],
    'import/preview'    => [C\ImportController::class, 'preview', 'auth'],
    'import/cancel'     => [C\ImportController::class, 'cancel', 'auth'],
    'export'            => [C\AccountController::class, 'export', 'auth'],

    'account'           => [C\AccountController::class, 'index', 'auth'],
    'account/profile'   => [C\AccountController::class, 'profile', 'auth'],
    'account/avatar'    => [C\AccountController::class, 'avatar', 'auth'],
    'avatar'            => [C\AccountController::class, 'avatarImage', 'auth'],
    'account/verify'    => [C\AccountController::class, 'verifyMail', 'auth'],
    'account/notify'    => [C\AccountController::class, 'notify', 'auth'],
    'pm/unsubscribe'    => [C\AccountController::class, 'unsubscribe', 'public'],
    'account/privacy'   => [C\AccountController::class, 'privacy', 'auth'],
    'account/tgdb'      => [C\AccountController::class, 'tgdbKey', 'auth'],
    'account/password'  => [C\AccountController::class, 'password', 'auth'],
    'account/2fa'       => [C\AccountController::class, 'twoFactorSetup', 'auth'],
    'account/2fa/off'   => [C\AccountController::class, 'twoFactorDisable', 'auth'],
    'account/recovery'  => [C\AccountController::class, 'recoveryCodes', 'auth'],

    'admin/users'          => [C\AdminController::class, 'users', 'admin'],
    'admin/user/action'    => [C\AdminController::class, 'userAction', 'admin'],
    'admin/settings'       => [C\AdminController::class, 'settings', 'admin'],
    'admin/mail'           => [C\AdminController::class, 'mail', 'admin'],
    'admin/recaptcha'      => [C\AdminController::class, 'recaptcha', 'admin'],
    'admin/tgdb'           => [C\AdminController::class, 'tgdbKey', 'admin'],
    'admin/platforms'      => [C\AdminController::class, 'platformImport', 'admin'],
    'admin/platform/edit'  => [C\AdminController::class, 'platformEdit', 'admin'],
    'admin/platform/delete' => [C\AdminController::class, 'platformDelete', 'admin'],
];

$route = isset($_GET['r']) && is_string($_GET['r']) ? $_GET['r'] : 'home';

if (!isset($routes[$route])) {
    http_response_code(404);
    View::render('errors/error', ['title' => '404', 'code' => 404, 'message' => 'Dieses Level gibt es nicht.']);
    exit;
}

[$controller, $method, $access] = $routes[$route];

if (is_post() && !$_POST && !$_FILES && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    http_response_code(413);
    View::render('errors/error', ['title' => 'Zu groß', 'code' => 413, 'message' => 'Die Anfrage war zu groß für den Server (post_max_size = ' . e(ini_get('post_max_size')) . '). Bitte weniger oder kleinere Fotos auf einmal hochladen.']);
    exit;
}

if (is_post() && !Csrf::check()) {
    http_response_code(419);
    View::render('errors/error', ['title' => 'Sitzung abgelaufen', 'code' => 419, 'message' => 'Das Formular ist abgelaufen. Bitte Seite neu laden und erneut absenden.']);
    exit;
}

$user = Auth::user();
if ($access === 'guest' && $user) {
    redirect('home');
}
if (($access === 'auth' || $access === 'admin') && !$user) {
    if ($route !== 'home') {
        flash('info', 'Bitte zuerst einloggen.');
    }
    redirect('login');
}
if ($access === 'admin' && !Auth::isAdmin()) {
    http_response_code(403);
    View::render('errors/error', ['title' => '403', 'code' => 403, 'message' => 'Zutritt nur für Admins.']);
    exit;
}

// 2FA-Pflicht: Konten ohne 2FA dürfen nur noch die Einrichtung aufrufen.
if ($user && !(int)$user['totp_enabled'] && Settings::bool(App::db(), 'require_2fa')
    && !in_array($route, ['account/2fa', 'logout'], true)) {
    flash('warn', 'Der Admin verlangt Zwei-Faktor-Anmeldung. Bitte jetzt einrichten.');
    redirect('account/2fa');
}

// HTML komprimiert ausliefern – spart bei langsamen Verbindungen (EDGE/3G) viel Zeit.
if (!in_array($route, ['photo', 'cover', 'avatar', 'export'], true) && extension_loaded('zlib') && !ini_get('zlib.output_compression')) {
    ob_start('ob_gzhandler');
}

(new $controller())->$method();
