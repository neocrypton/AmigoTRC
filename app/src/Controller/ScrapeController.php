<?php
declare(strict_types=1);

namespace Amigo\Controller;

use Amigo\Core\App;
use Amigo\Core\Auth;
use Amigo\Core\View;
use Amigo\Service\Catalog;
use Amigo\Service\Scraper;

/** Cover & Daten nachträglich von TheGamesDB holen – einzeln oder für alle offenen Einträge. */
final class ScrapeController
{
    private const LABELS = ['collection' => 'Sammlung', 'plays' => 'Durchgespielt-Liste'];
    private const BATCH = 8;
    private const SECONDS = 20.0;

    public function index(): void
    {
        $target = $this->target();
        $table = Scraper::TARGETS[$target];
        $uid = Auth::id();
        $open = App::db()->all(
            "SELECT r.id, r.title, r.scrape_status, p.name AS platform FROM {{$table}} r
             LEFT JOIN {platforms} p ON p.id = r.platform_id
             WHERE " . Scraper::openWhere($target, 'r') . "
               AND r.scrape_status IN ('review', 'none')
             ORDER BY r.scrape_status DESC, r.title LIMIT 200",
            [$uid]
        );
        View::render('scrape/index', [
            'title'     => 'Cover & Daten ergänzen',
            'nav'       => $target === 'collection' ? 'collection' : 'plays',
            'target'    => $target,
            'label'     => self::LABELS[$target],
            'counts'    => Scraper::counts($uid, $target),
            'open'      => $open,
            'enabled'   => Catalog::tgdb()->enabled(),
            'allowance' => Scraper::allowance($uid),
            'batch'     => self::BATCH,
        ]);
    }

    /** Einen Stapel abarbeiten. Antwortet mit JSON (Fortschrittsanzeige) oder leitet zurück (ohne JS). */
    public function run(): void
    {
        $target = $this->target();
        $json = str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
        if (!is_post()) {
            redirect('scrape', ['target' => $target]);
        }
        if (!Catalog::tgdb()->enabled()) {
            $this->respond($json, $target, ['error' => 'Kein TheGamesDB-API-Key verfügbar.']);
        }
        @set_time_limit(60);
        $stats = Scraper::runBatch(Auth::id(), $target, self::BATCH, self::SECONDS);
        $this->respond($json, $target, $stats);
    }

    public function reset(): void
    {
        $target = $this->target();
        if (is_post()) {
            Scraper::resetStatus(Auth::id(), $target);
            flash('ok', 'Offene Einträge werden beim nächsten Durchlauf erneut gesucht.');
        }
        redirect('scrape', ['target' => $target]);
    }

    /** Einzelnen Eintrag zuordnen: Treffer anzeigen, auswählen, verknüpfen. */
    public function item(): void
    {
        $target = $this->target();
        $table = Scraper::TARGETS[$target];
        $id = input_int('id', 1) ?? query_int('id', 1);
        $record = $id ? App::db()->one("SELECT * FROM {{$table}} WHERE id = ? AND user_id = ?", [$id, Auth::id()]) : null;
        if (!$record) {
            http_response_code(404);
            View::render('errors/error', ['title' => '404', 'code' => 404, 'message' => 'Eintrag nicht gefunden.']);
            return;
        }
        $back = $target === 'collection' ? url('item', ['id' => $record['id']]) : url('play/edit', ['id' => $record['id']]);

        if (is_post()) {
            $tgdbId = input_int('tgdb_id', 1);
            try {
                $game = $tgdbId ? Scraper::link($target, $record, $tgdbId, isset($_POST['adopt_title'])) : null;
                if ($game) {
                    flash('ok', 'Verknüpft mit „' . $game['title'] . '“ – Cover und Spieldaten übernommen.');
                    header('Location: ' . $back, true, 303);
                    exit;
                }
                flash('error', 'Das Spiel wurde bei TheGamesDB nicht gefunden.');
            } catch (\Throwable) {
                flash('error', 'TheGamesDB ist gerade nicht erreichbar.');
            }
            redirect('scrape/item', ['target' => $target, 'id' => $record['id']]);
        }

        $q = query('q', 100) ?: (string)$record['title'];
        $platformId = array_key_exists('platform', $_GET) ? query_int('platform', 1) : ($record['platform_id'] ? (int)$record['platform_id'] : null);
        $results = [];
        $error = null;
        $enabled = Catalog::tgdb()->enabled();
        if ($enabled) {
            try {
                $results = Scraper::candidates($q, Scraper::platformTgdb($platformId));
            } catch (\Throwable) {
                $error = 'TheGamesDB ist gerade nicht erreichbar.';
            }
        }
        View::render('scrape/item', [
            'title'      => 'Bei TheGamesDB suchen',
            'nav'        => $target === 'collection' ? 'collection' : 'plays',
            'target'     => $target,
            'record'     => $record,
            'q'          => $q,
            'platformId' => $platformId,
            'platforms'  => Catalog::platforms(),
            'results'    => $results,
            'error'      => $error,
            'enabled'    => $enabled,
            'back'       => $back,
            'want'       => Scraper::normalize((string)$record['title']),
        ]);
    }

    private function target(): string
    {
        $t = input('target', 12) ?: query('target', 12);
        return array_key_exists($t, Scraper::TARGETS) ? $t : 'collection';
    }

    private function respond(bool $json, string $target, array $data): never
    {
        if ($json) {
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
            echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
            exit;
        }
        if (isset($data['error'])) {
            flash('error', $data['error']);
        } else {
            flash($data['stopped'] ? 'warn' : 'ok', sprintf(
                '%d geprüft: %d verknüpft, %d zum Prüfen, %d ohne Treffer. Noch offen: %d.%s',
                $data['processed'], $data['linked'], $data['review'], $data['none'], $data['remaining'],
                $data['stopped'] ? ' ' . $data['stopped'] : ''
            ));
        }
        redirect('scrape', ['target' => $target]);
    }
}
