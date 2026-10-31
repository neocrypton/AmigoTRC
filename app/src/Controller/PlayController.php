<?php
declare(strict_types=1);

namespace Amigo\Controller;

use Amigo\Core\App;
use Amigo\Core\Auth;
use Amigo\Core\Database;
use Amigo\Core\View;
use Amigo\Service\Catalog;

/** "Durchgespielt"-Liste: Spiel, Plattform, Datum, Spielzeit, Punkte, Sterne + Text. */
final class PlayController
{
    private const PER_PAGE = 30;

    private const SORTS = [
        'date'     => 'pl.finished_on IS NULL, pl.finished_on DESC, pl.id DESC',
        'title'    => 'pl.title ASC',
        'rating'   => 'pl.rating IS NULL, pl.rating DESC, pl.title ASC',
        'playtime' => 'pl.playtime_minutes IS NULL, pl.playtime_minutes DESC',
        'score'    => 'pl.score IS NULL, pl.score DESC',
    ];

    public function index(): void
    {
        self::renderList(Auth::user(), false);
    }

    /** Durchgespielt-Liste eines Besitzers (eigene oder öffentliche fremde). */
    public static function renderList(array $owner, bool $foreign): void
    {
        $db = App::db();
        $filter = [
            'q'        => query('q', 100),
            'platform' => query_int('platform', 1),
            'rating'   => query_int('rating', 1, 5),
            'year'     => query_int('year', 1970, 2100),
            'sort'     => input_enum('sort', self::SORTS, $_GET) ?? 'date',
        ];
        $page = query_int('page', 1, 100000) ?? 1;

        $where = ['pl.user_id = ?'];
        $params = [(int)$owner['id']];
        if ($filter['q'] !== '') {
            $where[] = "(pl.title LIKE ? ESCAPE '!' OR pl.review LIKE ? ESCAPE '!')";
            $like = Database::like($filter['q']);
            array_push($params, $like, $like);
        }
        if ($filter['platform']) {
            $where[] = 'pl.platform_id = ?';
            $params[] = $filter['platform'];
        }
        if ($filter['rating']) {
            $where[] = 'pl.rating = ?';
            $params[] = $filter['rating'];
        }
        if ($filter['year']) {
            $where[] = 'pl.finished_on >= ? AND pl.finished_on <= ?';
            array_push($params, $filter['year'] . '-01-01', $filter['year'] . '-12-31');
        }
        $from = ' FROM {plays} pl LEFT JOIN {platforms} p ON p.id = pl.platform_id LEFT JOIN {games} g ON g.id = pl.game_id WHERE ' . implode(' AND ', $where);

        $summary = $db->one('SELECT COUNT(*) AS cnt, COALESCE(SUM(pl.playtime_minutes), 0) AS minutes, AVG(pl.rating) AS rating' . $from, $params);
        $total = (int)$summary['cnt'];
        $pages = max(1, (int)ceil($total / self::PER_PAGE));
        $page = min($page, $pages);

        $plays = $db->all(
            'SELECT pl.*, p.name AS platform_name, g.boxart_thumb' . $from
            . ' ORDER BY ' . self::SORTS[$filter['sort']] . ' LIMIT ? OFFSET ?',
            [...$params, self::PER_PAGE, ($page - 1) * self::PER_PAGE]
        );

        $years = array_map('intval', array_filter(array_column($db->all(
            'SELECT DISTINCT SUBSTR(finished_on, 1, 4) AS y FROM {plays} WHERE user_id = ? AND finished_on IS NOT NULL ORDER BY y DESC',
            [(int)$owner['id']]
        ), 'y')));

        View::render('plays/index', [
            'title'     => $foreign ? 'Durchgespielt von ' . $owner['username'] : 'Durchgespielt',
            'nav'       => $foreign ? 'community' : 'plays',
            'owner'     => $foreign ? $owner : null,
            'plays'     => $plays,
            'filter'    => $filter,
            'summary'   => $summary,
            'page'      => $page,
            'pages'     => $pages,
            'years'     => $years,
            'platforms' => Catalog::platforms(),
            'sorts'     => ['date' => 'Datum', 'title' => 'Titel', 'rating' => 'Wertung', 'playtime' => 'Spielzeit', 'score' => 'Punkte'],
        ]);
    }

    public function edit(): void
    {
        $db = App::db();
        $id = input_int('id', 1) ?? query_int('id', 1);
        $play = $id ? $this->findOwned($id) : null;
        $errors = [];

        if (is_post()) {
            [$data, $errors, $game] = $this->validate();
            if (!$errors) {
                $now = Database::now();
                if ($play) {
                    $db->update('plays', $data + ['updated_at' => $now, 'scrape_status' => null], ['id' => $play['id'], 'user_id' => Auth::id()]);
                } else {
                    $db->insert('plays', $data + ['user_id' => Auth::id(), 'created_at' => $now, 'updated_at' => $now]);
                }
                flash('ok', $play ? 'Eintrag gespeichert.' : 'STAGE CLEAR! Spiel als durchgespielt eingetragen.');
                redirect('plays');
            }
            $play = array_merge($play ?? [], $data, ['id' => $play['id'] ?? null, '_game' => $game]);
        }

        if (!$play) {
            // Vorbelegung, z. B. aus einem Sammlungs-Eintrag heraus.
            $play = ['id' => null, 'title' => '', 'game_id' => null, 'platform_id' => query_int('platform', 1), 'finished_on' => date('Y-m-d')];
            if ($gid = query_int('game', 1)) {
                $g = $db->one('SELECT * FROM {games} WHERE id = ?', [$gid]);
                if ($g) {
                    $play = ['game_id' => (int)$g['id'], 'title' => $g['title'], 'platform_id' => $g['platform_id'] ? (int)$g['platform_id'] : null] + $play;
                }
            }
        }
        if (!isset($play['_game']) && !empty($play['game_id'])) {
            $play['_game'] = $db->one('SELECT * FROM {games} WHERE id = ?', [$play['game_id']]);
        }

        View::render('plays/form', [
            'title'     => !empty($play['id']) ? 'Durchgespielt bearbeiten' : 'Durchgespielt eintragen',
            'nav'       => 'plays',
            'play'      => $play,
            'errors'    => $errors,
            'platforms' => Catalog::platforms(),
            'tgdb'      => Catalog::tgdb()->enabled(),
        ]);
    }

    public function delete(): void
    {
        if (is_post()) {
            $play = $this->findOwned(input_int('id', 1));
            App::db()->delete('plays', ['id' => $play['id'], 'user_id' => Auth::id()]);
            flash('ok', 'Eintrag gelöscht.');
        }
        redirect('plays');
    }

    private function findOwned(?int $id): array
    {
        $play = $id ? App::db()->one('SELECT * FROM {plays} WHERE id = ? AND user_id = ?', [$id, Auth::id()]) : null;
        if (!$play) {
            http_response_code(404);
            View::render('errors/error', ['title' => '404', 'code' => 404, 'message' => 'Eintrag nicht gefunden.']);
            exit;
        }
        return $play;
    }

    /** @return array{0: array, 1: string[], 2: ?array} */
    private function validate(): array
    {
        $errors = [];
        [$game, $warning] = Catalog::resolveGameFromInput();
        if ($warning) {
            flash('warn', $warning);
        }

        $hours = input_int('hours', 0, 100000);
        $minutes = input_int('minutes', 0, 59);
        $playtime = ($hours === null && $minutes === null) ? null : ($hours ?? 0) * 60 + ($minutes ?? 0);

        $scoreRaw = str_replace(['.', ' '], '', input('score', 25));
        $score = $scoreRaw === '' ? null : (preg_match('/^\d{1,15}$/', $scoreRaw) ? (int)$scoreRaw : false);

        $data = [
            'game_id'          => $game['id'] ?? null,
            'platform_id'      => input_int('platform_id', 1),
            'title'            => input('title', 255),
            'finished_on'      => input_date('finished_on'),
            'playtime_minutes' => $playtime,
            'score'            => $score === false ? null : $score,
            'rating'           => input_int('rating', 1, 5),
            'review'           => input('review', 10000) ?: null,
        ];
        if ($game) {
            $data['title'] = $data['title'] !== '' ? $data['title'] : $game['title'];
            $data['platform_id'] ??= $game['platform_id'] ? (int)$game['platform_id'] : null;
        }

        if ($data['title'] === '') {
            $errors[] = 'Bitte einen Spieltitel angeben oder aus der Suche wählen.';
        }
        if ($data['platform_id'] !== null && !Catalog::platformExists($data['platform_id'])) {
            $errors[] = 'Unbekannte Plattform.';
        }
        if (input('finished_on', 10) !== '' && $data['finished_on'] === null) {
            $errors[] = 'Das Datum ist ungültig.';
        }
        if ($score === false) {
            $errors[] = 'Punkte bitte als ganze Zahl angeben.';
        }
        return [$data, $errors, $game];
    }
}
