<?php
declare(strict_types=1);

namespace Amigo\Controller;

use Amigo\Core\App;
use Amigo\Core\Auth;
use Amigo\Core\Settings;
use Amigo\Core\View;
use Amigo\Service\Html;
use Amigo\Service\Recaptcha;
use Amigo\Service\Site;

/** Öffentliche Website: Startseite, Inhaltsseiten, FAQ, News, Bilder. */
final class SiteController
{
    /** Inline-Styles aus dem Editor (Farben, Ausrichtung …) – bereits gefiltert, nur als Attribut erlaubt. */
    public const CSP = ['style-src-attr' => ["'unsafe-inline'"]];

    public function home(): void
    {
        $page = Site::page('home');
        $this->guard($page);
        $this->render('site/home', [
            'page'         => $page,
            'title'        => '',
            'siteNav'      => 'home',
            'preview'      => !Site::visible($page),
            'description'  => Html::text($page['body'], 160),
            'news'         => Site::newsEnabled() ? Site::news(3) : [],
            'registration' => Settings::bool(App::db(), 'registration_open') && Recaptcha::configured(),
        ]);
    }

    public function page(): void
    {
        $slug = query('p', 30);
        if ($slug === 'home') {
            redirect('site');
        }
        $page = Site::page($slug);
        $this->guard($page);
        $this->render('site/page', [
            'page'        => $page,
            'title'       => $page['title'],
            'siteNav'     => $slug,
            'preview'     => !Site::visible($page),
            'description' => Html::text($page['body'], 160),
            'faq'   => $slug === 'faq' ? Site::faq() : [],
        ]);
    }

    public function news(): void
    {
        if (!Site::newsEnabled() && !Auth::isAdmin()) {
            $this->notFound();
        }
        $per = 10;
        $total = Site::newsCount();
        $pages = max(1, (int)ceil($total / $per));
        $page = min(query_int('page', 1) ?? 1, $pages);
        $this->render('site/news', [
            'title'    => 'News',
            'siteNav'  => 'news',
            'news'     => Site::news($per, ($page - 1) * $per),
            'pageNo'   => $page,
            'pages'    => $pages,
            'preview'  => !Site::newsEnabled(),
        ]);
    }

    public function newsItem(): void
    {
        $item = App::db()->one('SELECT n.*, u.username FROM {site_news} n LEFT JOIN {users} u ON u.id = n.author_id WHERE n.id = ?', [query_int('id', 1) ?? 0]);
        $public = $item && (int)$item['published'] && $item['published_at'] <= \Amigo\Core\Database::now() && Site::newsEnabled();
        if (!$item || (!$public && !Auth::isAdmin())) {
            $this->notFound();
        }
        $this->render('site/news_item', ['title' => $item['title'], 'item' => $item, 'preview' => !$public, 'siteNav' => 'news', 'description' => Html::text($item['body'], 160)]);
    }

    /** Bilder aus dem Editor und Partner-Banner. */
    public function file(): void
    {
        $name = query('f', 40);
        $path = Site::file($name);
        if (!$path) {
            http_response_code(404);
            exit;
        }
        $etag = '"' . $name . '"';
        header('Cache-Control: public, max-age=31536000, immutable');
        header('ETag: ' . $etag);
        if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
            http_response_code(304);
            exit;
        }
        header('Content-Type: ' . match (pathinfo($name, PATHINFO_EXTENSION)) { 'jpg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif' });
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: inline');
        readfile($path);
        exit;
    }

    /** Nicht freigegebene Seiten: 404 – außer für Admins (Vorschau). */
    private function guard(?array $page): void
    {
        if (!$page || (!Site::visible($page) && !Auth::isAdmin())) {
            $this->notFound();
        }
    }

    private function notFound(): never
    {
        http_response_code(404);
        View::render('errors/error', ['title' => '404', 'code' => 404, 'message' => 'Dieses Level gibt es nicht.']);
        exit;
    }

    private function render(string $view, array $data): void
    {
        App::sendSecurityHeaders(self::CSP);
        $data['links'] = Site::links();
        $data['nav'] = $data['nav'] ?? '';
        View::render($view, $data, 'layout_site');
    }

    /** Für Templates: gefiltertes HTML eines Website-Textes. */
    public static function html(?string $html): string
    {
        return Html::render((string)$html);
    }
}
