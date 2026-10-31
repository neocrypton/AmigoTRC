<?php
declare(strict_types=1);

namespace Amigo\Controller;

use Amigo\Core\App;
use Amigo\Core\Auth;
use Amigo\Core\Crypto;
use Amigo\Core\Options;
use Amigo\Core\RateLimiter;
use Amigo\Core\Totp;
use Amigo\Core\View;
use Amigo\Core\Badges;
use Amigo\Service\Accounts;
use Amigo\Service\Avatar;
use Amigo\Service\Catalog;
use Amigo\Service\Mailer;
use Amigo\Service\TheGamesDb;

final class AccountController
{
    public function index(): void
    {
        $user = Auth::user();
        View::render('account/index', [
            'title'         => 'Mein Konto',
            'nav'           => 'account',
            'user'          => $user,
            'recoveryLeft'  => count(json_decode((string)$user['recovery_codes'], true) ?: []),
            'keySource'     => Catalog::keySourceFor($user),
            'keyHint'       => ($k = Catalog::userKey($user)) !== '' ? '…' . substr($k, -4) : null,
        ]);
    }

    /** Avatar wählen (Badge), hochladen oder eigenes Bild entfernen. */
    public function avatar(): void
    {
        if (!is_post()) {
            redirect('account');
        }
        $user = Auth::user();
        $db = App::db();
        $action = input('action', 10);
        if ($action === 'badge') {
            $badge = input('badge', 40);
            if (!isset(Badges::ALL[$badge])) {
                flash('error', 'Unbekannter Badge.');
            } else {
                Avatar::removeFile($user);
                $db->update('users', ['avatar' => 'b:' . $badge], ['id' => $user['id']]);
                flash('ok', 'Avatar gewählt: ' . Badges::ALL[$badge] . '.');
            }
        } elseif ($action === 'upload') {
            $bucket = 'avatar:' . $user['id'];
            if (RateLimiter::tooMany($bucket, 10, 3600)) {
                flash('error', 'Zu viele Uploads – bitte später erneut.');
            } else {
                RateLimiter::hit($bucket);
                try {
                    $name = Avatar::store(is_array($_FILES['avatar'] ?? null) ? $_FILES['avatar'] : []);
                    Avatar::removeFile($user);
                    $db->update('users', ['avatar' => 'u:' . $name], ['id' => $user['id']]);
                    flash('ok', 'Eigenes Bild als Avatar gespeichert.');
                } catch (\RuntimeException $e) {
                    flash('error', $e->getMessage());
                }
            }
        } elseif ($action === 'reset') {
            Avatar::removeFile($user);
            $db->update('users', ['avatar' => null], ['id' => $user['id']]);
            flash('ok', 'Avatar zurückgesetzt.');
        }
        redirect('account');
    }

    /** Eigenes Avatarbild ausliefern (nur für angemeldete Nutzer, wie die Community). */
    public function avatarImage(): void
    {
        $user = App::db()->one('SELECT id, avatar FROM {users} WHERE id = ?', [query_int('u', 1) ?? 0]);
        $a = $user ? Avatar::resolve($user) : null;
        $path = ($a && $a['type'] === 'upload') ? Avatar::file($a['value']) : null;
        if (!$path) {
            http_response_code(404);
            exit;
        }
        $etag = '"' . $a['value'] . '"';
        header('Cache-Control: private, max-age=31536000, immutable');
        header('ETag: ' . $etag);
        if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
            http_response_code(304);
            exit;
        }
        header('Content-Type: image/png');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    /** Bestätigungs-Mail für die eigene Adresse (erneut) senden. */
    public function verifyMail(): void
    {
        $user = Auth::user();
        if (is_post() && !empty($user['email']) && !$user['email_verified_at'] && Mailer::configured()) {
            $bucket = 'resend:' . $user['id'];
            if (RateLimiter::tooMany($bucket, 3, 3600)) {
                flash('error', 'Bitte später erneut versuchen.');
            } else {
                RateLimiter::hit($bucket);
                try {
                    Accounts::sendVerification($user);
                    flash('ok', 'Bestätigungs-Mail verschickt.');
                } catch (\Throwable) {
                    flash('error', 'Die E-Mail konnte nicht verschickt werden.');
                }
            }
        }
        redirect('account');
    }

    /** E-Mail-Benachrichtigung bei neuen PNs an/aus. */
    public function notify(): void
    {
        if (is_post()) {
            $on = isset($_POST['pm_notify']);
            App::db()->update('users', ['pm_notify' => $on ? 1 : 0], ['id' => Auth::id()]);
            flash('ok', $on ? 'Du bekommst eine E-Mail bei neuen privaten Nachrichten.' : 'Keine E-Mails mehr bei neuen privaten Nachrichten.');
        }
        redirect('account');
    }

    /** Abmelde-Link aus der Benachrichtigungs-Mail (ohne Login, mit Bestätigung). */
    public function unsubscribe(): void
    {
        $uid = input_int('u', 1) ?? query_int('u', 1) ?? 0;
        $token = input('t', 64) ?: query('t', 64);
        $valid = $uid > 0 && hash_equals(\Amigo\Service\Messages::unsubscribeToken($uid), $token)
            && App::db()->value('SELECT id FROM {users} WHERE id = ?', [$uid]);
        $done = false;
        if ($valid && is_post()) {
            App::db()->update('users', ['pm_notify' => 0], ['id' => $uid]);
            $done = true;
        }
        View::render('account/unsubscribe', ['title' => 'Benachrichtigungen', 'valid' => (bool)$valid, 'done' => $done, 'uid' => $uid, 'token' => $token], 'layout_plain');
    }

    /** Sichtbarkeit der eigenen Sammlung / Durchgespielt-Liste für andere Nutzer. */
    public function privacy(): void
    {
        if (is_post()) {
            App::db()->update('users', [
                'collection_public' => isset($_POST['collection_public']) ? 1 : 0,
                'plays_public'      => isset($_POST['plays_public']) ? 1 : 0,
            ], ['id' => Auth::id()]);
            flash('ok', 'Sichtbarkeit gespeichert.');
        }
        redirect('account');
    }

    /** Eigenen TheGamesDB-API-Key speichern, testen oder entfernen. */
    public function tgdbKey(): void
    {
        if (!is_post()) {
            redirect('account');
        }
        $user = Auth::user();
        $action = input('action', 10);
        if ($action === 'remove') {
            Catalog::saveUserKey((int)$user['id'], null);
            flash('ok', 'Dein API-Key wurde entfernt.');
            redirect('account');
        }
        if ($action === 'save') {
            $key = preg_replace('/\s+/', '', input('tgdb_api_key', 200));
            if (!Catalog::validApiKey($key)) {
                flash('error', 'Der API-Key sieht ungültig aus (nur Buchstaben und Ziffern, 16–128 Zeichen).');
                redirect('account');
            }
            Catalog::saveUserKey((int)$user['id'], $key);
        } else {
            $key = Catalog::userKey($user);
        }
        $uid = (int)$user['id'];
        $db = App::db();
        $result = (new TheGamesDb($key, $db, fn (int $left) => $db->update('users', ['tgdb_allowance' => $left], ['id' => $uid])))->ping();
        if ($action === 'save') {
            flash($result['ok'] ? 'ok' : 'warn', 'API-Key gespeichert. ' . ($result['ok'] ? $result['message'] : 'Test fehlgeschlagen: ' . $result['message']));
        } else {
            flash($result['ok'] ? 'ok' : 'error', $result['message']);
        }
        redirect('account');
    }

    public function profile(): void
    {
        if (is_post()) {
            $email = input('email', 190);
            $user = Auth::user();
            if ($email === '' && Accounts::emailRequired() && $user['role'] !== 'admin') {
                flash('error', 'Eine E-Mail-Adresse ist Pflicht.');
            } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                flash('error', 'Die E-Mail-Adresse ist ungültig.');
            } elseif ($email !== '' && (int)App::db()->value('SELECT COUNT(*) FROM {users} WHERE LOWER(email) = LOWER(?) AND id <> ?', [$email, Auth::id()]) > 0) {
                flash('error', 'Diese E-Mail-Adresse wird bereits verwendet.');
            } elseif (strcasecmp($email, (string)$user['email']) === 0) {
                flash('info', 'Keine Änderung.');
            } else {
                // Neue Adresse gilt als unbestätigt.
                App::db()->update('users', ['email' => $email ?: null, 'email_verified_at' => null], ['id' => Auth::id()]);
                $msg = 'Profil gespeichert.';
                if ($email !== '' && Mailer::configured()) {
                    try {
                        Accounts::sendVerification(['email' => $email] + $user);
                        $msg .= ' Bitte bestätige die neue Adresse über den Link in der E-Mail.';
                    } catch (\Throwable) {
                        $msg .= ' Die Bestätigungs-Mail konnte nicht verschickt werden.';
                    }
                }
                flash('ok', $msg);
            }
        }
        redirect('account');
    }

    public function password(): void
    {
        if (!is_post()) {
            redirect('account');
        }
        $user = Auth::user();
        $current = (string)($_POST['current'] ?? '');
        $new = (string)($_POST['password'] ?? '');

        if (!$this->checkPassword($user, $current)) {
            flash('error', 'Das aktuelle Passwort ist falsch.');
        } elseif ($problem = Auth::passwordProblem($new, $user['username'])) {
            flash('error', $problem);
        } elseif (!hash_equals($new, (string)($_POST['password2'] ?? ''))) {
            flash('error', 'Die neuen Passwörter stimmen nicht überein.');
        } else {
            App::db()->update('users', ['password_hash' => Auth::hash($new)], ['id' => $user['id']]);
            Auth::refreshPasswordVersion();
            flash('ok', 'Passwort geändert. Andere Sitzungen wurden abgemeldet.');
        }
        redirect('account');
    }

    public function twoFactorSetup(): void
    {
        $user = Auth::user();
        if ((int)$user['totp_enabled'] === 1) {
            flash('info', '2FA ist bereits aktiv.');
            redirect('account');
        }
        if (empty($_SESSION['totp_setup'])) {
            $_SESSION['totp_setup'] = Totp::generateSecret();
        }
        $secret = $_SESSION['totp_setup'];
        $error = null;

        if (is_post()) {
            $bucket = '2fasetup:' . $user['id'];
            if (RateLimiter::tooMany($bucket, 10, 900)) {
                $error = 'Zu viele Versuche – bitte kurz warten.';
            } elseif (($step = Totp::verify($secret, input('code', 10))) !== null) {
                [$codes, $hashes] = Auth::generateRecoveryCodes();
                App::db()->update('users', [
                    'totp_secret'    => Crypto::encrypt($secret),
                    'totp_enabled'   => 1,
                    'totp_last_step' => $step,
                    'recovery_codes' => $hashes,
                ], ['id' => $user['id']]);
                unset($_SESSION['totp_setup']);
                RateLimiter::clear($bucket);
                session_regenerate_id(true);
                $this->showRecoveryCodes($codes, '2FA aktiviert! Bewahre diese Recovery-Codes sicher auf.');
                return;
            } else {
                RateLimiter::hit($bucket);
                $error = 'Der Code stimmt nicht. Uhrzeit am Handy korrekt?';
            }
        }

        $issuer = (string)App::config('site_name', 'AMIGO!');
        header('Cache-Control: no-store');
        View::render('account/twofactor', [
            'title'   => '2FA einrichten',
            'nav'     => 'account',
            'scripts' => ['js/qrcode.min.js'],
            'secret' => $secret,
            'uri'    => Totp::uri($secret, $user['username'], $issuer),
            'error'  => $error,
        ]);
    }

    public function twoFactorDisable(): void
    {
        if (!is_post()) {
            redirect('account');
        }
        $user = Auth::user();
        $secret = Crypto::decrypt((string)$user['totp_secret']);
        $bucket = '2faoff:' . $user['id'];
        if (RateLimiter::tooMany($bucket, 5, 900)) {
            flash('error', 'Zu viele Versuche – bitte später erneut.');
        } elseif (!$this->checkPassword($user, (string)($_POST['current'] ?? ''))
            || $secret === null
            || Totp::verify($secret, input('code', 10), (int)$user['totp_last_step']) === null) {
            RateLimiter::hit($bucket);
            flash('error', 'Passwort oder 2FA-Code falsch.');
        } else {
            App::db()->update('users', ['totp_secret' => null, 'totp_enabled' => 0, 'totp_last_step' => 0, 'recovery_codes' => null], ['id' => $user['id']]);
            flash('ok', '2FA deaktiviert.');
        }
        redirect('account');
    }

    public function recoveryCodes(): void
    {
        $user = Auth::user();
        if (!is_post() || (int)$user['totp_enabled'] !== 1) {
            redirect('account');
        }
        if (!$this->checkPassword($user, (string)($_POST['current'] ?? ''))) {
            flash('error', 'Das Passwort ist falsch.');
            redirect('account');
        }
        [$codes, $hashes] = Auth::generateRecoveryCodes();
        App::db()->update('users', ['recovery_codes' => $hashes], ['id' => $user['id']]);
        $this->showRecoveryCodes($codes, 'Neue Recovery-Codes erzeugt. Die alten sind ab sofort ungültig.');
    }

    /** CSV-Export der eigenen Daten. */
    public function export(): void
    {
        $type = query('type', 10) === 'plays' ? 'plays' : 'collection';
        $db = App::db();
        if ($type === 'plays') {
            $rows = $db->all(
                'SELECT pl.title, p.name AS platform, pl.finished_on, pl.playtime_minutes, pl.score, pl.rating, pl.review, g.tgdb_id
                 FROM {plays} pl LEFT JOIN {platforms} p ON p.id = pl.platform_id LEFT JOIN {games} g ON g.id = pl.game_id
                 WHERE pl.user_id = ? ORDER BY pl.finished_on',
                [Auth::id()]
            );
            $head = ['Titel', 'Plattform', 'Durchgespielt am', 'Spielzeit (Min)', 'Punkte', 'Sterne', 'Bewertung', 'TGDB-ID'];
        } else {
            $rows = $db->all(
                'SELECT i.title, p.name AS platform, i.kind, i.region, i.completeness, i.cond_overall, i.cond_media, i.cond_box, i.cond_manual,
                        i.condition_notes, i.purchase_date, i.price_cents, i.purchase_place, i.notes, g.tgdb_id
                 FROM {items} i LEFT JOIN {platforms} p ON p.id = i.platform_id LEFT JOIN {games} g ON g.id = i.game_id
                 WHERE i.user_id = ? ORDER BY p.name, i.title',
                [Auth::id()]
            );
            foreach ($rows as &$r) {
                $r['kind'] = Options::KINDS[$r['kind']] ?? $r['kind'];
                foreach (['cond_overall', 'cond_media', 'cond_box', 'cond_manual'] as $c) {
                    $r[$c] = $r[$c] === null ? '' : cond_label((int)$r[$c]);
                }
                $r['price_cents'] = $r['price_cents'] === null ? '' : number_format($r['price_cents'] / 100, 2, ',', '');
            }
            unset($r);
            $head = ['Titel', 'Plattform', 'Art', 'Region', 'Vollständigkeit', 'Zustand', 'Zustand Medium', 'Zustand Verpackung',
                'Zustand Anleitung', 'Zustandsnotiz', 'Kaufdatum', 'Preis (EUR)', 'Gekauft bei', 'Notizen', 'TGDB-ID'];
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="amigo-' . $type . '-' . date('Y-m-d') . '.csv"');
        header('Cache-Control: private, no-store');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // BOM, damit Excel UTF-8 erkennt
        fputcsv($out, $head, ';', '"', '');
        foreach ($rows as $row) {
            fputcsv($out, array_map([$this, 'csvSafe'], array_values($row)), ';', '"', '');
        }
        fclose($out);
        exit;
    }

    /** Verhindert CSV-/Formel-Injection beim Öffnen in Excel/LibreOffice. */
    private function csvSafe(mixed $v): string
    {
        $v = (string)($v ?? '');
        return ($v !== '' && str_contains('=+-@' . "\t\r", $v[0])) ? "'" . $v : $v;
    }

    private function checkPassword(array $user, string $password): bool
    {
        $bucket = 'pwcheck:' . $user['id'];
        if (RateLimiter::tooMany($bucket, 5, 900)) {
            return false;
        }
        $ok = $password !== '' && strlen($password) <= 1000 && password_verify($password, $user['password_hash']);
        if (!$ok) {
            RateLimiter::hit($bucket);
        }
        return $ok;
    }

    private function showRecoveryCodes(array $codes, string $message): void
    {
        header('Cache-Control: no-store');
        View::render('account/recovery', ['title' => 'Recovery-Codes', 'nav' => 'account', 'codes' => $codes, 'message' => $message]);
    }
}
