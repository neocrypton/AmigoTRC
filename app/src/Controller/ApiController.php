<?php
declare(strict_types=1);

namespace Amigo\Controller;

use Amigo\Core\App;
use Amigo\Core\Auth;
use Amigo\Core\Database;
use Amigo\Core\RateLimiter;
use Amigo\Service\Catalog;

/** JSON-Endpunkte für die Spielsuche (Autovervollständigung). */
final class ApiController
{
    public function games(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: private, no-store');

        $q = query('q', 100);
        $platformId = query_int('platform', 1);
        $page = query_int('page', 1, 20) ?? 1; // Seite 2+ = „Weitere Treffer laden“ (nur TheGamesDB)
        if (mb_strlen($q) < 2) {
            $this->json(['results' => []]);
        }

        $db = App::db();
        $local = $page > 1 ? [] : $db->all(
            "SELECT g.id, g.tgdb_id, g.title, g.release_date, g.boxart_thumb, p.name AS platform, g.platform_id
             FROM {games} g LEFT JOIN {platforms} p ON p.id = g.platform_id
             WHERE g.title LIKE ? ESCAPE '!'" . ($platformId ? ' AND g.platform_id = ?' : '') . '
             ORDER BY g.title LIMIT 10',
            $platformId ? [Database::like($q), $platformId] : [Database::like($q)]
        );
        $results = [];
        $seen = [];
        foreach ($local as $g) {
            $results[] = [
                'game_id'     => (int)$g['id'],
                'tgdb_id'     => $g['tgdb_id'] ? (int)$g['tgdb_id'] : null,
                'title'       => $g['title'],
                'platform'    => $g['platform'],
                'platform_id' => $g['platform_id'] ? (int)$g['platform_id'] : null,
                'year'        => $g['release_date'] ? substr($g['release_date'], 0, 4) : null,
                'thumb'       => tgdb_image($g['boxart_thumb']),
            ];
            if ($g['tgdb_id']) {
                $seen[(int)$g['tgdb_id']] = true;
            }
        }

        $tgdb = Catalog::tgdb();
        $error = null;
        $more = false;
        if ($tgdb->enabled()) {
            if (RateLimiter::tooMany('api:' . Auth::id(), 40, 60)) {
                $this->json(['results' => $results, 'error' => 'Zu viele Suchanfragen – kurz warten.']);
            }
            RateLimiter::hit('api:' . Auth::id());
            $platformTgdb = $platformId ? $db->value('SELECT tgdb_id FROM {platforms} WHERE id = ?', [$platformId]) : null;
            try {
                $platformNames = array_column($db->all('SELECT tgdb_id, name, id FROM {platforms} WHERE tgdb_id IS NOT NULL'), null, 'tgdb_id');
                $found = $tgdb->searchPage($q, $platformTgdb ? (int)$platformTgdb : null, $page);
                $more = $found['more'];
                foreach ($found['games'] as $g) {
                    if (isset($seen[$g['tgdb_id']])) {
                        continue;
                    }
                    $p = $platformNames[$g['platform_tgdb_id']] ?? null;
                    $results[] = [
                        'game_id'     => null,
                        'tgdb_id'     => $g['tgdb_id'],
                        'title'       => $g['title'],
                        'platform'    => $p['name'] ?? ('TGDB #' . $g['platform_tgdb_id']),
                        'platform_id' => $p ? (int)$p['id'] : null,
                        'year'        => $g['release_date'] ? substr($g['release_date'], 0, 4) : null,
                        'thumb'       => tgdb_image($g['boxart_thumb']),
                    ];
                }
            } catch (\Throwable $e) {
                $error = 'TheGamesDB nicht erreichbar.';
            }
        }

        $this->json(['results' => $results, 'error' => $error, 'page' => $page, 'more' => $more]);
    }

    /** Live-Vorschläge aus dem gemeinsamen Zubehör-Katalog. */
    public function accessories(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: private, no-store');
        $q = query('q', 100);
        if (mb_strlen($q) < 2) {
            $this->json(['results' => []]);
        }
        $results = [];
        foreach (\Amigo\Service\AccessoryCatalog::search($q, query_int('platform', 1)) as $a) {
            $results[] = [
                'accessory_id' => (int)$a['id'],
                'title'        => $a['name'],
                'platform'     => $a['platform_name'],
                'platform_id'  => $a['platform_id'] ? (int)$a['platform_id'] : null,
                'year'         => $a['manufacturer'],                 // wird als Zusatzzeile angezeigt
                'uses'         => (int)$a['uses'],
                'thumb'        => \Amigo\Service\AccessoryCatalog::imageUrl($a),
            ];
        }
        $this->json(['results' => $results, 'more' => false]);
    }

    private function json(array $data): never
    {
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
        exit;
    }
}
