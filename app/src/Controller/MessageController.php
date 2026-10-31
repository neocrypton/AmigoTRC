<?php
declare(strict_types=1);

namespace Amigo\Controller;

use Amigo\Core\App;
use Amigo\Core\Auth;
use Amigo\Core\Database;
use Amigo\Core\RateLimiter;
use Amigo\Core\View;
use Amigo\Service\Messages;

/** Private Nachrichten: Posteingang, neue Unterhaltung, Unterhaltung lesen/antworten, Teilnehmer verwalten. */
final class MessageController
{
    private const PER_PAGE = 20;

    public function __construct()
    {
        if (!Messages::enabled()) {
            http_response_code(404);
            View::render('errors/error', ['title' => 'AUS', 'code' => 'PN', 'message' => 'Private Nachrichten sind auf dieser Installation nicht aktiv.']);
            exit;
        }
    }

    public function index(): void
    {
        $uid = Auth::id();
        if (is_post()) {
            $ids = array_values(array_unique(array_filter(array_map('intval', is_array($_POST['ids'] ?? null) ? $_POST['ids'] : []))));
            $action = input('action', 10);
            $n = 0;
            foreach ($ids as $cid) {
                if (!Messages::find($cid, $uid)) {
                    continue;
                }
                if ($action === 'leave') {
                    Messages::setStatus($cid, $uid, 'left');
                } elseif ($action === 'read') {
                    Messages::markRead($cid, $uid);
                }
                $n++;
            }
            if ($n === 0) {
                flash('info', 'Keine Unterhaltung ausgewählt.');
            } else {
                flash('ok', $action === 'leave' ? $n . ' Unterhaltung(en) gelöscht – du bist ausgetreten.' : $n . ' Unterhaltung(en) als gelesen markiert.');
            }
            redirect('messages');
        }
        $total = Messages::inboxCount($uid);
        $pages = max(1, (int)ceil($total / self::PER_PAGE));
        $page = min(query_int('page', 1, 100000) ?? 1, $pages);
        View::render('messages/index', [
            'title'         => 'Nachrichten',
            'nav'           => 'messages',
            'conversations' => Messages::inbox($uid, self::PER_PAGE, ($page - 1) * self::PER_PAGE),
            'total'         => $total,
            'page'          => $page,
            'pages'         => $pages,
        ]);
    }

    public function compose(): void
    {
        $uid = Auth::id();
        $data = ['to' => query('to', 200), 'subject' => '', 'body' => ''];
        $errors = [];
        if (is_post()) {
            $data = ['to' => input('to', 1000), 'subject' => input('subject', Messages::SUBJECT_MAX), 'body' => input('body', Messages::BODY_MAX)];
            [$recipients, $errors] = Messages::resolveRecipients($data['to'], $uid);
            if (!$recipients && !$errors) {
                $errors[] = 'Bitte mindestens einen Empfänger angeben.';
            }
            if (count($recipients) + 1 > Messages::MAX_PARTICIPANTS) {
                $errors[] = 'Maximal ' . Messages::MAX_PARTICIPANTS . ' Teilnehmer pro Unterhaltung (inklusive dir).';
            }
            if ($data['subject'] === '') {
                $errors[] = 'Bitte einen Betreff angeben.';
            }
            if ($data['body'] === '') {
                $errors[] = 'Bitte eine Nachricht schreiben.';
            }
            if (!$errors && RateLimiter::tooMany('pm-new:' . $uid, 20, 3600)) {
                $errors[] = 'Du hast in letzter Zeit sehr viele Unterhaltungen gestartet – bitte später erneut.';
            }
            if (!$errors) {
                RateLimiter::hit('pm-new:' . $uid);
                $cid = Messages::create($uid, array_keys($recipients), $data['subject'], $data['body']);
                flash('ok', 'Nachricht gesendet.');
                redirect('message', ['id' => $cid]);
            }
        }
        View::render('messages/compose', ['title' => 'Neue Nachricht', 'nav' => 'messages', 'data' => $data, 'errors' => $errors]);
    }

    public function show(): void
    {
        $uid = Auth::id();
        $conv = $this->findOrFail(query_int('id', 1));
        $lastRead = (int)$conv['last_read_id'];
        Messages::markRead((int)$conv['id'], $uid);
        View::render('messages/show', [
            'title'        => $conv['subject'],
            'nav'          => 'messages',
            'conv'         => $conv,
            'messages'     => Messages::messages((int)$conv['id']),
            'participants' => Messages::participants((int)$conv['id']),
            'isCreator'    => (int)$conv['creator_id'] === $uid,
            'lastRead'     => $lastRead,
            'max'          => Messages::MAX_PARTICIPANTS,
        ]);
    }

    public function reply(): void
    {
        $uid = Auth::id();
        $conv = $this->findOrFail(input_int('id', 1));
        $body = input('body', Messages::BODY_MAX);
        if ($body === '') {
            flash('error', 'Die Nachricht ist leer.');
        } elseif (Messages::activeCount((int)$conv['id']) < 2) {
            flash('error', 'Alle anderen haben die Unterhaltung verlassen – lade jemanden ein oder starte eine neue.');
        } elseif (RateLimiter::tooMany('pm-msg:' . $uid, 120, 3600)) {
            flash('error', 'Zu viele Nachrichten in kurzer Zeit – bitte etwas warten.');
        } else {
            RateLimiter::hit('pm-msg:' . $uid);
            Messages::reply((int)$conv['id'], $uid, $body);
            flash('ok', 'Antwort gesendet.');
        }
        header('Location: ' . url('message', ['id' => $conv['id']]) . '#ende', true, 303);
        exit;
    }

    /** Teilnehmer hinzufügen/entfernen (Ersteller), austreten, eigene Nachrichten löschen. */
    public function action(): void
    {
        $uid = Auth::id();
        $conv = $this->findOrFail(input_int('id', 1));
        $cid = (int)$conv['id'];
        $isCreator = (int)$conv['creator_id'] === $uid;

        switch (input('action', 20)) {
            case 'leave':
                Messages::setStatus($cid, $uid, 'left');
                flash('ok', 'Du hast die Unterhaltung „' . $conv['subject'] . '“ verlassen.');
                redirect('messages');
            case 'delete_messages':
                $ids = is_array($_POST['msg'] ?? null) ? $_POST['msg'] : [];
                $n = Messages::deleteOwnMessages($cid, $uid, $ids);
                flash($n ? 'ok' : 'info', $n ? $n . ' Nachricht(en) gelöscht.' : 'Keine eigene Nachricht ausgewählt.');
                if (!App::db()->value('SELECT id FROM {conversations} WHERE id = ?', [$cid])) {
                    redirect('messages');
                }
                break;
            case 'add':
                if (!$isCreator) {
                    break;
                }
                [$users, $errors] = Messages::resolveRecipients(input('to', 1000), $uid);
                foreach ($errors as $err) {
                    flash('error', $err);
                }
                $active = Messages::activeCount($cid);
                $new = array_filter(array_keys($users), fn ($id) => !App::db()->value(
                    "SELECT 1 FROM {conversation_users} WHERE conversation_id = ? AND user_id = ? AND status = 'active'", [$cid, $id]
                ));
                if ($active + count($new) > Messages::MAX_PARTICIPANTS) {
                    flash('error', 'Maximal ' . Messages::MAX_PARTICIPANTS . ' Teilnehmer pro Unterhaltung.');
                } elseif ($new) {
                    $n = Messages::addParticipants($cid, $new);
                    flash('ok', $n . ' Teilnehmer hinzugefügt.');
                }
                break;
            case 'remove':
                $target = input_int('user', 1);
                if ($isCreator && $target && $target !== $uid) {
                    Messages::setStatus($cid, $target, 'removed');
                    flash('ok', 'Teilnehmer entfernt.');
                }
                break;
        }
        redirect('message', ['id' => $cid]);
    }

    /** Benutzernamen-Vorschläge für das Empfängerfeld. */
    public function users(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: private, no-store');
        $q = query('q', 40);
        $rows = mb_strlen($q) < 1 ? [] : App::db()->all(
            "SELECT username FROM {users} WHERE is_active = 1 AND id <> ? AND username LIKE ? ESCAPE '!' ORDER BY username LIMIT 8",
            [Auth::id(), ltrim(Database::like($q), '%')] // Anfang des Namens („an“ → „anna“)
        );
        echo json_encode(['users' => array_column($rows, 'username')], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
        exit;
    }

    private function findOrFail(?int $id): array
    {
        $conv = $id ? Messages::find($id, Auth::id()) : null;
        if (!$conv) {
            http_response_code(404);
            View::render('errors/error', ['title' => '404', 'code' => 404, 'message' => 'Diese Unterhaltung gibt es nicht (mehr) oder du bist kein Teilnehmer.']);
            exit;
        }
        return $conv;
    }
}
