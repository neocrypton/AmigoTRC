<?php
declare(strict_types=1);

namespace Amigo\Controller;

use Amigo\Core\App;
use Amigo\Core\Auth;
use Amigo\Core\Database;
use Amigo\Core\RateLimiter;
use Amigo\Core\Settings;
use Amigo\Core\View;
use Amigo\Service\Accounts;
use Amigo\Service\Catalog;
use Amigo\Service\Mailer;
use Amigo\Service\Recaptcha;
use Amigo\Service\TheGamesDb;

final class AdminController
{
    public function users(): void
    {
        $errors = [];
        $data = ['username' => '', 'email' => '', 'role' => 'user'];
        if (is_post()) {
            $data = ['username' => input('username', 40), 'email' => input('email', 190), 'role' => input('role', 10) === 'admin' ? 'admin' : 'user'];
            $password = (string)($_POST['password'] ?? '');
            $errors = AuthController::validateNewUser($data['username'], $data['email'], $password, (string)($_POST['password2'] ?? ''));
            if (!$errors) {
                App::db()->insert('users', [
                    'username'      => $data['username'],
                    'email'         => $data['email'] ?: null,
                    'password_hash' => Auth::hash($password),
                    'role'          => $data['role'],
                    'created_at'    => Database::now(),
                ]);
                flash('ok', 'Benutzer „' . $data['username'] . '“ angelegt.');
                redirect('admin/users');
            }
        }
        $users = App::db()->all(
            'SELECT u.id, u.username, u.email, u.role, u.is_active, u.totp_enabled, u.created_at, u.last_login_at,
                    u.pending, u.email_verified_at, u.avatar,
                    (SELECT COUNT(*) FROM {items} i WHERE i.user_id = u.id) AS items
             FROM {users} u ORDER BY u.username'
        );
        $pending = array_values(array_filter($users, fn ($u) => $u['pending'] !== null));
        $users = array_values(array_filter($users, fn ($u) => $u['pending'] === null));
        View::render('admin/users', ['title' => 'Benutzer', 'nav' => 'admin', 'users' => $users, 'pending' => $pending, 'errors' => $errors, 'data' => $data]);
    }

    public function userAction(): void
    {
        if (!is_post()) {
            redirect('admin/users');
        }
        $db = App::db();
        $id = input_int('id', 1);
        $user = $id ? $db->one('SELECT * FROM {users} WHERE id = ?', [$id]) : null;
        if (!$user) {
            redirect('admin/users');
        }
        $self = (int)$user['id'] === Auth::id();
        $action = input('action', 20);

        if ($self && in_array($action, ['toggle_active', 'toggle_admin', 'delete'], true)) {
            flash('error', 'Das eigene Konto kann hier nicht gesperrt, herabgestuft oder gelöscht werden.');
            redirect('admin/users');
        }

        switch ($action) {
            case 'approve':
                if ($user['pending'] !== null) {
                    Accounts::approve($user);
                    flash('ok', '„' . $user['username'] . '“ freigeschaltet.' . (!empty($user['email']) && Mailer::configured() ? ' Eine Info-Mail wurde verschickt.' : ''));
                }
                break;
            case 'reject':
                if ($user['pending'] !== null) {
                    $db->delete('users', ['id' => $user['id']]);
                    flash('ok', 'Registrierung von „' . $user['username'] . '“ abgelehnt und entfernt.');
                }
                break;
            case 'toggle_active':
                $db->update('users', ['is_active' => (int)$user['is_active'] ? 0 : 1], ['id' => $user['id']]);
                flash('ok', (int)$user['is_active'] ? 'Konto gesperrt.' : 'Konto entsperrt.');
                break;
            case 'toggle_admin':
                $db->update('users', ['role' => $user['role'] === 'admin' ? 'user' : 'admin'], ['id' => $user['id']]);
                flash('ok', 'Rolle geändert.');
                break;
            case 'reset_2fa':
                $db->update('users', ['totp_secret' => null, 'totp_enabled' => 0, 'totp_last_step' => 0, 'recovery_codes' => null], ['id' => $user['id']]);
                flash('ok', '2FA für „' . $user['username'] . '“ zurückgesetzt.');
                break;
            case 'delete':
                if (input('confirm', 40) !== $user['username']) {
                    flash('error', 'Zum Löschen den Benutzernamen exakt eingeben.');
                    break;
                }
                \Amigo\Service\Avatar::removeFile($user);
                $dir = App::dataDir() . '/uploads/' . (int)$user['id'];
                foreach (glob($dir . '/*.jpg') ?: [] as $f) {
                    @unlink($f);
                }
                @rmdir($dir);
                $db->delete('users', ['id' => $user['id']]);
                \Amigo\Service\Messages::cleanup(); // Unterhaltungen ohne verbliebene Teilnehmer
                flash('ok', 'Benutzer und alle Daten gelöscht.');
                break;
        }
        redirect('admin/users');
    }

    public function settings(): void
    {
        $db = App::db();
        if (is_post()) {
            $open = isset($_POST['registration_open']);
            if ($open && !Recaptcha::configured()) {
                $open = false;
                flash('error', 'Die Registrierung kann erst geöffnet werden, wenn reCAPTCHA eingerichtet ist.');
            }
            $mode = input_enum('activation_mode', Accounts::MODES) ?? Accounts::mode();
            if (Accounts::needsEmail($mode) && !Mailer::configured()) {
                flash('error', 'E-Mail-Bestätigung braucht einen eingerichteten E-Mail-Versand – Aktivierungsmodus nicht geändert.');
                $mode = Accounts::mode();
            }
            Settings::set($db, 'registration_open', $open ? '1' : '0');
            Settings::set($db, 'activation_mode', $mode);
            Settings::set($db, 'require_email', isset($_POST['require_email']) ? '1' : '0');
            Settings::set($db, 'require_2fa', isset($_POST['require_2fa']) ? '1' : '0');
            if (isset($_POST['clear_cache'])) {
                $db->run('DELETE FROM {api_cache}');
            }
            if (isset($_POST['require_2fa']) && !(int)Auth::user()['totp_enabled']) {
                flash('warn', 'Du selbst hast noch keine 2FA – bitte jetzt einrichten.');
            }
            flash('ok', 'Einstellungen gespeichert.');
            redirect('admin/settings');
        }

        $checks = [
            'PHP-Version'           => [PHP_VERSION, PHP_VERSION_ID >= 80100],
            'Datenbank'             => [$db->driver === 'mysql' ? 'MySQL/MariaDB' : 'SQLite ' . $db->pdo()->getAttribute(\PDO::ATTR_SERVER_VERSION), true],
            'HTTPS'                 => [App::isHttps() ? 'aktiv' : 'NICHT aktiv', App::isHttps()],
            'Upload-Limit'          => [ini_get('upload_max_filesize') . ' / POST ' . ini_get('post_max_size'), true],
            'WebP-Unterstützung'    => [function_exists('imagecreatefromwebp') ? 'ja' : 'nein', function_exists('imagecreatefromwebp')],
            'Verschlüsselung'       => [function_exists('sodium_crypto_secretbox') ? 'libsodium' : 'OpenSSL', true],
            'TheGamesDB (System)'   => [Catalog::systemKey() !== '' ? 'API-Key gesetzt' : 'kein API-Key', true],
            'TGDB-Kontingent'       => [Settings::get($db, 'tgdb_allowance') ?? 'unbekannt', true],
            'Datenverzeichnis'      => [App::dataDir(), is_writable(App::dataDir())],
        ];
        $counts = [
            'users'     => (int)$db->value('SELECT COUNT(*) FROM {users}'),
            'items'     => (int)$db->value('SELECT COUNT(*) FROM {items}'),
            'photos'    => (int)$db->value('SELECT COUNT(*) FROM {photos}'),
            'bytes'     => (int)$db->value('SELECT COALESCE(SUM(bytes), 0) FROM {photos}'),
            'plays'     => (int)$db->value('SELECT COUNT(*) FROM {plays}'),
            'games'     => (int)$db->value('SELECT COUNT(*) FROM {games}'),
            'platforms' => (int)$db->value('SELECT COUNT(*) FROM {platforms}'),
            'cache'     => (int)$db->value('SELECT COUNT(*) FROM {api_cache}'),
        ];

        View::render('admin/settings', [
            'title'        => 'System',
            'nav'          => 'admin',
            'registration' => Settings::bool($db, 'registration_open'),
            'require2fa'   => Settings::bool($db, 'require_2fa'),
            'checks'       => $checks,
            'counts'       => $counts,
            'probe'        => probe_path(),
            'keySource'    => Catalog::systemKeySource(),
            'keyHint'      => Catalog::systemKey() !== '' ? '…' . substr(Catalog::systemKey(), -4) : null,
            'tgdbShared'   => Settings::bool($db, 'tgdb_shared'),
            'captchaSite'  => Recaptcha::siteKey(),
            'mode'         => Accounts::mode(),
            'requireEmail' => Settings::bool($db, 'require_email'),
            'mail'         => Mailer::settings(),
            'mailOk'       => Mailer::configured(),
            'baseUrl'      => Accounts::baseUrl(),
            'pendingCount' => Accounts::pendingCount(),
            'captchaOk'    => Recaptcha::configured(),
            'userKeys'     => (int)$db->value('SELECT COUNT(*) FROM {users} WHERE tgdb_api_key IS NOT NULL'),
        ]);
    }

    /** E-Mail-Versand speichern oder Testmail schicken. */
    public function mail(): void
    {
        if (!is_post()) {
            redirect('admin/settings');
        }
        $db = App::db();
        if (input('action', 10) === 'save') {
            $errors = Mailer::save([
                'transport'  => input('transport', 10),
                'from'       => input('from', 190),
                'from_name'  => input('from_name', 80),
                'host'       => input('host', 253),
                'port'       => input('port', 5),
                'secure'     => input('secure', 5),
                'user'       => input('user', 190),
                'pass'       => (string)($_POST['pass'] ?? ''),
                'clear_pass' => isset($_POST['clear_pass']),
            ]);
            $base = input('base_url', 255);
            if (!preg_match('#^https?://[A-Za-z0-9.-]+(:\d+)?(/[A-Za-z0-9._~%/-]*)?$#', $base)) {
                $errors[] = 'Die Adresse der Seite ist ungültig (z. B. https://amigo.example.de/).';
            } else {
                Settings::set($db, 'base_url', rtrim($base, '/') . '/');
            }
            flash($errors ? 'error' : 'ok', $errors ? implode(' ', $errors) : 'E-Mail-Einstellungen gespeichert.');
            redirect('admin/settings');
        }
        // Testmail
        $to = input('test_to', 190) ?: (string)(Auth::user()['email'] ?? '');
        if (RateLimiter::tooMany('mailtest:' . Auth::id(), 10, 3600)) {
            flash('error', 'Zu viele Testmails – bitte später erneut.');
        } elseif (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Bitte eine Empfängeradresse für die Testmail angeben.');
        } else {
            RateLimiter::hit('mailtest:' . Auth::id());
            try {
                Mailer::send($to, (string)App::config('site_name', 'AMIGO!') . ': Testmail', "Der E-Mail-Versand funktioniert. GAME ON!
");
                flash('ok', 'Testmail an ' . $to . ' verschickt – bitte Posteingang (und Spam-Ordner) prüfen.');
            } catch (\Throwable $e) {
                flash('error', 'Versand fehlgeschlagen: ' . $e->getMessage());
            }
        }
        redirect('admin/settings');
    }

    /** reCAPTCHA-Schlüssel speichern, prüfen oder entfernen. */
    public function recaptcha(): void
    {
        if (!is_post()) {
            redirect('admin/settings');
        }
        $db = App::db();
        $action = input('action', 10);
        if ($action === 'remove') {
            Recaptcha::clear();
            if (Settings::bool($db, 'registration_open')) {
                Settings::set($db, 'registration_open', '0');
                flash('warn', 'reCAPTCHA entfernt – die öffentliche Registrierung wurde deshalb geschlossen.');
            } else {
                flash('ok', 'reCAPTCHA entfernt.');
            }
            redirect('admin/settings');
        }
        if ($action === 'save') {
            $site = trim(input('site_key', 120));
            $secret = trim(input('secret_key', 120));
            if ($secret === '' && Recaptcha::secret() !== '') {
                $secret = Recaptcha::secret(); // nur Website-Schlüssel geändert
            }
            if (!Recaptcha::validKey($site) || !Recaptcha::validKey($secret)) {
                flash('error', 'Website-Schlüssel und Geheimschlüssel sehen ungültig aus.');
                redirect('admin/settings');
            }
            Recaptcha::save($site, $secret);
        }
        $result = Recaptcha::checkSecret();
        $prefix = $action === 'save' ? 'reCAPTCHA gespeichert. ' : '';
        flash($result['ok'] ? 'ok' : ($action === 'save' ? 'warn' : 'error'), $prefix . $result['message']);
        redirect('admin/settings');
    }

    /** TheGamesDB-API-Key speichern, entfernen oder testen. */
    public function tgdbKey(): void
    {
        if (!is_post()) {
            redirect('admin/settings');
        }
        $action = input('action', 10);
        if ($action === 'remove') {
            Catalog::saveSystemKey(null);
            flash('ok', Catalog::systemKeySource() === 'config'
                ? 'Key aus dem Adminbereich entfernt – es gilt jetzt wieder der Key aus config/config.php.'
                : 'API-Key entfernt. Die TheGamesDB-Funktionen sind deaktiviert.');
            redirect('admin/settings');
        }
        if ($action === 'save') {
            $key = preg_replace('/\s+/', '', input('tgdb_api_key', 200));
            if (!Catalog::validApiKey($key)) {
                flash('error', 'Der API-Key sieht ungültig aus (nur Buchstaben und Ziffern, 16–128 Zeichen).');
                redirect('admin/settings');
            }
            Catalog::saveSystemKey($key);
        }
        if ($action === 'shared') {
            Settings::set(App::db(), 'tgdb_shared', isset($_POST['tgdb_shared']) ? '1' : '0');
            flash('ok', isset($_POST['tgdb_shared'])
                ? 'Nutzer ohne eigenen Key dürfen jetzt den System-Key mitnutzen.'
                : 'Nutzer brauchen jetzt einen eigenen API-Key für TheGamesDB.');
            redirect('admin/settings');
        }
        $result = (new TheGamesDb(Catalog::systemKey(), App::db()))->ping();
        if ($action === 'save') {
            flash($result['ok'] ? 'ok' : 'warn', 'API-Key gespeichert. ' . ($result['ok'] ? $result['message'] : 'Test fehlgeschlagen: ' . $result['message']));
        } else {
            flash($result['ok'] ? 'ok' : 'error', $result['message']);
        }
        redirect('admin/settings');
    }

    /** Plattformen aus TheGamesDB auswählen und importieren. */
    public function platformImport(): void
    {
        $tgdb = Catalog::tgdb();
        $db = App::db();

        if (is_post()) {
            $ids = isset($_POST['all'])
                ? null
                : array_values(array_unique(array_filter(array_map('intval', is_array($_POST['ids'] ?? null) ? $_POST['ids'] : []))));
            try {
                $count = 0;
                $db->transaction(function () use ($tgdb, $ids, &$count) {
                    foreach ($tgdb->platforms() as $p) {
                        if ($ids === null || in_array($p['tgdb_id'], $ids, true)) {
                            Catalog::upsertPlatform($p);
                            $count++;
                        }
                    }
                });
                flash('ok', $count . ' Plattform(en) importiert/aktualisiert.');
            } catch (\Throwable $e) {
                flash('error', 'Import fehlgeschlagen: ' . $e->getMessage());
            }
            redirect('admin/platforms');
        }

        $remote = [];
        $error = null;
        if ($tgdb->enabled()) {
            try {
                $remote = $tgdb->platforms();
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }
        $local = $db->all('SELECT p.*, (SELECT COUNT(*) FROM {items} i WHERE i.platform_id = p.id) AS used FROM {platforms} p ORDER BY p.name');
        $imported = array_flip(array_filter(array_map(fn ($p) => $p['tgdb_id'] ? (int)$p['tgdb_id'] : null, $local)));

        View::render('admin/platforms', [
            'title'    => 'Plattformen verwalten',
            'nav'      => 'admin',
            'remote'   => $remote,
            'local'    => $local,
            'imported' => $imported,
            'error'    => $error,
            'enabled'  => $tgdb->enabled(),
        ]);
    }

    /** Eigene Plattform anlegen/bearbeiten (z. B. Exoten, die TGDB nicht kennt). */
    public function platformEdit(): void
    {
        $db = App::db();
        $id = input_int('id', 1) ?? query_int('id', 1);
        $platform = $id ? $db->one('SELECT * FROM {platforms} WHERE id = ?', [$id]) : null;
        $errors = [];

        if (is_post()) {
            $data = [
                'name'         => input('name', 150),
                'alias'        => input('alias', 150) ?: null,
                'manufacturer' => input('manufacturer', 150) ?: null,
                'developer'    => input('developer', 150) ?: null,
                'media'        => input('media', 100) ?: null,
                'cpu'          => input('cpu', 255) ?: null,
                'memory'       => input('memory', 255) ?: null,
                'overview'     => input('overview', 10000) ?: null,
            ];
            if ($data['name'] === '') {
                $errors[] = 'Bitte einen Namen angeben.';
            }
            if (!$errors) {
                if ($platform) {
                    $db->update('platforms', $data, ['id' => $platform['id']]);
                } else {
                    $db->insert('platforms', $data + ['created_at' => Database::now()]);
                }
                flash('ok', 'Plattform gespeichert.');
                redirect('admin/platforms');
            }
            $platform = array_merge($platform ?? [], $data);
        }

        View::render('admin/platform_form', [
            'title'    => $platform && !empty($platform['id']) ? 'Plattform bearbeiten' : 'Neue Plattform',
            'nav'      => 'admin',
            'platform' => $platform ?? [],
            'errors'   => $errors,
        ]);
    }

    public function platformDelete(): void
    {
        if (is_post() && ($id = input_int('id', 1))) {
            App::db()->delete('platforms', ['id' => $id]);
            flash('ok', 'Plattform entfernt. Verknüpfte Einträge bleiben erhalten (ohne Plattform).');
        }
        redirect('admin/platforms');
    }
}
