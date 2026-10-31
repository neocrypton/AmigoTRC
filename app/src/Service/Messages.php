<?php
declare(strict_types=1);

namespace Amigo\Service;

use Amigo\Core\App;
use Amigo\Core\Crypto;
use Amigo\Core\Database;
use Amigo\Core\Settings;

/**
 * Private Nachrichten als Unterhaltungen (ähnlich WoltLab Burning Board):
 * bis zu 10 Teilnehmer, die drin bleiben, bis der Ersteller sie entfernt oder
 * sie selbst austreten. Verlassen alle, wird die Unterhaltung gelöscht.
 *
 * Status in {conversation_users}: active | left | removed
 */
final class Messages
{
    public const MAX_PARTICIPANTS = 10;
    public const SUBJECT_MAX = 150;
    public const BODY_MAX = 5000;

    /** E-Mails, die nach dem Ausliefern der Seite verschickt werden. */
    private static array $mailQueue = [];

    /** Nur aktiv, solange die öffentliche Registrierung freigegeben ist. */
    public static function enabled(): bool
    {
        return Settings::bool(App::db(), 'registration_open');
    }

    public static function unreadCount(int $userId): int
    {
        return (int)App::db()->value(
            "SELECT COUNT(*) FROM {conversation_users} cu JOIN {conversations} c ON c.id = cu.conversation_id
             WHERE cu.user_id = ? AND cu.status = 'active' AND c.last_message_id > cu.last_read_id",
            [$userId]
        );
    }

    /** Unterhaltungen des Nutzers (nur aktive Teilnahme), neueste zuerst. */
    public static function inbox(int $userId, int $limit, int $offset): array
    {
        $db = App::db();
        $rows = $db->all(
            "SELECT c.id, c.subject, c.creator_id, c.last_message_at, c.last_message_id, cu.last_read_id,
                    (SELECT COUNT(*) FROM {messages} m WHERE m.conversation_id = c.id) AS message_count,
                    (SELECT u.username FROM {messages} m LEFT JOIN {users} u ON u.id = m.user_id WHERE m.conversation_id = c.id ORDER BY m.id DESC LIMIT 1) AS last_author
             FROM {conversation_users} cu JOIN {conversations} c ON c.id = cu.conversation_id
             WHERE cu.user_id = ? AND cu.status = 'active'
             ORDER BY c.last_message_at DESC, c.id DESC LIMIT ? OFFSET ?",
            [$userId, $limit, $offset]
        );
        if (!$rows) {
            return [];
        }
        $ids = array_column($rows, 'id');
        $people = $db->all(
            "SELECT cu.conversation_id, u.id, u.username, u.avatar FROM {conversation_users} cu JOIN {users} u ON u.id = cu.user_id
             WHERE cu.status = 'active' AND cu.conversation_id IN (" . Database::placeholders(count($ids)) . ') ORDER BY u.username',
            $ids
        );
        $byConv = [];
        foreach ($people as $p) {
            $byConv[$p['conversation_id']][] = $p;
        }
        foreach ($rows as &$r) {
            $r['participants'] = $byConv[$r['id']] ?? [];
            $r['unread'] = (int)$r['last_message_id'] > (int)$r['last_read_id'];
        }
        return $rows;
    }

    public static function inboxCount(int $userId): int
    {
        return (int)App::db()->value("SELECT COUNT(*) FROM {conversation_users} WHERE user_id = ? AND status = 'active'", [$userId]);
    }

    /** Unterhaltung, wenn der Nutzer aktiver Teilnehmer ist – sonst null. */
    public static function find(int $conversationId, int $userId): ?array
    {
        return App::db()->one(
            "SELECT c.*, cu.last_read_id FROM {conversations} c JOIN {conversation_users} cu ON cu.conversation_id = c.id
             WHERE c.id = ? AND cu.user_id = ? AND cu.status = 'active'",
            [$conversationId, $userId]
        );
    }

    public static function participants(int $conversationId): array
    {
        return App::db()->all(
            "SELECT u.id, u.username, u.avatar, cu.status, cu.joined_at FROM {conversation_users} cu JOIN {users} u ON u.id = cu.user_id
             WHERE cu.conversation_id = ? ORDER BY cu.status, u.username",
            [$conversationId]
        );
    }

    public static function messages(int $conversationId): array
    {
        return App::db()->all(
            'SELECT m.id, m.user_id, m.body, m.created_at, u.username, u.avatar FROM {messages} m LEFT JOIN {users} u ON u.id = m.user_id
             WHERE m.conversation_id = ? ORDER BY m.id',
            [$conversationId]
        );
    }

    /**
     * Wandelt eine Empfängerliste („anna, bob; carl“) in aktive Nutzer um.
     * @return array{0: array<int, array>, 1: string[]} [gefundene Nutzer, Fehler]
     */
    public static function resolveRecipients(string $list, int $senderId): array
    {
        $names = array_values(array_unique(array_filter(array_map('trim', preg_split('/[,;\s]+/', $list) ?: []))));
        $found = [];
        $errors = [];
        foreach (array_slice($names, 0, 50) as $name) {
            $u = App::db()->one('SELECT id, username FROM {users} WHERE LOWER(username) = LOWER(?) AND is_active = 1', [$name]);
            if (!$u) {
                $errors[] = 'Unbekannter Empfänger: ' . mb_substr($name, 0, 40);
            } elseif ((int)$u['id'] !== $senderId) {
                $found[(int)$u['id']] = $u;
            }
        }
        return [$found, $errors];
    }

    /** Neue Unterhaltung anlegen. Gibt die ID zurück. */
    public static function create(int $senderId, array $recipientIds, string $subject, string $body): int
    {
        return App::db()->transaction(function (Database $db) use ($senderId, $recipientIds, $subject, $body) {
            $now = Database::now();
            $cid = $db->insert('conversations', ['subject' => $subject, 'creator_id' => $senderId, 'created_at' => $now, 'last_message_at' => $now]);
            $mid = $db->insert('messages', ['conversation_id' => $cid, 'user_id' => $senderId, 'body' => $body, 'created_at' => $now]);
            $db->update('conversations', ['last_message_id' => $mid], ['id' => $cid]);
            $db->insert('conversation_users', ['conversation_id' => $cid, 'user_id' => $senderId, 'status' => 'active', 'last_read_id' => $mid, 'joined_at' => $now]);
            foreach ($recipientIds as $rid) {
                $db->insert('conversation_users', ['conversation_id' => $cid, 'user_id' => (int)$rid, 'status' => 'active', 'last_read_id' => 0, 'joined_at' => $now]);
            }
            self::notify($cid, $mid, $senderId);
            return $cid;
        });
    }

    public static function reply(int $conversationId, int $userId, string $body): void
    {
        $db = App::db();
        $db->transaction(function (Database $db) use ($conversationId, $userId, $body) {
            $now = Database::now();
            $mid = $db->insert('messages', ['conversation_id' => $conversationId, 'user_id' => $userId, 'body' => $body, 'created_at' => $now]);
            $db->update('conversations', ['last_message_at' => $now, 'last_message_id' => $mid], ['id' => $conversationId]);
            $db->update('conversation_users', ['last_read_id' => $mid], ['conversation_id' => $conversationId, 'user_id' => $userId]);
            self::notify($conversationId, $mid, $userId);
        });
    }

    public static function markRead(int $conversationId, int $userId): void
    {
        App::db()->run(
            'UPDATE {conversation_users} SET last_read_id = (SELECT last_message_id FROM {conversations} WHERE id = ?) WHERE conversation_id = ? AND user_id = ?',
            [$conversationId, $conversationId, $userId]
        );
    }

    public static function activeCount(int $conversationId): int
    {
        return (int)App::db()->value("SELECT COUNT(*) FROM {conversation_users} WHERE conversation_id = ? AND status = 'active'", [$conversationId]);
    }

    /** Teilnehmer hinzufügen (nur Ersteller). Gibt Anzahl hinzugefügter zurück. */
    public static function addParticipants(int $conversationId, array $userIds, int $byUserId = 0): int
    {
        $db = App::db();
        $added = 0;
        foreach ($userIds as $uid) {
            $row = $db->one('SELECT status FROM {conversation_users} WHERE conversation_id = ? AND user_id = ?', [$conversationId, $uid]);
            if ($row && $row['status'] === 'active') {
                continue;
            }
            if ($row) {
                // Wieder einladen (nach Austritt/Entfernen)
                $db->update('conversation_users', ['status' => 'active', 'last_read_id' => 0, 'joined_at' => Database::now()], ['conversation_id' => $conversationId, 'user_id' => $uid]);
            } else {
                $db->insert('conversation_users', ['conversation_id' => $conversationId, 'user_id' => $uid, 'status' => 'active', 'last_read_id' => 0, 'joined_at' => Database::now()]);
            }
            $added++;
        }
        if ($added) {
            // Hinzugefügte erhalten (falls gewünscht) eine Mail, auch wenn ihr letzter Besuch Mails blockieren würde.
            $db->run(
                'UPDATE {conversation_users} SET notified_id = 0 WHERE conversation_id = ? AND user_id IN (' . Database::placeholders(count($userIds)) . ')',
                [$conversationId, ...array_map('intval', $userIds)]
            );
            $last = (int)$db->value('SELECT last_message_id FROM {conversations} WHERE id = ?', [$conversationId]);
            self::notify($conversationId, $last, $byUserId, array_map('intval', $userIds));
        }
        return $added;
    }

    /** Teilnehmer entfernen (Ersteller) bzw. selbst austreten. Leere Unterhaltungen werden gelöscht. */
    public static function setStatus(int $conversationId, int $userId, string $status): void
    {
        App::db()->update('conversation_users', ['status' => $status], ['conversation_id' => $conversationId, 'user_id' => $userId]);
        self::deleteIfEmpty($conversationId);
    }

    /** Eigene Nachrichten löschen. Gibt Anzahl gelöschter zurück. */
    public static function deleteOwnMessages(int $conversationId, int $userId, array $messageIds): int
    {
        $db = App::db();
        $ids = array_values(array_unique(array_filter(array_map('intval', $messageIds))));
        if (!$ids) {
            return 0;
        }
        $n = $db->run(
            'DELETE FROM {messages} WHERE conversation_id = ? AND user_id = ? AND id IN (' . Database::placeholders(count($ids)) . ')',
            [$conversationId, $userId, ...$ids]
        )->rowCount();
        $last = $db->one('SELECT id, created_at FROM {messages} WHERE conversation_id = ? ORDER BY id DESC LIMIT 1', [$conversationId]);
        if ($last === null) {
            $db->delete('conversations', ['id' => $conversationId]);
        } else {
            $db->update('conversations', ['last_message_at' => $last['created_at'], 'last_message_id' => (int)$last['id']], ['id' => $conversationId]);
        }
        return $n;
    }

    /**
     * Merkt E-Mails für Teilnehmer vor, die das wünschen.
     * Pro Unterhaltung gibt es nur eine Mail, bis der Empfänger sie wieder gelesen hat
     * (notified_id <= last_read_id). Nur an bestätigte Adressen.
     */
    public static function notify(int $conversationId, int $messageId, int $senderId, ?array $onlyUserIds = null): void
    {
        if (!Mailer::configured()) {
            return;
        }
        $db = App::db();
        $rows = $db->all(
            "SELECT u.id, u.username, u.email, cu.notified_id, cu.last_read_id FROM {conversation_users} cu JOIN {users} u ON u.id = cu.user_id
             WHERE cu.conversation_id = ? AND cu.status = 'active' AND u.id <> ? AND u.is_active = 1
               AND u.pm_notify = 1 AND u.email IS NOT NULL AND u.email_verified_at IS NOT NULL
               AND cu.notified_id <= cu.last_read_id",
            [$conversationId, $senderId]
        );
        if (!$rows) {
            return;
        }
        $conv = $db->one('SELECT subject FROM {conversations} WHERE id = ?', [$conversationId]);
        $sender = (string)($db->value('SELECT username FROM {users} WHERE id = ?', [$senderId]) ?? 'Jemand');
        $site = (string)App::config('site_name', 'AMIGO!');
        $base = Accounts::baseUrl();
        foreach ($rows as $r) {
            if ($onlyUserIds !== null && !in_array((int)$r['id'], $onlyUserIds, true)) {
                continue;
            }
            $db->update('conversation_users', ['notified_id' => $messageId], ['conversation_id' => $conversationId, 'user_id' => $r['id']]);
            self::$mailQueue[] = [
                (string)$r['email'],
                $site . ': Neue Nachricht von ' . $sender . ' – ' . mb_strimwidth((string)$conv['subject'], 0, 60, '…'),
                implode("\n", [
                    'Hallo ' . $r['username'] . ',',
                    '',
                    $sender . ' hat dir in der Unterhaltung „' . $conv['subject'] . '“ geschrieben.',
                    '',
                    'Lesen und antworten:',
                    $base . 'index.php?' . http_build_query(['r' => 'message', 'id' => $conversationId]),
                    '',
                    'Weitere Nachrichten in dieser Unterhaltung lösen keine neue E-Mail aus, bis du sie gelesen hast.',
                    '',
                    'Keine E-Mails mehr zu privaten Nachrichten:',
                    self::unsubscribeUrl((int)$r['id']),
                    '',
                    '– ' . $site,
                ]),
            ];
        }
        static $registered = false;
        if (self::$mailQueue && !$registered) {
            $registered = true;
            register_shutdown_function([self::class, 'flushMail']);
        }
    }

    /** Verschickt vorgemerkte Mails – nach Möglichkeit erst, nachdem die Seite ausgeliefert ist. */
    public static function flushMail(): void
    {
        if (!self::$mailQueue) {
            return;
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        @set_time_limit(120);
        foreach (self::$mailQueue as [$to, $subject, $text]) {
            try {
                Mailer::send($to, $subject, $text);
            } catch (\Throwable $e) {
                error_log('AMIGO: PN-Benachrichtigung an ' . $to . ' fehlgeschlagen: ' . $e->getMessage());
            }
        }
        self::$mailQueue = [];
    }

    public static function unsubscribeToken(int $userId): string
    {
        return substr(Crypto::hmac('pm-unsubscribe', (string)$userId), 0, 32);
    }

    public static function unsubscribeUrl(int $userId): string
    {
        return Accounts::baseUrl() . 'index.php?' . http_build_query(['r' => 'pm/unsubscribe', 'u' => $userId, 't' => self::unsubscribeToken($userId)]);
    }

    public static function deleteIfEmpty(int $conversationId): void
    {
        if (self::activeCount($conversationId) === 0) {
            App::db()->delete('conversations', ['id' => $conversationId]);
        }
    }

    /** Unterhaltungen ohne aktive Teilnehmer entfernen (z. B. nach dem Löschen eines Nutzers). */
    public static function cleanup(): void
    {
        App::db()->run(
            "DELETE FROM {conversations} WHERE NOT EXISTS
             (SELECT 1 FROM {conversation_users} cu WHERE cu.conversation_id = {conversations}.id AND cu.status = 'active')"
        );
    }
}
