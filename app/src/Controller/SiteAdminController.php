<?php
declare(strict_types=1);

namespace Amigo\Controller;

use Amigo\Core\App;
use Amigo\Core\Auth;
use Amigo\Core\Database;
use Amigo\Core\Settings;
use Amigo\Core\View;
use Amigo\Service\Html;
use Amigo\Service\Site;

/** Adminbereich der öffentlichen Website: Schalter, Seiten, FAQ, News, Links/Partner. */
final class SiteAdminController
{
    /** CKEditor setzt Inline-Styles (Positionen, Farben) – nur auf den Bearbeiten-Seiten erlaubt. */
    private const EDITOR = [
        'modules' => ['js/editor.js'],
        'styles'  => ['vendor/ckeditor5-49.1.0/ckeditor5.css', 'css/editor.css'],
    ];

    public function index(): void
    {
        $db = App::db();
        if (is_post()) {
            Settings::set($db, 'site_enabled', isset($_POST['site_enabled']) ? '1' : '0');
            Settings::set($db, 'news_enabled', isset($_POST['news_enabled']) ? '1' : '0');
            flash('ok', isset($_POST['site_enabled']) ? 'Website ist eingeschaltet.' : 'Website ist ausgeschaltet – Besucher sehen nur die Anmeldung.');
            redirect('admin/site');
        }
        View::render('admin/site/index', [
            'title'       => 'Website',
            'nav'         => 'admin',
            'siteEnabled' => Settings::bool($db, 'site_enabled'),
            'newsEnabled' => Settings::bool($db, 'news_enabled'),
            'pages'       => Site::pages(),
            'counts'      => [
                'faq'   => (int)$db->value('SELECT COUNT(*) FROM {site_faq}'),
                'news'  => (int)$db->value('SELECT COUNT(*) FROM {site_news}'),
                'links' => (int)$db->value('SELECT COUNT(*) FROM {site_links}'),
            ],
        ]);
    }

    public function page(): void
    {
        $slug = input('slug', 30) ?: query('p', 30);
        $page = Site::page($slug);
        if (!$page) {
            redirect('admin/site');
        }
        $errors = [];
        if (is_post()) {
            $data = [
                'title'      => input('title', 150),
                'body'       => Html::clean($this->rawHtml('body')),
                'enabled'    => isset($_POST['enabled']) ? 1 : 0,
                'updated_at' => Database::now(),
            ];
            if ($data['title'] === '') {
                $errors[] = 'Bitte einen Titel angeben.';
            }
            if (!$errors) {
                App::db()->update('site_pages', $data, ['id' => $page['id']]);
                flash('ok', '„' . $data['title'] . '“ gespeichert.');
                redirect('admin/site/page', ['p' => $slug]);
            }
            $page = array_merge($page, $data);
        }
        $this->editor('admin/site/page', ['title' => 'Seite bearbeiten: ' . $page['title'], 'page' => $page, 'errors' => $errors]);
    }

    public function faq(): void
    {
        View::render('admin/site/faq', ['title' => 'FAQ', 'nav' => 'admin', 'items' => Site::faq(false)]);
    }

    public function faqEdit(): void
    {
        $db = App::db();
        $id = input_int('id', 1) ?? query_int('id', 1);
        $item = $id ? $db->one('SELECT * FROM {site_faq} WHERE id = ?', [$id]) : null;
        $errors = [];
        if (is_post()) {
            $data = [
                'question' => input('question', 255),
                'answer'   => Html::clean($this->rawHtml('answer')),
                'enabled'  => isset($_POST['enabled']) ? 1 : 0,
            ];
            if ($data['question'] === '') {
                $errors[] = 'Bitte eine Frage eingeben.';
            }
            if (!$errors) {
                if ($item) {
                    $db->update('site_faq', $data, ['id' => $item['id']]);
                } else {
                    $db->insert('site_faq', $data + ['sort' => Site::nextSort('site_faq')]);
                }
                flash('ok', 'Frage gespeichert.');
                redirect('admin/site/faq');
            }
            $item = array_merge($item ?? [], $data);
        }
        $this->editor('admin/site/faq_form', ['title' => $item && !empty($item['id']) ? 'Frage bearbeiten' : 'Neue Frage', 'item' => $item ?? ['enabled' => 1], 'errors' => $errors]);
    }

    public function news(): void
    {
        View::render('admin/site/news', [
            'title'       => 'News',
            'nav'         => 'admin',
            'items'       => App::db()->all('SELECT * FROM {site_news} ORDER BY published_at DESC, id DESC'),
            'newsEnabled' => Site::newsEnabled(),
            'now'         => Database::now(),
        ]);
    }

    public function newsEdit(): void
    {
        $db = App::db();
        $id = input_int('id', 1) ?? query_int('id', 1);
        $item = $id ? $db->one('SELECT * FROM {site_news} WHERE id = ?', [$id]) : null;
        $errors = [];
        if (is_post()) {
            $when = self::localToUtc(input('published_at', 20));
            $data = [
                'title'        => input('title', 200),
                'body'         => Html::clean($this->rawHtml('body')),
                'published'    => isset($_POST['published']) ? 1 : 0,
                'published_at' => $when ?? Database::now(),
                'updated_at'   => Database::now(),
            ];
            if ($data['title'] === '') {
                $errors[] = 'Bitte einen Titel angeben.';
            }
            if (input('published_at', 20) !== '' && $when === null) {
                $errors[] = 'Das Datum ist ungültig.';
            }
            if (!$errors) {
                if ($item) {
                    $db->update('site_news', $data, ['id' => $item['id']]);
                } else {
                    $db->insert('site_news', $data + ['author_id' => Auth::id(), 'created_at' => Database::now()]);
                }
                flash('ok', $data['published'] ? 'News gespeichert und veröffentlicht.' : 'News als Entwurf gespeichert.');
                redirect('admin/site/news');
            }
            $item = array_merge($item ?? [], $data);
        }
        $this->editor('admin/site/news_form', [
            'title' => $item && !empty($item['id']) ? 'News bearbeiten' : 'Neue News',
            'item'  => $item ?? ['published' => 1, 'published_at' => Database::now()],
            'errors' => $errors,
        ]);
    }

    public function links(): void
    {
        View::render('admin/site/links', ['title' => 'Links & Partner', 'nav' => 'admin', 'items' => Site::links(false)]);
    }

    public function linkEdit(): void
    {
        $db = App::db();
        $id = input_int('id', 1) ?? query_int('id', 1);
        $item = $id ? $db->one('SELECT * FROM {site_links} WHERE id = ?', [$id]) : null;
        $errors = [];
        if (is_post()) {
            $url = input('url', 500);
            $data = [
                'name'    => input('name', 100),
                'url'     => Html::url($url, false) ?? '',
                'enabled' => isset($_POST['enabled']) ? 1 : 0,
            ];
            if ($data['name'] === '') {
                $errors[] = 'Bitte einen Namen angeben.';
            }
            if ($data['url'] === '') {
                $errors[] = 'Bitte eine vollständige Adresse mit https:// angeben.';
            }
            $banner = $item['banner'] ?? null;
            $newBanner = null;
            if (!$errors && ($_FILES['banner']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                try {
                    $newBanner = Site::storeBanner($_FILES['banner']);
                } catch (\RuntimeException $e) {
                    $errors[] = $e->getMessage();
                }
            }
            if (!$errors) {
                if ($newBanner || isset($_POST['remove_banner'])) {
                    Site::removeFile($banner);
                    $banner = $newBanner;
                }
                $data['banner'] = $banner;
                if ($item) {
                    $db->update('site_links', $data, ['id' => $item['id']]);
                } else {
                    $db->insert('site_links', $data + ['sort' => Site::nextSort('site_links')]);
                }
                flash('ok', 'Link gespeichert.');
                redirect('admin/site/links');
            }
            $data['url'] = $url;
            $item = array_merge($item ?? [], $data);
        }
        View::render('admin/site/link_form', [
            'title'  => $item && !empty($item['id']) ? 'Link bearbeiten' : 'Neuer Link',
            'nav'    => 'admin',
            'item'   => $item ?? ['enabled' => 1, 'banner' => null],
            'errors' => $errors,
        ]);
    }

    /** Verschieben, Ein-/Ausschalten und Löschen von FAQ, Links und News. */
    public function action(): void
    {
        $db = App::db();
        $id = input_int('id', 1) ?? 0;
        $action = input('action', 20);
        [$type, $verb] = array_pad(explode('_', $action, 2), 2, '');
        $map = ['faq' => ['site_faq', 'admin/site/faq'], 'link' => ['site_links', 'admin/site/links'], 'news' => ['site_news', 'admin/site/news']];
        if (!is_post() || !isset($map[$type])) {
            redirect('admin/site');
        }
        [$table, $back] = $map[$type];
        $row = $db->one('SELECT * FROM {' . $table . '} WHERE id = ?', [$id]);
        if ($row) {
            switch ($verb) {
                case 'up':
                case 'down':
                    if ($type !== 'news') {
                        Site::move($table, $id, $verb === 'up' ? -1 : 1);
                    }
                    break;
                case 'toggle':
                    $col = $type === 'news' ? 'published' : 'enabled';
                    $db->update($table, [$col => (int)$row[$col] ? 0 : 1], ['id' => $id]);
                    break;
                case 'delete':
                    if ($type === 'link') {
                        Site::removeFile($row['banner']);
                    }
                    $db->delete($table, ['id' => $id]);
                    flash('ok', 'Eintrag gelöscht.');
                    break;
            }
        }
        redirect($back);
    }

    /** Bild-Upload aus dem CKEditor (SimpleUploadAdapter) – Antwort als JSON. */
    public function upload(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        if (!is_post()) {
            http_response_code(405);
            echo json_encode(['error' => ['message' => 'Nur POST.']]);
            return;
        }
        try {
            $name = Site::storeImage($_FILES['upload'] ?? []);
            echo json_encode(['url' => Site::fileUrl($name)], JSON_UNESCAPED_SLASHES);
        } catch (\RuntimeException $e) {
            http_response_code(422);
            echo json_encode(['error' => ['message' => $e->getMessage()]], JSON_UNESCAPED_UNICODE);
        }
    }

    /** HTML aus dem Editor (ohne Längenkürzung von input(), max. 1 MB). */
    private function rawHtml(string $key): string
    {
        $v = $_POST[$key] ?? '';
        return is_string($v) ? substr($v, 0, 1024 * 1024) : '';
    }

    private function editor(string $view, array $data): void
    {
        App::sendSecurityHeaders(SiteController::CSP);
        View::render($view, $data + ['nav' => 'admin'] + self::EDITOR);
    }

    /** Datum/Uhrzeit aus <input type="datetime-local"> (Ortszeit) → UTC für die Datenbank. */
    public static function localToUtc(string $local): ?string
    {
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $local, new \DateTimeZone(date_default_timezone_get()));
        return $d ? $d->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s') : null;
    }

    public static function utcToLocal(?string $utc): string
    {
        if (!$utc) {
            return '';
        }
        return (new \DateTimeImmutable($utc, new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone(date_default_timezone_get()))->format('Y-m-d\TH:i');
    }
}
