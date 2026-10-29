<?php
declare(strict_types=1);

namespace Amigo\Controller;

use Amigo\Core\App;
use Amigo\Core\Auth;
use Amigo\Core\Database;
use Amigo\Core\View;

final class PlatformController
{
    public function index(): void
    {
        $q = query('q', 100);
        $mine = query('mine', 1) === '1';
        $params = [Auth::id(), Auth::id()];
        $where = [];
        if ($q !== '') {
            $where[] = "(p.name LIKE ? ESCAPE '!' OR p.manufacturer LIKE ? ESCAPE '!' OR p.alias LIKE ? ESCAPE '!')";
            $like = Database::like($q);
            array_push($params, $like, $like, $like);
        }
        $sql = 'SELECT p.id, p.name, p.manufacturer, p.icon, p.tgdb_id,
                       (SELECT COUNT(*) FROM {items} i WHERE i.platform_id = p.id AND i.user_id = ?) AS items,
                       (SELECT COUNT(*) FROM {plays} pl WHERE pl.platform_id = p.id AND pl.user_id = ?) AS plays
                FROM {platforms} p' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY p.name';
        $platforms = App::db()->all($sql, $params);
        if ($mine) {
            $platforms = array_values(array_filter($platforms, fn ($p) => $p['items'] > 0 || $p['plays'] > 0));
        }

        View::render('platforms/index', [
            'title'     => 'Plattformen',
            'nav'       => 'platforms',
            'platforms' => $platforms,
            'q'         => $q,
            'mine'      => $mine,
        ]);
    }

    public function show(): void
    {
        $db = App::db();
        $platform = $db->one('SELECT * FROM {platforms} WHERE id = ?', [query_int('id', 1) ?? 0]);
        if (!$platform) {
            http_response_code(404);
            View::render('errors/error', ['title' => '404', 'code' => 404, 'message' => 'Plattform nicht gefunden.']);
            return;
        }
        $counts = [
            'items' => (int)$db->value('SELECT COUNT(*) FROM {items} WHERE platform_id = ? AND user_id = ?', [$platform['id'], Auth::id()]),
            'plays' => (int)$db->value('SELECT COUNT(*) FROM {plays} WHERE platform_id = ? AND user_id = ?', [$platform['id'], Auth::id()]),
        ];
        View::render('platforms/show', [
            'title'    => $platform['name'],
            'nav'      => 'platforms',
            'platform' => $platform,
            'counts'   => $counts,
        ]);
    }
}
