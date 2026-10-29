<?php
declare(strict_types=1);

namespace Amigo\Controller;

use Amigo\Core\App;
use Amigo\Core\Auth;
use Amigo\Core\View;

final class DashboardController
{
    public function index(): void
    {
        $db = App::db();
        $uid = Auth::id();

        $stats = [
            'items'     => (int)$db->value('SELECT COUNT(*) FROM {items} WHERE user_id = ?', [$uid]),
            'games'     => (int)$db->value("SELECT COUNT(*) FROM {items} WHERE user_id = ? AND kind = 'game'", [$uid]),
            'platforms' => (int)$db->value('SELECT COUNT(DISTINCT platform_id) FROM {items} WHERE user_id = ? AND platform_id IS NOT NULL', [$uid]),
            'plays'     => (int)$db->value('SELECT COUNT(*) FROM {plays} WHERE user_id = ?', [$uid]),
            'minutes'   => (int)$db->value('SELECT COALESCE(SUM(playtime_minutes), 0) FROM {plays} WHERE user_id = ?', [$uid]),
            'value'     => (int)$db->value('SELECT COALESCE(SUM(price_cents), 0) FROM {items} WHERE user_id = ?', [$uid]),
            'rating'    => $db->value('SELECT AVG(rating) FROM {plays} WHERE user_id = ? AND rating IS NOT NULL', [$uid]),
        ];

        $recentItems = $db->all(
            'SELECT i.id, i.title, i.kind, p.name AS platform, g.boxart_thumb,
                    (SELECT ph.id FROM {photos} ph WHERE ph.item_id = i.id ORDER BY ph.sort, ph.id LIMIT 1) AS photo_id
             FROM {items} i
             LEFT JOIN {platforms} p ON p.id = i.platform_id
             LEFT JOIN {games} g ON g.id = i.game_id
             WHERE i.user_id = ? ORDER BY i.created_at DESC, i.id DESC LIMIT 6',
            [$uid]
        );

        $recentPlays = $db->all(
            'SELECT pl.id, pl.title, pl.finished_on, pl.rating, pl.playtime_minutes, p.name AS platform
             FROM {plays} pl LEFT JOIN {platforms} p ON p.id = pl.platform_id
             WHERE pl.user_id = ? ORDER BY pl.finished_on IS NULL, pl.finished_on DESC, pl.id DESC LIMIT 5',
            [$uid]
        );

        $topPlatforms = $db->all(
            'SELECT p.id, p.name, COUNT(*) AS cnt FROM {items} i JOIN {platforms} p ON p.id = i.platform_id
             WHERE i.user_id = ? GROUP BY p.id, p.name ORDER BY cnt DESC, p.name LIMIT 6',
            [$uid]
        );

        View::render('dashboard', [
            'title'        => 'Start',
            'nav'          => 'home',
            'stats'        => $stats,
            'recentItems'  => $recentItems,
            'recentPlays'  => $recentPlays,
            'topPlatforms' => $topPlatforms,
        ]);
    }
}
