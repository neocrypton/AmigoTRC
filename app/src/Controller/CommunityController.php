<?php
declare(strict_types=1);

namespace Amigo\Controller;

use Amigo\Core\App;
use Amigo\Core\Auth;
use Amigo\Core\Database;
use Amigo\Core\View;

/**
 * Community: Nutzerliste und Profile. Sammlung und Durchgespielt-Liste anderer
 * Nutzer sind nur sichtbar, wenn sie diese selbst als öffentlich markiert haben.
 */
final class CommunityController
{
    public function index(): void
    {
        $q = query('q', 40);
        $params = [];
        $where = 'u.is_active = 1';
        if ($q !== '') {
            $where .= " AND u.username LIKE ? ESCAPE '!'";
            $params[] = Database::like($q);
        }
        // Zähler nur für freigegebene Bereiche ermitteln.
        $users = App::db()->all(
            "SELECT u.id, u.username, u.created_at, u.collection_public, u.plays_public,
                    CASE WHEN u.collection_public = 1 THEN (SELECT COUNT(*) FROM {items} i WHERE i.user_id = u.id) END AS items,
                    CASE WHEN u.plays_public = 1 THEN (SELECT COUNT(*) FROM {plays} pl WHERE pl.user_id = u.id) END AS plays
             FROM {users} u WHERE $where ORDER BY u.username",
            $params
        );
        View::render('community/index', ['title' => 'Community', 'nav' => 'community', 'users' => $users, 'q' => $q]);
    }

    public function profile(): void
    {
        $owner = $this->findOwner();
        $db = App::db();
        $uid = (int)$owner['id'];
        $self = $uid === Auth::id();

        $data = ['items' => [], 'plays' => [], 'stats' => []];
        if ((int)$owner['collection_public'] === 1) {
            $data['stats']['items'] = (int)$db->value('SELECT COUNT(*) FROM {items} WHERE user_id = ?', [$uid]);
            $data['stats']['platforms'] = (int)$db->value('SELECT COUNT(DISTINCT platform_id) FROM {items} WHERE user_id = ? AND platform_id IS NOT NULL', [$uid]);
            $data['items'] = $db->all(
                'SELECT i.id, i.title, i.kind, p.name AS platform_name, g.boxart_thumb,
                        (SELECT ph.id FROM {photos} ph WHERE ph.item_id = i.id ORDER BY ph.sort, ph.id LIMIT 1) AS photo_id
                 FROM {items} i LEFT JOIN {platforms} p ON p.id = i.platform_id LEFT JOIN {games} g ON g.id = i.game_id
                 WHERE i.user_id = ? ORDER BY i.created_at DESC, i.id DESC LIMIT 10',
                [$uid]
            );
        }
        if ((int)$owner['plays_public'] === 1) {
            $data['stats']['plays'] = (int)$db->value('SELECT COUNT(*) FROM {plays} WHERE user_id = ?', [$uid]);
            $data['stats']['minutes'] = (int)$db->value('SELECT COALESCE(SUM(playtime_minutes), 0) FROM {plays} WHERE user_id = ?', [$uid]);
            $data['plays'] = $db->all(
                'SELECT pl.title, pl.finished_on, pl.rating, p.name AS platform
                 FROM {plays} pl LEFT JOIN {platforms} p ON p.id = pl.platform_id
                 WHERE pl.user_id = ? ORDER BY pl.finished_on IS NULL, pl.finished_on DESC, pl.id DESC LIMIT 5',
                [$uid]
            );
        }

        View::render('community/profile', ['title' => $owner['username'], 'nav' => 'community', 'owner' => $owner, 'self' => $self] + $data);
    }

    public function collection(): void
    {
        $owner = $this->findOwner();
        if ((int)$owner['id'] === Auth::id()) {
            redirect('collection');
        }
        $this->requirePublic($owner, 'collection_public', 'Sammlung');
        CollectionController::renderList($owner, true);
    }

    public function item(): void
    {
        $owner = $this->findOwner();
        if ((int)$owner['id'] === Auth::id()) {
            redirect('item', ['id' => query_int('id', 1)]);
        }
        $this->requirePublic($owner, 'collection_public', 'Sammlung');
        $id = query_int('id', 1);
        $item = $id ? App::db()->one('SELECT * FROM {items} WHERE id = ? AND user_id = ?', [$id, (int)$owner['id']]) : null;
        if (!$item) {
            $this->notFound('Eintrag nicht gefunden.');
        }
        CollectionController::renderItem($item, $owner, true);
    }

    public function plays(): void
    {
        $owner = $this->findOwner();
        if ((int)$owner['id'] === Auth::id()) {
            redirect('plays');
        }
        $this->requirePublic($owner, 'plays_public', 'Durchgespielt-Liste');
        PlayController::renderList($owner, true);
    }

    private function findOwner(): array
    {
        $name = query('name', 40);
        $owner = $name !== ''
            ? App::db()->one(
                'SELECT id, username, created_at, collection_public, plays_public FROM {users} WHERE LOWER(username) = LOWER(?) AND is_active = 1',
                [$name]
            )
            : null;
        if (!$owner) {
            $this->notFound('Diesen Spieler gibt es nicht.');
        }
        return $owner;
    }

    private function requirePublic(array $owner, string $flag, string $label): void
    {
        if ((int)$owner[$flag] !== 1) {
            http_response_code(403);
            View::render('errors/error', [
                'title' => 'PRIVAT', 'code' => 403,
                'message' => $owner['username'] . ' hat die ' . $label . ' nicht freigegeben.',
            ]);
            exit;
        }
    }

    private function notFound(string $message): never
    {
        http_response_code(404);
        View::render('errors/error', ['title' => '404', 'code' => 404, 'message' => $message]);
        exit;
    }
}
