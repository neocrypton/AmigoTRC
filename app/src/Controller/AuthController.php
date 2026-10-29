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

final class AuthController
{
    private const WINDOW = 900; // 15 Minuten

    public function login(): void
    {
        $error = null;
        $username = '';

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
                if (!$user) {
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
            'registration' => Settings::bool(App::db(), 'registration_open'),
        ], 'layout_plain');
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
        $errors = [];
        $data = ['username' => '', 'email' => ''];

        if (is_post()) {
            $data['username'] = input('username', 40);
            $data['email'] = input('email', 190);
            $password = (string)($_POST['password'] ?? '');
            $bucket = 'register:' . RateLimiter::ip();

            if (RateLimiter::tooMany($bucket, 5, 3600)) {
                $errors[] = 'Zu viele Registrierungen von dieser Adresse. Bitte später erneut versuchen.';
            }
            $errors = array_merge($errors, self::validateNewUser($data['username'], $data['email'], $password, (string)($_POST['password2'] ?? '')));

            if (!$errors) {
                RateLimiter::hit($bucket);
                App::db()->insert('users', [
                    'username'      => $data['username'],
                    'email'         => $data['email'] ?: null,
                    'password_hash' => Auth::hash($password),
                    'role'          => 'user',
                    'created_at'    => Database::now(),
                ]);
                flash('ok', 'Konto angelegt – PLAYER 2 HAS JOINED! Bitte einloggen.');
                redirect('login');
            }
        }

        View::render('auth/register', ['title' => 'Registrieren', 'errors' => $errors, 'data' => $data], 'layout_plain');
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
