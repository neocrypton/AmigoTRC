<?php
declare(strict_types=1);

namespace Amigo\Service;

use Amigo\Core\App;
use Amigo\Core\Crypto;
use Amigo\Core\Settings;

/**
 * Google reCAPTCHA v2 („Ich bin kein Roboter“) für die öffentliche Registrierung.
 * Website-Schlüssel ist öffentlich, der Geheimschlüssel wird verschlüsselt gespeichert
 * und die Prüfung erfolgt ausschließlich serverseitig.
 */
final class Recaptcha
{
    /** Zusätzliche CSP-Quellen, die das Widget braucht (nur auf der Registrierungsseite). */
    public const CSP = [
        'script-src' => ['https://www.google.com/recaptcha/', 'https://www.gstatic.com/recaptcha/'],
        'frame-src'  => ['https://www.google.com/recaptcha/', 'https://recaptcha.google.com/recaptcha/'],
        'style-src'  => ["'unsafe-inline'"],
    ];

    public static function siteKey(): string
    {
        return (string)Settings::get(App::db(), 'recaptcha_site', '');
    }

    public static function secret(): string
    {
        $stored = Settings::get(App::db(), 'recaptcha_secret');
        return $stored ? (string)Crypto::decrypt($stored) : '';
    }

    public static function configured(): bool
    {
        return self::siteKey() !== '' && self::secret() !== '';
    }

    public static function validKey(string $key): bool
    {
        return (bool)preg_match('/^[A-Za-z0-9_-]{20,100}$/', $key);
    }

    public static function save(string $siteKey, string $secret): void
    {
        $db = App::db();
        Settings::set($db, 'recaptcha_site', $siteKey);
        Settings::set($db, 'recaptcha_secret', Crypto::encrypt($secret));
    }

    public static function clear(): void
    {
        $db = App::db();
        Settings::set($db, 'recaptcha_site', null);
        Settings::set($db, 'recaptcha_secret', null);
    }

    /** Prüft das Token aus dem Formular (g-recaptcha-response) bei Google. */
    public static function verify(string $token, string $ip): bool
    {
        if (!self::configured() || $token === '' || strlen($token) > 4000) {
            return false;
        }
        $res = self::siteverify(['secret' => self::secret(), 'response' => $token, 'remoteip' => $ip]);
        if (!is_array($res) || ($res['success'] ?? false) !== true) {
            return false;
        }
        // Token muss für diese Domain ausgestellt sein (falls Google den Hostnamen liefert).
        $host = strtolower((string)parse_url('//' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST));
        return empty($res['hostname']) || $host === '' || strtolower((string)$res['hostname']) === $host;
    }

    /**
     * Prüft, ob Google den Geheimschlüssel akzeptiert. Ein absichtlich leeres
     * Token liefert bei gültigem Schlüssel nur „missing-input-response“.
     * @return array{ok: bool, message: string}
     */
    public static function checkSecret(): array
    {
        $res = self::siteverify(['secret' => self::secret(), 'response' => '']);
        if ($res === null) {
            return ['ok' => false, 'message' => 'Google reCAPTCHA ist vom Server aus nicht erreichbar.'];
        }
        $codes = (array)($res['error-codes'] ?? []);
        if (in_array('invalid-input-secret', $codes, true) || in_array('missing-input-secret', $codes, true)) {
            return ['ok' => false, 'message' => 'Google lehnt den Geheimschlüssel ab.'];
        }
        return ['ok' => true, 'message' => 'Geheimschlüssel von Google akzeptiert.'];
    }

    private static function siteverify(array $fields): ?array
    {
        $url = (string)App::config('recaptcha_verify_url', 'https://www.google.com/recaptcha/api/siteverify');
        $body = http_build_query($fields);
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $body,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);
            $out = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
            if ($out === false || $status !== 200) {
                return null;
            }
        } else {
            $ctx = stream_context_create(['http' => [
                'method' => 'POST', 'timeout' => 10, 'follow_location' => 0,
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n", 'content' => $body,
            ]]);
            $out = @file_get_contents($url, false, $ctx);
            if ($out === false) {
                return null;
            }
        }
        $data = json_decode((string)$out, true);
        return is_array($data) ? $data : null;
    }
}
