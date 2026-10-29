<?php
declare(strict_types=1);

namespace Amigo\Controller;

use Amigo\Core\App;
use Amigo\Core\Auth;
use Amigo\Core\Database;
use Amigo\Core\Settings;
use Amigo\Core\View;
use Amigo\Service\Catalog;

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
                    (SELECT COUNT(*) FROM {items} i WHERE i.user_id = u.id) AS items
             FROM {users} u ORDER BY u.username'
        );
        View::render('admin/users', ['title' => 'Benutzer', 'nav' => 'admin', 'users' => $users, 'errors' => $errors, 'data' => $data]);
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
                $dir = App::dataDir() . '/uploads/' . (int)$user['id'];
                foreach (glob($dir . '/*.jpg') ?: [] as $f) {
                    @unlink($f);
                }
                @rmdir($dir);
                $db->delete('users', ['id' => $user['id']]);
                flash('ok', 'Benutzer und alle Daten gelöscht.');
                break;
        }
        redirect('admin/users');
    }

    public function settings(): void
    {
        $db = App::db();
        if (is_post()) {
            Settings::set($db, 'registration_open', isset($_POST['registration_open']) ? '1' : '0');
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
            'TheGamesDB'            => [Catalog::tgdb()->enabled() ? 'API-Key gesetzt' : 'kein API-Key', Catalog::tgdb()->enabled()],
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
            'dataInWeb'    => str_starts_with(realpath(App::dataDir()) ?: '', realpath(AMIGO_ROOT) ?: "\0"),
        ]);
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
