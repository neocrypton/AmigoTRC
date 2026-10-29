<?php
declare(strict_types=1);

namespace Amigo\Controller;

use Amigo\Core\App;
use Amigo\Core\Auth;
use Amigo\Core\Database;
use Amigo\Core\Options;
use Amigo\Core\View;
use Amigo\Service\Catalog;
use Amigo\Service\ImageStore;

final class CollectionController
{
    private const PER_PAGE = 24;
    private const MAX_PHOTOS = 30;

    private const SORTS = [
        'title'    => 'i.title ASC',
        'new'      => 'i.created_at DESC, i.id DESC',
        'platform' => 'p.name ASC, i.title ASC',
        'price'    => 'i.price_cents DESC, i.title ASC',
    ];

    public function index(): void
    {
        $db = App::db();
        $filter = [
            'q'        => query('q', 100),
            'platform' => query_int('platform', 1),
            'kind'     => input_enum('kind', Options::KINDS, $_GET),
            'sort'     => input_enum('sort', self::SORTS, $_GET) ?? 'title',
            'view'     => query('view', 4) === 'list' ? 'list' : 'grid',
        ];
        $page = query_int('page', 1, 100000) ?? 1;

        $where = ['i.user_id = ?'];
        $params = [Auth::id()];
        if ($filter['q'] !== '') {
            $where[] = "(i.title LIKE ? ESCAPE '!' OR i.notes LIKE ? ESCAPE '!' OR p.name LIKE ? ESCAPE '!')";
            $like = Database::like($filter['q']);
            array_push($params, $like, $like, $like);
        }
        if ($filter['platform']) {
            $where[] = 'i.platform_id = ?';
            $params[] = $filter['platform'];
        }
        if ($filter['kind']) {
            $where[] = 'i.kind = ?';
            $params[] = $filter['kind'];
        }
        $from = ' FROM {items} i LEFT JOIN {platforms} p ON p.id = i.platform_id LEFT JOIN {games} g ON g.id = i.game_id WHERE ' . implode(' AND ', $where);

        $total = (int)$db->value('SELECT COUNT(*)' . $from, $params);
        $pages = max(1, (int)ceil($total / self::PER_PAGE));
        $page = min($page, $pages);

        $items = $db->all(
            'SELECT i.id, i.title, i.kind, i.region, i.completeness, i.cond_overall, i.price_cents,
                    p.name AS platform_name, g.boxart_thumb,
                    (SELECT ph.id FROM {photos} ph WHERE ph.item_id = i.id ORDER BY ph.sort, ph.id LIMIT 1) AS photo_id,
                    (SELECT COUNT(*) FROM {photos} ph WHERE ph.item_id = i.id) AS photo_count'
            . $from . ' ORDER BY ' . self::SORTS[$filter['sort']] . ' LIMIT ? OFFSET ?',
            [...$params, self::PER_PAGE, ($page - 1) * self::PER_PAGE]
        );

        View::render('collection/index', [
            'title'     => 'Sammlung',
            'nav'       => 'collection',
            'items'     => $items,
            'filter'    => $filter,
            'page'      => $page,
            'pages'     => $pages,
            'total'     => $total,
            'platforms' => Catalog::platforms(),
            'sorts'     => ['title' => 'Titel A–Z', 'new' => 'Neueste zuerst', 'platform' => 'Plattform', 'price' => 'Kaufpreis'],
        ]);
    }

    public function show(): void
    {
        $item = $this->findOwned(query_int('id', 1));
        $db = App::db();
        $photos = $db->all('SELECT * FROM {photos} WHERE item_id = ? ORDER BY sort, id', [$item['id']]);
        $game = $item['game_id'] ? $db->one('SELECT * FROM {games} WHERE id = ?', [$item['game_id']]) : null;
        $platform = $item['platform_id'] ? $db->one('SELECT * FROM {platforms} WHERE id = ?', [$item['platform_id']]) : null;
        $plays = $item['game_id']
            ? $db->all('SELECT * FROM {plays} WHERE user_id = ? AND game_id = ? ORDER BY finished_on DESC', [Auth::id(), $item['game_id']])
            : [];

        View::render('collection/show', [
            'title'    => $item['title'],
            'nav'      => 'collection',
            'item'     => $item,
            'photos'   => $photos,
            'game'     => $game,
            'platform' => $platform,
            'plays'    => $plays,
        ]);
    }

    public function edit(): void
    {
        $db = App::db();
        $id = input_int('id', 1) ?? query_int('id', 1);
        $item = $id ? $this->findOwned($id) : null;
        $errors = [];

        if (is_post()) {
            [$data, $errors, $game] = $this->validate($item);
            if (!$errors) {
                $now = Database::now();
                if ($item) {
                    $db->update('items', $data + ['updated_at' => $now], ['id' => $item['id'], 'user_id' => Auth::id()]);
                    $itemId = (int)$item['id'];
                } else {
                    $itemId = $db->insert('items', $data + ['user_id' => Auth::id(), 'created_at' => $now, 'updated_at' => $now]);
                }
                $warnings = $this->savePhotos($itemId);
                foreach ($warnings as $w) {
                    flash('warn', $w);
                }
                flash('ok', $item ? 'Eintrag gespeichert.' : '1UP! Neuer Eintrag in der Sammlung.');
                redirect('item', ['id' => $itemId]);
            }
            // Formular mit den gesendeten Werten erneut anzeigen.
            $item = array_merge($item ?? [], $data, ['id' => $item['id'] ?? null]);
            $item['_game'] = $game;
        }

        $item ??= [
            'id' => null, 'kind' => input_enum('kind', Options::KINDS, $_GET) ?? 'game',
            'platform_id' => query_int('platform', 1), 'title' => '', 'game_id' => null,
        ];
        if (!isset($item['_game']) && !empty($item['game_id'])) {
            $item['_game'] = $db->one('SELECT * FROM {games} WHERE id = ?', [$item['game_id']]);
        }
        $photos = !empty($item['id']) ? $db->all('SELECT * FROM {photos} WHERE item_id = ? ORDER BY sort, id', [$item['id']]) : [];

        View::render('collection/form', [
            'title'      => !empty($item['id']) ? 'Eintrag bearbeiten' : 'Neuer Eintrag',
            'nav'        => 'add',
            'item'       => $item,
            'photos'     => $photos,
            'errors'     => $errors,
            'platforms'  => Catalog::platforms(),
            'tgdb'       => Catalog::tgdb()->enabled(),
            'maxUpload'  => $this->uploadLimits(),
        ]);
    }

    public function delete(): void
    {
        if (!is_post()) {
            redirect('collection');
        }
        $item = $this->findOwned(input_int('id', 1));
        $db = App::db();
        foreach ($db->all('SELECT file FROM {photos} WHERE item_id = ?', [$item['id']]) as $ph) {
            ImageStore::remove(Auth::id(), $ph['file']);
        }
        $db->delete('items', ['id' => $item['id'], 'user_id' => Auth::id()]);
        flash('ok', 'Eintrag gelöscht.');
        redirect('collection');
    }

    /** Liefert ein Foto aus – nur an den Besitzer. */
    public function photo(): void
    {
        $id = query_int('id', 1);
        $photo = $id ? App::db()->one('SELECT * FROM {photos} WHERE id = ? AND user_id = ?', [$id, Auth::id()]) : null;
        $path = $photo ? ImageStore::path((int)$photo['user_id'], $photo['file'], query('s', 1) === 't') : null;
        if (!$path) {
            http_response_code(404);
            exit;
        }
        $etag = '"' . $photo['file'] . (query('s', 1) === 't' ? 't' : 'f') . '"';
        header('Cache-Control: private, max-age=31536000, immutable');
        header('ETag: ' . $etag);
        if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
            http_response_code(304);
            exit;
        }
        header('Content-Type: image/jpeg');
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: inline; filename="amigo-' . (int)$photo['id'] . '.jpg"');
        readfile($path);
        exit;
    }

    private function findOwned(?int $id): array
    {
        $item = $id ? App::db()->one('SELECT * FROM {items} WHERE id = ? AND user_id = ?', [$id, Auth::id()]) : null;
        if (!$item) {
            http_response_code(404);
            View::render('errors/error', ['title' => '404', 'code' => 404, 'message' => 'Eintrag nicht gefunden.']);
            exit;
        }
        return $item;
    }

    /** @return array{0: array, 1: string[], 2: ?array} */
    private function validate(?array $existing): array
    {
        $errors = [];
        [$game, $gameWarning] = Catalog::resolveGameFromInput();
        if ($gameWarning) {
            flash('warn', $gameWarning);
        }
        $cond = static fn (string $k) => input_int($k, 0, 5);

        $data = [
            'kind'            => input_enum('kind', Options::KINDS) ?? 'game',
            'game_id'         => $game['id'] ?? null,
            'platform_id'     => input_int('platform_id', 1),
            'title'           => input('title', 255),
            'region'          => input_enum('region', Options::REGIONS),
            'completeness'    => input_enum('completeness', Options::COMPLETENESS),
            'cond_overall'    => $cond('cond_overall'),
            'cond_media'      => $cond('cond_media'),
            'cond_box'        => $cond('cond_box'),
            'cond_manual'     => $cond('cond_manual'),
            'condition_notes' => input('condition_notes', 5000) ?: null,
            'purchase_date'   => input_date('purchase_date'),
            'price_cents'     => input_money('price'),
            'purchase_place'  => input('purchase_place', 150) ?: null,
            'notes'           => input('notes', 10000) ?: null,
        ];

        if ($game) {
            $data['title'] = $data['title'] !== '' ? $data['title'] : $game['title'];
            $data['platform_id'] ??= $game['platform_id'] ? (int)$game['platform_id'] : null;
        }
        if ($data['title'] === '') {
            $errors[] = 'Bitte einen Titel angeben oder ein Spiel aus der Suche wählen.';
        }
        if ($data['platform_id'] !== null && !Catalog::platformExists($data['platform_id'])) {
            $errors[] = 'Unbekannte Plattform.';
        }
        if (input('price', 20) !== '' && $data['price_cents'] === null) {
            $errors[] = 'Der Preis ist ungültig (z. B. 24,90).';
        }
        if (input('purchase_date', 10) !== '' && $data['purchase_date'] === null) {
            $errors[] = 'Das Kaufdatum ist ungültig.';
        }
        return [$data, $errors, $game];
    }

    /** Verarbeitet Bildunterschriften, Reihenfolge, Löschungen und neue Uploads. */
    private function savePhotos(int $itemId): array
    {
        $db = App::db();
        $uid = Auth::id();
        $warnings = [];
        $existing = $db->all('SELECT id, file FROM {photos} WHERE item_id = ? AND user_id = ?', [$itemId, $uid]);
        $captions = is_array($_POST['captions'] ?? null) ? $_POST['captions'] : [];
        $sorts = is_array($_POST['sorts'] ?? null) ? $_POST['sorts'] : [];
        $delete = array_map('intval', is_array($_POST['delete_photo'] ?? null) ? $_POST['delete_photo'] : []);

        foreach ($existing as $ph) {
            $pid = (int)$ph['id'];
            if (in_array($pid, $delete, true)) {
                ImageStore::remove($uid, $ph['file']);
                $db->delete('photos', ['id' => $pid, 'user_id' => $uid]);
                continue;
            }
            $db->update('photos', [
                'caption' => input((string)$pid, 500, $captions) ?: null,
                'sort'    => input_int((string)$pid, 0, 999, $sorts) ?? 0,
            ], ['id' => $pid, 'user_id' => $uid]);
        }

        $uploads = array_filter(ImageStore::normalize($_FILES['photos'] ?? null), fn ($f) => ($f['error'] ?? 4) !== UPLOAD_ERR_NO_FILE);
        if (!$uploads) {
            return $warnings;
        }
        $newCaptions = is_array($_POST['new_captions'] ?? null) ? array_values($_POST['new_captions']) : [];
        $count = (int)$db->value('SELECT COUNT(*) FROM {photos} WHERE item_id = ?', [$itemId]);
        $sort = (int)$db->value('SELECT COALESCE(MAX(sort), 0) FROM {photos} WHERE item_id = ?', [$itemId]);

        foreach (array_values($uploads) as $i => $upload) {
            if ($count >= self::MAX_PHOTOS) {
                $warnings[] = 'Maximal ' . self::MAX_PHOTOS . ' Fotos pro Eintrag.';
                break;
            }
            try {
                $stored = ImageStore::store($upload, $uid);
                $db->insert('photos', [
                    'item_id'    => $itemId,
                    'user_id'    => $uid,
                    'file'       => $stored['file'],
                    'width'      => $stored['width'],
                    'height'     => $stored['height'],
                    'bytes'      => $stored['bytes'],
                    'caption'    => input((string)$i, 500, $newCaptions) ?: null,
                    'sort'       => ++$sort,
                    'created_at' => Database::now(),
                ]);
                $count++;
            } catch (\RuntimeException $e) {
                $warnings[] = 'Foto „' . mb_substr(basename((string)$upload['name']), 0, 60) . '“: ' . $e->getMessage();
            }
        }
        return $warnings;
    }

    private function uploadLimits(): array
    {
        $toMb = static function (string $v): int {
            $n = (int)$v;
            return match (strtolower(substr(trim($v), -1))) {
                'g' => $n * 1024, 'k' => max(1, intdiv($n, 1024)), 'm' => $n, default => max(1, intdiv($n, 1048576)),
            };
        };
        return [
            'file'  => min($toMb((string)ini_get('upload_max_filesize')), (int)App::config('upload_max_mb', 10)),
            'post'  => $toMb((string)ini_get('post_max_size')),
            'files' => (int)ini_get('max_file_uploads'),
        ];
    }
}
