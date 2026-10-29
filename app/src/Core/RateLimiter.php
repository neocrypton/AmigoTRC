<?php
declare(strict_types=1);

namespace Amigo\Core;

/** Zählt Versuche pro "Bucket" (z. B. Login je IP) in einem Zeitfenster. */
final class RateLimiter
{
    public static function tooMany(string $bucket, int $max, int $windowSeconds): bool
    {
        $count = App::db()->value(
            'SELECT COUNT(*) FROM {attempts} WHERE bucket = ? AND created_at > ?',
            [self::key($bucket), time() - $windowSeconds]
        );
        return (int)$count >= $max;
    }

    public static function hit(string $bucket): void
    {
        $db = App::db();
        $db->insert('attempts', ['bucket' => self::key($bucket), 'created_at' => time()]);
        if (random_int(1, 50) === 1) {
            $db->run('DELETE FROM {attempts} WHERE created_at < ?', [time() - 86400]);
        }
    }

    public static function clear(string $bucket): void
    {
        App::db()->delete('attempts', ['bucket' => self::key($bucket)]);
    }

    /** Buckets werden gehasht gespeichert (keine Klartext-IP in der Datenbank). */
    private static function key(string $bucket): string
    {
        [$type] = explode(':', $bucket, 2);
        return substr($type, 0, 20) . ':' . substr(Crypto::hmac('ratelimit', $bucket), 0, 64);
    }

    public static function ip(): string
    {
        return (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }
}
