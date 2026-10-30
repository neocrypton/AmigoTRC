<?php
declare(strict_types=1);

namespace Amigo\Controller;

use Amigo\Core\App;
use Amigo\Core\Auth;
use Amigo\Core\Crypto;
use Amigo\Core\Database;
use Amigo\Core\RateLimiter;
use Amigo\Core\Settings;
use Amigo\Core\Totp;
use Amigo\Core\View;
use Amigo\Service\Accounts;
use Amigo\Service\Mailer;
use Amigo\Service\Recaptcha;

final class AuthController
{
    private const WINDOW = 900; // 15 Minuten

    public function login(): void
    {
        $error = null;
        $username = '';
        $canResend = false;

        if (is_post()) {
            $username = input('username', 40);
            $password = (string)($_POST['password'] ?? '');
            $ipBucket = 'login:ip:' . RateLimiter::ip();
            $userBucket = 'login:user:' . mb_strtolower($username);

            if (RateLimiter::tooMany($ipBucket, 20, self::WINDOW) || RateLimiter::tooMany($userBucket, 5, self::WINDOW)) {
                $error = 'Zu viele Fehlversuche. Bitte in 15 Minuten erneut versuchen.';
            } elseif ($username === '' || $password === '' || strlen($password) > 1000) {
                $error = 'Bitte Benutzername und Passwort eingeben.';
            } else {
                $user = Auth::attempt($username, $password);
                if (!$user && Auth::$failure !== 'invalid') {
                    // Passwort stimmte, Konto ist aber (noch) nicht aktiv.
                    RateLimiter::clear($userBucket);
                    $error = match (Auth::$failure) {
                        'pending_email' => 'Bitte bestätige zuerst deine E-Mail-Adresse über den Link in unserer Mail.',
                        'pending_admin' => 'Dein Konto wartet noch auf die Freischaltung durch einen Admin.',
                        default         => 'Dieses Konto ist gesperrt.',
                    };
                    if (Auth::$failure === 'pending_email' && Mailer::configured()) {
                        $_SESSION['resend_uid'] = ['uid' => Auth::$failedUserId, 'at' => time()];
                        $canResend = true;
                    }
                } elseif (!$user) {
                    RateLimiter::hit($ipBucket);
                    RateLimiter::hit($userBucket);
                    $error = 'Benutzername oder Passwort falsch.';
                } else {
                    RateLimiter::clear($userBucket);
                    if ((int)$user['totp_enabled'] === 1) {
                        session_regenerate_id(true);
                        $_SESSION['2fa_pending'] = ['uid' => (int)$user['id'], 'at' => time()];
                        redirect('login/2fa');
                    }
                    Auth::login($user);
                    redirect('home');
                }
            }
        }

        View::render('auth/login', [
            'title'        => 'Login',
            'error'        => $error,
            'username'     => $username,
            'registration' => Settings::bool(App::db(), 'registration_open') && Recaptcha::configured(),
            'canResend'    => $canResend,
        ], 'layout_plain');
    }

    /** Bestätigungslink aus der E-Mail. */
    public function verify(): void
    {
        $bucket = 'verify:' . RateLimiter::ip();
        if (RateLimiter::tooMany($bucket, 20, 3600)) {
            flash('error', 'Zu viele Versuche. Bitte später erneut.');
            redirect('login');
        }
        $user = Accounts::verify(query_int('u', 1) ?? 0, query('t', 64));
        if (!$user) {
            RateLimiter::hit($bucket);
            flash('error', 'Der Bestätigungslink ist ungültig oder abgelaufen. Logge dich ein, um einen neuen anzufordern.');
            redirect(Auth::user() ? 'account' : 'login');
        }
        if (Auth::user()) {
            flash('ok', 'E-Mail-Adresse bestätigt.');
            redirect('account');
        }
        flash('ok', $user['pending'] === 'admin'
            ? 'E-Mail bestätigt! Dein Konto wird jetzt noch von einem Admin freigeschaltet – du bekommst Bescheid.'
            : 'E-Mail bestätigt – du kannst dich jetzt einloggen.');
        redirect('login');
    }

    /** Neue Bestätigungs-Mail (nur nach korrektem Passwort beim Login, siehe resend_uid). */
    public function resend(): void
    {
        $r = $_SESSION['resend_uid'] ?? null;
        unset($_SESSION['resend_uid']);
        if (is_post() && is_array($r) && time() - (int)$r['at'] < 600) {
            $user = App::db()->one("SELECT * FROM {users} WHERE id = ? AND pending = 'email'", [(int)$r['uid']]);
            $bucket = 'resend:' . (int)$r['uid'];
            if ($user && !RateLimiter::tooMany($bucket, 3, 3600)) {
                RateLimiter::hit($bucket);
                try {
                    Accounts::sendVerification($user);
                    flash('ok', 'Neue Bestätigungs-Mail verschickt.');
                } catch (\Throwable) {
                    flash('error', 'Die E-Mail konnte nicht verschickt werden. Bitte später erneut versuchen.');
                }
            } else {
                flash('error', 'Bitte später erneut versuchen.');
            }
        }
        redirect('login');
    }

    public function twoFactor(): void
    {
        $pending = $_SESSION['2fa_pending'] ?? null;
        if (!is_array($pending) || time() - (int)$pending['at'] > 300) {
            unset($_SESSION['2fa_pending']);
            flash('warn', 'Bitte erneut einloggen.');
            redirect('login');
        }
        $user = App::db()->one('SELECT * FROM {users} WHERE id = ? AND is_active = 1', [$pending['uid']]);
        if (!$user) {
            redirect('login');
        }

        $error = null;
        if (is_post()) {
            $bucket = '2fa:' . $user['id'];
            $code = input('code', 20);
            if (RateLimiter::tooMany($bucket, 6, self::WINDOW)) {
                $error = 'Zu viele Fehlversuche. Bitte in 15 Minuten erneut versuchen.';
            } else {
                $ok = false;
                $secret = Crypto::decrypt((string)$user['totp_secret']);
                if ($secret !== null && preg_match('/^\d{6}$/', preg_replace('/\s+/', '', $code))) {
                    $step = Totp::verify($secret, $code, (int)$user['totp_last_step']);
                    if ($step !== null) {
                        App::db()->update('users', ['totp_last_step' => $step], ['id' => $user['id']]);
                        $ok = true;
                    }
                } elseif (Auth::useRecoveryCode($user, $code)) {
                    $ok = true;
                    flash('warn', 'Recovery-Code verwendet. Bitte prüfe deine verbleibenden Codes im Konto.');
                }
                if ($ok) {
                    RateLimiter::clear($bucket);
                    Auth::login($user);
                    redirect('home');
                }
                RateLimiter::hit($bucket);
                $error = 'Code ungültig.';
            }
        }

        View::render('auth/twofactor', ['title' => '2FA', 'error' => $error], 'layout_plain');
    }

    public function logout(): void
    {
        if (is_post()) {
            Auth::logout();
            flash('ok', 'GAME OVER – bis zum nächsten Mal!');
        }
        redirect('login');
    }

    public function register(): void
    {
        if (!Settings::bool(App::db(), 'registration_open')) {
            flash('info', 'Die Registrierung ist geschlossen.');
            redirect('login');
        }
        // Öffentliche Registrierung nur mit Bot-Schutz.
        if (!Recaptcha::configured()) {
            flash('info', 'Die Registrierung ist vorübergehend nicht möglich.');
            redirect('login');
        }
        App::sendSecurityHeaders(Recaptcha::CSP);
        $mode = Accounts::mode();
        $emailRequired = Accounts::emailRequired();
        if (Accounts::needsEmail($mode) && !Mailer::configured()) {
            flash('info', 'Die Registrierung ist vorübergehend nicht möglich.');
            redirect('login');
        }
        $errors = [];
        $data = ['username' => '', 'email' => ''];

        if (is_post()) {
            $data['username'] = input('username', 40);
            $data['email'] = input('email', 190);
            $password = (string)($_POST['password'] ?? '');
            $bucket = 'register:' . RateLimiter::ip();

            if (RateLimiter::tooMany($bucket, 5, 3600)) {
                $errors[] = 'Zu viele Registrierungen von dieser Adresse. Bitte später erneut versuchen.';
            } elseif (!Recaptcha::verify((string)($_POST['g-recaptcha-response'] ?? ''), RateLimiter::ip())) {
                // Fehlversuche zählen mit, damit Bots nicht endlos probieren können.
                RateLimiter::hit($bucket . ':captcha');
                $errors[] = 'Bitte bestätige „Ich bin kein Roboter“.';
            }
            if (RateLimiter::tooMany($bucket . ':captcha', 20, 3600)) {
                $errors = ['Zu viele fehlgeschlagene Versuche. Bitte später erneut versuchen.'];
            }
            if ($emailRequired && $data['email'] === '') {
                $errors[] = 'Bitte eine E-Mail-Adresse angeben.';
            }
            Accounts::purgeStale();
            $errors = array_merge($errors, self::validateNewUser($data['username'], $data['email'], $password, (string)($_POST['password2'] ?? '')));

            if (!$errors) {
                RateLimiter::hit($bucket);
                $id = App::db()->insert('users', [
                    'username'      => $data['username'],
                    'email'         => $data['email'] ?: null,
                    'password_hash' => Auth::hash($password),
                    'role'          => 'user',
                    'created_at'    => Database::now(),
                ] + Accounts::initialState());
                $user = App::db()->one('SELECT * FROM {users} WHERE id = ?', [$id]);

                if ($user['pending'] === 'email') {
                    try {
                        Accounts::sendVerification($user);
                        flash('ok', 'Fast geschafft! Wir haben dir eine E-Mail geschickt – bitte bestätige deine Adresse über den Link.');
                    } catch (\Throwable) {
                        flash('warn', 'Konto angelegt, aber die Bestätigungs-Mail konnte nicht verschickt werden. Versuche später, dich einzuloggen, um sie erneut anzufordern.');
                    }
                } elseif ($user['pending'] === 'admin') {
                    Accounts::notifyAdmins($user);
                    flash('ok', 'Konto angelegt! Ein Admin schaltet es in Kürze frei.');
                } else {
                    flash('ok', 'Konto angelegt – PLAYER 2 HAS JOINED! Bitte einloggen.');
                }
                redirect('login');
            }
        }

        View::render('auth/register', [
            'title'   => 'Registrieren',
            'errors'  => $errors,
            'data'    => $data,
            'siteKey' => Recaptcha::siteKey(),
            'emailRequired' => $emailRequired,
            'mode'    => $mode,
            'theme'   => ($_COOKIE['amigo_theme'] ?? 'crt') === 'dmg' ? 'light' : 'dark',
        ], 'layout_plain');
    }

    /** @return string[] Fehlermeldungen */
    public static function validateNewUser(string $username, string $email, string $password, string $password2): array
    {
        $errors = [];
        if (!preg_match('/^[A-Za-z0-9_.-]{3,40}$/', $username)) {
            $errors[] = 'Benutzername: 3–40 Zeichen, nur Buchstaben, Ziffern, Punkt, Binde- und Unterstrich.';
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
        if (!$errors) {
            $exists = App::db()->value(
                'SELECT COUNT(*) FROM {users} WHERE LOWER(username) = LOWER(?) OR (email IS NOT NULL AND LOWER(email) = LOWER(?))',
                [$username, $email === '' ? "\0" : $email]
            );
            if ((int)$exists > 0) {
                $errors[] = 'Benutzername oder E-Mail ist bereits vergeben.';
            }
        }
        return $errors;
    }
}
