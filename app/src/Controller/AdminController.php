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
                Accounts::deleteUser($user);
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
            'captchaOk'    => Recaptcha::configured(),
            'useRecaptcha' => Recaptcha::active(),
            'mode'         => Accounts::mode(),
            'requireEmail' => Settings::bool($db, 'require_email'),
            'mail'         => Mailer::settings(),
            'mailOk'       => Mailer::configured(),
            'baseUrl'      => Accounts::baseUrl(),
            'pendingCount' => Accounts::pendingCount(),
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

    /** Bot-Schutz wählen sowie reCAPTCHA-Schlüssel speichern, prüfen oder entfernen. */
    public function recaptcha(): void
    {
        if (!is_post()) {
            redirect('admin/settings');
        }
        $db = App::db();
        $action = input('action', 10);
        if ($action === 'mode') {
            $want = input('bot_protection', 10) === 'recaptcha' ? 'recaptcha' : 'builtin';
            if ($want === 'recaptcha' && !Recaptcha::configured()) {
                flash('error', 'Für reCAPTCHA bitte zuerst Website-Schlüssel und Geheimschlüssel eintragen.');
            } else {
                Settings::set($db, 'bot_protection', $want);
                flash('ok', $want === 'recaptcha'
                    ? 'Die Registrierung nutzt jetzt Google reCAPTCHA. Bitte die Datenschutzerklärung um reCAPTCHA ergänzen.'
                    : 'Die Registrierung nutzt jetzt den eingebauten Bot-Schutz.');
            }
            redirect('admin/settings');
        }
        if ($action === 'remove') {
            Recaptcha::clear();
            if (Recaptcha::selected()) {
                Settings::set($db, 'bot_protection', 'builtin');
                flash('warn', 'reCAPTCHA entfernt – die Registrierung nutzt wieder den eingebauten Bot-Schutz.');
            } else {
                flash('ok', 'reCAPTCHA-Schlüssel entfernt.');
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
        $prefix = $action === 'save' ? 'reCAPTCHA-Schlüssel gespeichert. ' : '';
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
    /** Admin › Zubehör-Katalog: Liste mit Suche, Löschen und Standardbild entfernen. */
    public function accessories(): void
    {
        $db = App::db();
        if (is_post()) {
            $id = input_int('id', 1);
            $acc = \Amigo\Service\AccessoryCatalog::find($id);
            if ($acc) {
                $action = input('action', 20);
                if ($action === 'delete') {
                    \Amigo\Service\AccessoryCatalog::delete((int)$acc['id']);
                    flash('ok', '„' . $acc['name'] . '“ aus dem Katalog gelöscht. Sammlungs-Einträge bleiben erhalten.');
                } elseif ($action === 'remove_image') {
                    \Amigo\Service\AccessoryCatalog::removeImage((int)$acc['id']);
                    flash('ok', 'Standardbild von „' . $acc['name'] . '“ entfernt. Das nächste hochgeladene Foto wird neues Standardbild.');
                }
            }
            redirect('admin/accessories', array_filter(['q' => query('q', 100)]));
        }
        $q = query('q', 100);
        $params = [];
        $where = '';
        if ($q !== '') {
            $where = " WHERE a.name LIKE ? ESCAPE '!' OR a.manufacturer LIKE ? ESCAPE '!' OR p.name LIKE ? ESCAPE '!'";
            $params = array_fill(0, 3, Database::like($q));
        }
        $rows = $db->all(
            'SELECT a.*, p.name AS platform_name, u.username AS creator, iu.username AS image_by,
                    (SELECT COUNT(*) FROM {items} i WHERE i.accessory_id = a.id) AS uses
             FROM {accessories} a
             LEFT JOIN {platforms} p ON p.id = a.platform_id
             LEFT JOIN {users} u ON u.id = a.created_by
             LEFT JOIN {users} iu ON iu.id = a.image_user_id' . $where . '
             ORDER BY a.name LIMIT 500',
            $params
        );
        View::render('admin/accessories', ['title' => 'Zubehör-Katalog', 'nav' => 'admin', 'rows' => $rows, 'q' => $q]);
    }

    /** Katalog-Eintrag bearbeiten (Name, Plattform, Hersteller). */
    public function accessoryEdit(): void
    {
        $db = App::db();
        $acc = \Amigo\Service\AccessoryCatalog::find(input_int('id', 1) ?? query_int('id', 1));
        if (!$acc) {
            redirect('admin/accessories');
        }
        $errors = [];
        if (is_post()) {
            $name = mb_substr(trim(preg_replace('/\s+/u', ' ', input('name', 150))), 0, 150);
            $platformId = input_int('platform_id', 1);
            if ($name === '') {
                $errors[] = 'Bitte einen Namen angeben.';
            }
            if ($platformId !== null && !Catalog::platformExists($platformId)) {
                $errors[] = 'Unbekannte Plattform.';
            }
            if (!$errors) {
                $db->update('accessories', [
                    'name'         => $name,
                    'platform_id'  => $platformId,
                    'manufacturer' => input('manufacturer', 150) ?: null,
                    'updated_at'   => Database::now(),
                ], ['id' => (int)$acc['id']]);
                flash('ok', 'Katalog-Eintrag gespeichert.');
                redirect('admin/accessories');
            }
            $acc = array_merge($acc, ['name' => $name, 'platform_id' => $platformId, 'manufacturer' => input('manufacturer', 150)]);
        }
        View::render('admin/accessory_form', [
            'title' => 'Zubehör bearbeiten', 'nav' => 'admin', 'acc' => $acc, 'errors' => $errors, 'platforms' => Catalog::platforms(),
        ]);
    }

    /** Admin › Statistik: Badges, Einträge pro Tag, Nutzerzuwachs, Farbschemata. */
    public function stats(): void
    {
        $db = App::db();
        $days = 30;
        $tz = new \DateTimeZone(date_default_timezone_get());
        $today = new \DateTimeImmutable('today', $tz);
        $from = $today->modify('-' . ($days - 1) . ' days');
        // Grenze in UTC (Zeitstempel in der DB sind UTC); einen Tag Puffer, gezählt wird nach Ortszeit
        $fromUtc = $from->modify('-1 day')->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        // Tage der letzten 30 Tage (Ortszeit) mit 0 vorbelegen
        $empty = [];
        for ($d = $from; $d <= $today; $d = $d->modify('+1 day')) {
            $empty[$d->format('Y-m-d')] = 0;
        }
        $perDay = function (string $table) use ($db, $fromUtc, $tz, $empty): array {
            $out = $empty;
            foreach ($db->all("SELECT created_at FROM {{$table}} WHERE created_at >= ?", [$fromUtc]) as $r) {
                $day = (new \DateTimeImmutable($r['created_at'], new \DateTimeZone('UTC')))->setTimezone($tz)->format('Y-m-d');
                if (isset($out[$day])) {
                    $out[$day]++;
                }
            }
            return $out;
        };
        // Durchschnitt pro Tag seit dem ersten Eintrag (inkl. heute)
        $average = function (string $table) use ($db, $today, $tz): array {
            $row = $db->one("SELECT COUNT(*) AS n, MIN(created_at) AS first FROM {{$table}}");
            $n = (int)$row['n'];
            if (!$n || !$row['first']) {
                return ['total' => 0, 'days' => 0, 'avg' => 0.0];
            }
            $first = (new \DateTimeImmutable($row['first'], new \DateTimeZone('UTC')))->setTimezone($tz)->setTime(0, 0);
            $span = (int)$first->diff($today)->days + 1;
            return ['total' => $n, 'days' => $span, 'avg' => $n / $span];
        };

        $items = $perDay('items');
        $plays = $perDay('plays');
        $users = $perDay('users');

        // Badges: 'b:<id>' = Badge, 'u:<datei>' = eigenes Bild, leer = automatisch
        $badges = array_fill_keys(array_keys(\Amigo\Core\Badges::ALL), 0);
        $custom = 0;
        $auto = 0;
        foreach ($db->all('SELECT avatar, COUNT(*) AS n FROM {users} GROUP BY avatar') as $r) {
            $a = (string)($r['avatar'] ?? '');
            if (str_starts_with($a, 'b:') && isset($badges[substr($a, 2)])) {
                $badges[substr($a, 2)] += (int)$r['n'];
            } elseif (str_starts_with($a, 'u:')) {
                $custom += (int)$r['n'];
            } else {
                $auto += (int)$r['n'];
            }
        }
        arsort($badges);

        // Farbschemata (zuletzt genutzt; NULL = seit dem Update noch nicht angemeldet)
        $themes = array_fill_keys(array_keys(\Amigo\Core\Options::THEMES), 0);
        $themeUnknown = 0;
        foreach ($db->all('SELECT theme, COUNT(*) AS n FROM {users} GROUP BY theme') as $r) {
            if ($r['theme'] !== null && isset($themes[$r['theme']])) {
                $themes[$r['theme']] += (int)$r['n'];
            } else {
                $themeUnknown += (int)$r['n'];
            }
        }

        View::render('admin/stats', [
            'title'        => 'Statistik',
            'nav'          => 'admin',
            'days'         => $days,
            'items'        => $items,
            'plays'        => $plays,
            'users'        => $users,
            'avgItems'     => $average('items'),
            'avgPlays'     => $average('plays'),
            'avgUsers'     => $average('users'),
            'badges'       => $badges,
            'custom'       => $custom,
            'auto'         => $auto,
            'themes'       => $themes,
            'themeUnknown' => $themeUnknown,
            'userTotal'    => (int)$db->value('SELECT COUNT(*) FROM {users}'),
        ]);
    }

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
