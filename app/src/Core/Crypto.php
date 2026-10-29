<?php
declare(strict_types=1);

namespace Amigo\Core;

/**
 * Symmetrische Verschlüsselung sensibler Felder (z. B. 2FA-Geheimnis).
 * Nutzt libsodium (XSalsa20-Poly1305), sonst OpenSSL AES-256-GCM.
 * Der Schlüssel liegt nur in config/config.php, nie in der Datenbank.
 */
final class Crypto
{
    public static function generateKey(): string
    {
        return base64_encode(random_bytes(32));
    }

    private static function key(string $purpose): string
    {
        $master = base64_decode((string)App::config('app_key'), true);
        if ($master === false || strlen($master) < 32) {
            throw new \RuntimeException('app_key fehlt oder ist ungültig.');
        }
        return hash_hmac('sha256', $purpose, $master, true);
    }

    public static function encrypt(string $plain): string
    {
        $key = self::key('field-encryption');
        if (function_exists('sodium_crypto_secretbox')) {
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            return 's1:' . base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, $key));
        }
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        return 'o1:' . base64_encode($iv . $tag . $cipher);
    }

    public static function decrypt(string $payload): ?string
    {
        $key = self::key('field-encryption');
        $raw = base64_decode(substr($payload, 3), true);
        if ($raw === false) {
            return null;
        }
        if (str_starts_with($payload, 's1:') && function_exists('sodium_crypto_secretbox_open')) {
            $n = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
            $plain = sodium_crypto_secretbox_open(substr($raw, $n), substr($raw, 0, $n), $key);
            return $plain === false ? null : $plain;
        }
        if (str_starts_with($payload, 'o1:')) {
            $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
            return $plain === false ? null : $plain;
        }
        return null;
    }

    /** Schlüsselgebundener Hash, z. B. für IP-Adressen oder Recovery-Codes. */
    public static function hmac(string $purpose, string $value): string
    {
        return hash_hmac('sha256', $value, self::key($purpose));
    }
}
