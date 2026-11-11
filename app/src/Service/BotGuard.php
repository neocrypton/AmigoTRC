<?php
declare(strict_types=1);

namespace Amigo\Service;

use Amigo\Core\Crypto;
use Amigo\Core\RateLimiter;

/**
 * Eigener Bot-Schutz für die Registrierung – ohne Fremdanbieter, ohne Cookies.
 *
 * Proof-of-Work nach dem offenen ALTCHA-Prinzip: Der Server stellt eine signierte
 * Aufgabe („finde die Zahl n, für die SHA-256(salt + n) = challenge ergibt“), der
 * Browser probiert die Zahlen durch (für Menschen ca. 1 Sekunde, für Massen-Bots teuer)
 * und schickt die Lösung mit dem Formular. Jede Aufgabe gilt nur einmal und begrenzt lange.
 */
final class BotGuard
{
    /** Größte mögliche Zahl – bestimmt den Rechenaufwand im Browser. */
    public const MAX_NUMBER = 100000;
    private const MIN_NUMBER = 1000;
    private const TTL = 1200;     // 20 Minuten Zeit zum Ausfüllen
    private const MIN_SECONDS = 2; // schneller füllt kein Mensch das Formular aus

    /** @return array{algorithm:string, challenge:string, salt:string, signature:string, maxnumber:int} */
    public static function challenge(): array
    {
        $salt = bin2hex(random_bytes(12)) . '?expires=' . (time() + self::TTL);
        $challenge = hash('sha256', $salt . random_int(self::MIN_NUMBER, self::MAX_NUMBER));
        return [
            'algorithm' => 'SHA-256',
            'challenge' => $challenge,
            'salt'      => $salt,
            'signature' => Crypto::hmac('botguard', $challenge),
            'maxnumber' => self::MAX_NUMBER,
        ];
    }

    /** Prüft die Lösung aus dem Formular (Base64-JSON). Jede Aufgabe wird nur einmal akzeptiert. */
    public static function verify(string $payload): bool
    {
        $json = base64_decode($payload, true);
        $data = $json !== false && strlen($json) < 1000 ? json_decode($json, true) : null;
        if (!is_array($data)) {
            return false;
        }
        $challenge = $data['challenge'] ?? null;
        $salt = $data['salt'] ?? null;
        $number = $data['number'] ?? null;
        $signature = $data['signature'] ?? null;
        if (($data['algorithm'] ?? '') !== 'SHA-256' || !is_string($challenge) || !is_string($salt) || !is_string($signature)
            || !is_int($number) || $number < 0 || $number > self::MAX_NUMBER
            || !preg_match('/^[a-f0-9]{24}\?expires=(\d{10})$/', $salt, $m)) {
            return false;
        }
        $expires = (int)$m[1];
        $issued = $expires - self::TTL;
        if ($expires < time() || time() - $issued < self::MIN_SECONDS) {
            return false;
        }
        if (!hash_equals(Crypto::hmac('botguard', $challenge), $signature) || !hash_equals(hash('sha256', $salt . $number), $challenge)) {
            return false;
        }
        $bucket = 'botguard:' . $challenge;
        if (RateLimiter::tooMany($bucket, 1, self::TTL + 60)) {
            return false; // schon einmal benutzt
        }
        RateLimiter::hit($bucket);
        return true;
    }
}
