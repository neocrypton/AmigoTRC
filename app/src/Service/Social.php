<?php
declare(strict_types=1);

namespace Amigo\Service;

use Amigo\Core\App;
use Amigo\Core\Database;

/**
 * Freunde, Blockieren, Likes für Sammlungen und Online-Status.
 *
 * - Freunde und Likes gibt es nur im Community-Modus (öffentliche Registrierung offen,
 *   wie bei den privaten Nachrichten). Blockieren wirkt immer.
 * - Freundschaftsanfragen nur an Nutzer, die sie zulassen (Standard: aus).
 * - Blockieren (in beide Richtungen wirksam) beendet Freundschaften/Anfragen, entfernt Likes
 *   und verhindert Sichtbarkeit, Nachrichten, Likes und Anfragen.
 */
final class Social
{
    public const ONLINE_SECONDS = 300;   // „online“ = in den letzten 5 Minuten aktiv
    private const TOUCH_SECONDS = 60;    // Online-Zeitstempel höchstens einmal pro Minute schreiben

    /** @var array<int, int[]> */
    private static array $blockCache = [];

    public static function enabled(): bool
    {
        return Messages::enabled();
    }

    // ------------------------------------------------------------------ Online-Status

    public static function touch(array $user): void
    {
        if (time() - (int)($user['last_seen_at'] ?? 0) >= self::TOUCH_SECONDS) {
            App::db()->update('users', ['last_seen_at' => time()], ['id' => (int)$user['id']]);
        }
    }

    public static function isOnline(array $user): bool
    {
        return time() - (int)($user['last_seen_at'] ?? 0) <= self::ONLINE_SECONDS;
    }

    // ------------------------------------------------------------------ Blockieren

    /** IDs aller Nutzer, die $uid blockiert hat oder von denen $uid blockiert wurde. */
    public static function blockedIds(int $uid): array
    {
        if (!isset(self::$blockCache[$uid])) {
            $ids = [];
            foreach (App::db()->all('SELECT blocked_id AS id FROM {user_blocks} WHERE user_id = ? UNION SELECT user_id AS id FROM {user_blocks} WHERE blocked_id = ?', [$uid, $uid]) as $r) {
                $ids[(int)$r['id']] = (int)$r['id'];
            }
            self::$blockCache[$uid] = array_values($ids);
        }
        return self::$blockCache[$uid];
    }

    public static function isBlocked(int $a, int $b): bool
    {
        return $a !== $b && in_array($b, self::blockedIds($a), true);
    }

    /** Hat $uid den Nutzer $other selbst blockiert? */
    public static function hasBlocked(int $uid, int $other): bool
    {
        return (int)App::db()->value('SELECT COUNT(*) FROM {user_blocks} WHERE user_id = ? AND blocked_id = ?', [$uid, $other]) > 0;
    }

    public static function block(int $uid, int $target): void
    {
        if ($uid === $target) {
            return;
        }
        App::db()->transaction(function (Database $db) use ($uid, $target) {
            if (!self::hasBlocked($uid, $target)) {
                $db->insert('user_blocks', ['user_id' => $uid, 'blocked_id' => $target, 'created_at' => Database::now()]);
            }
            $db->run('DELETE FROM {friendships} WHERE (requester_id = ? AND addressee_id = ?) OR (requester_id = ? AND addressee_id = ?)', [$uid, $target, $target, $uid]);
            $db->run('DELETE FROM {collection_likes} WHERE (owner_id = ? AND user_id = ?) OR (owner_id = ? AND user_id = ?)', [$uid, $target, $target, $uid]);
        });
        self::$blockCache = [];
    }

    public static function unblock(int $uid, int $target): void
    {
        App::db()->delete('user_blocks', ['user_id' => $uid, 'blocked_id' => $target]);
        self::$blockCache = [];
    }

    /** Von $uid blockierte Nutzer (für die eigene Liste). */
    public static function blockedByMe(int $uid): array
    {
        return App::db()->all(
            'SELECT u.id, u.username, u.avatar, b.created_at FROM {user_blocks} b JOIN {users} u ON u.id = b.blocked_id WHERE b.user_id = ? ORDER BY u.username',
            [$uid]
        );
    }

    /** SQL-Bedingung „Nutzer-Spalte $col ist für $uid nicht blockiert“ + Parameter. */
    public static function notBlockedSql(string $col, int $uid): array
    {
        return ["$col NOT IN (SELECT blocked_id FROM {user_blocks} WHERE user_id = ?) AND $col NOT IN (SELECT user_id FROM {user_blocks} WHERE blocked_id = ?)", [$uid, $uid]];
    }

    // ------------------------------------------------------------------ Freunde

    /** none | friends | outgoing | incoming */
    public static function friendState(int $uid, int $other): string
    {
        $row = App::db()->one(
            'SELECT requester_id, status FROM {friendships} WHERE (requester_id = ? AND addressee_id = ?) OR (requester_id = ? AND addressee_id = ?)',
            [$uid, $other, $other, $uid]
        );
        if (!$row) {
            return 'none';
        }
        if ($row['status'] === 'accepted') {
            return 'friends';
        }
        return (int)$row['requester_id'] === $uid ? 'outgoing' : 'incoming';
    }

    /** Darf $uid an $target eine Anfrage senden? Gibt null oder den Grund zurück. */
    public static function requestProblem(int $uid, array $target): ?string
    {
        $tid = (int)$target['id'];
        return match (true) {
            !self::enabled()                            => 'Freunde gibt es nur, solange die Community geöffnet ist.',
            $uid === $tid                               => 'Du kannst dir nicht selbst eine Anfrage schicken.',
            self::isBlocked($uid, $tid)                 => 'Mit diesem Nutzer ist keine Interaktion möglich.',
            (int)($target['allow_friend_requests'] ?? 0) !== 1 => $target['username'] . ' nimmt keine Freundschaftsanfragen an.',
            default                                     => null,
        };
    }

    /** Anfrage senden; liegt bereits eine Anfrage des anderen vor, wird sie angenommen. Gibt den neuen Zustand zurück. */
    public static function request(int $uid, array $target): string
    {
        $tid = (int)$target['id'];
        $state = self::friendState($uid, $tid);
        if ($state === 'incoming') {
            self::accept($uid, $tid);
            return 'friends';
        }
        if ($state === 'none') {
            App::db()->insert('friendships', ['requester_id' => $uid, 'addressee_id' => $tid, 'status' => 'pending', 'created_at' => Database::now()]);
            return 'outgoing';
        }
        return $state;
    }

    public static function accept(int $uid, int $requester): bool
    {
        if (self::isBlocked($uid, $requester)) {
            return false;
        }
        $n = App::db()->run(
            "UPDATE {friendships} SET status = 'accepted', accepted_at = ? WHERE requester_id = ? AND addressee_id = ? AND status = 'pending'",
            [Database::now(), $requester, $uid]
        )->rowCount();
        return $n > 0;
    }

    /** Anfrage ablehnen, eigene Anfrage zurückziehen oder Freundschaft beenden. */
    public static function removeRelation(int $uid, int $other): void
    {
        App::db()->run('DELETE FROM {friendships} WHERE (requester_id = ? AND addressee_id = ?) OR (requester_id = ? AND addressee_id = ?)', [$uid, $other, $other, $uid]);
    }

    /** Bestätigte Freunde von $uid (ohne blockierte), mit Online-Zeitstempel. */
    public static function friends(int $uid): array
    {
        return App::db()->all(
            "SELECT u.id, u.username, u.avatar, u.last_seen_at, f.accepted_at
             FROM {friendships} f JOIN {users} u ON u.id = CASE WHEN f.requester_id = ? THEN f.addressee_id ELSE f.requester_id END
             WHERE (f.requester_id = ? OR f.addressee_id = ?) AND f.status = 'accepted' AND u.is_active = 1
             ORDER BY u.username",
            [$uid, $uid, $uid]
        );
    }

    public static function incoming(int $uid): array
    {
        return App::db()->all(
            "SELECT u.id, u.username, u.avatar, f.created_at FROM {friendships} f JOIN {users} u ON u.id = f.requester_id
             WHERE f.addressee_id = ? AND f.status = 'pending' AND u.is_active = 1 ORDER BY f.created_at DESC",
            [$uid]
        );
    }

    public static function outgoing(int $uid): array
    {
        return App::db()->all(
            "SELECT u.id, u.username, u.avatar, f.created_at FROM {friendships} f JOIN {users} u ON u.id = f.addressee_id
             WHERE f.requester_id = ? AND f.status = 'pending' AND u.is_active = 1 ORDER BY f.created_at DESC",
            [$uid]
        );
    }

    public static function incomingCount(int $uid): int
    {
        if (!self::enabled()) {
            return 0;
        }
        return (int)App::db()->value(
            "SELECT COUNT(*) FROM {friendships} f JOIN {users} u ON u.id = f.requester_id WHERE f.addressee_id = ? AND f.status = 'pending' AND u.is_active = 1",
            [$uid]
        );
    }

    // ------------------------------------------------------------------ Likes

    public static function likeCount(int $ownerId): int
    {
        return (int)App::db()->value('SELECT COUNT(*) FROM {collection_likes} WHERE owner_id = ?', [$ownerId]);
    }

    public static function hasLiked(int $ownerId, int $uid): bool
    {
        return (int)App::db()->value('SELECT COUNT(*) FROM {collection_likes} WHERE owner_id = ? AND user_id = ?', [$ownerId, $uid]) > 0;
    }

    /** Darf $uid die Sammlung von $owner liken? */
    public static function canLike(int $uid, array $owner): bool
    {
        $oid = (int)$owner['id'];
        return self::enabled() && $uid !== $oid
            && (int)$owner['collection_public'] === 1
            && (int)($owner['allow_likes'] ?? 1) === 1
            && !self::isBlocked($uid, $oid);
    }

    /** Like setzen bzw. zurücknehmen. Gibt true zurück, wenn danach geliked ist. */
    public static function toggleLike(int $uid, array $owner): bool
    {
        $oid = (int)$owner['id'];
        if (self::hasLiked($oid, $uid)) {
            App::db()->delete('collection_likes', ['owner_id' => $oid, 'user_id' => $uid]);
            return false;
        }
        App::db()->insert('collection_likes', ['owner_id' => $oid, 'user_id' => $uid, 'created_at' => Database::now()]);
        return true;
    }
}
