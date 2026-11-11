<?php
/**
 * Layout der öffentlichen Website (Startseite, Infoseiten, FAQ, News).
 * @var array $links  Links/Partner für die Seitenleiste
 */
use Amigo\Core\App;
use Amigo\Core\Auth;
use Amigo\Core\Options;
use Amigo\Service\Site;

$user = Auth::user();
$theme = $_COOKIE['amigo_theme'] ?? 'crt';
$theme = isset(Options::THEMES[$theme]) ? $theme : 'crt';
$site = (string)App::config('site_name', 'AMIGO!');
$icon = static fn (string $name): string => '<svg class="ico" aria-hidden="true"><use href="' . e(asset('img/icons.svg')) . '#' . $name . '"></use></svg>';
$current = $siteNav ?? '';
$indexable = Site::enabled() && empty($preview);
?>
<!doctype html>
<html lang="de" data-theme="<?= e($theme) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="dark light">
    <meta name="theme-color" content="#120f24">
    <meta name="robots" content="<?= $indexable ? 'index, follow' : 'noindex, nofollow' ?>">
    <?php if (!empty($description)): ?><meta name="description" content="<?= e($description) ?>"><?php endif; ?>
    <title><?= e(($title ?? '') !== '' ? $title . ' · ' . $site : $site . ' – The Retro Collector') ?></title>
    <link rel="icon" href="<?= e(asset('img/icon-95.png')) ?>">
    <link rel="apple-touch-icon" href="<?= e(asset('img/icon-200.png')) ?>">
    <link rel="preload" href="<?= e(asset('fonts/press-start-2p-latin.woff2')) ?>" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <script src="<?= e(asset('js/app.js')) ?>" defer></script>
</head>
<body class="site">
<a class="skip" href="#main">Zum Inhalt springen</a>
<header class="topbar">
    <div class="shell topbar-inner site-topbar">
        <a class="brand" href="<?= e(url('site')) ?>" aria-label="<?= e($site) ?> – Startseite">
            <img src="<?= e(asset('img/logo.png')) ?>" alt="" width="40" height="40">
            <span class="brand-text"><span class="brand-name"><?= e($site) ?></span><span class="brand-sub">The Retro Collector</span></span>
        </a>
        <div class="topbar-actions">
            <button class="iconbtn" type="button" data-theme-toggle title="Farbschema wechseln"><?= $icon('palette') ?><span class="sr">Farbschema</span></button>
            <?php if ($user): ?>
            <a class="btn btn-primary btn-sm" href="<?= e(url()) ?>">▶ Meine Sammlung</a>
            <?php else: ?>
            <a class="btn btn-primary btn-sm" href="<?= e(url('login')) ?>">▶ Login</a>
            <?php endif; ?>
        </div>
        <nav class="sitenav" aria-label="Website">
            <?php foreach (Site::nav() as [$href, $label, $key]): ?>
            <a href="<?= e($href) ?>"<?= $current === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
    </div>
</header>

<main id="main" class="shell main">
    <?php require __DIR__ . '/partials/flash.php'; ?>
    <?php if (!Site::enabled() && Auth::isAdmin()): ?>
    <div class="flash flash-info">👁 Vorschau – die Website ist ausgeschaltet und für Besucher nicht sichtbar. <a href="<?= e(url('admin/site')) ?>">Admin › Website</a></div>
    <?php elseif (!empty($preview)): ?>
    <div class="flash flash-info">👁 Vorschau – dieser Inhalt ist für Besucher nicht sichtbar. <a href="<?= e(url('admin/site')) ?>">Admin › Website</a></div>
    <?php endif; ?>
    <div class="site-grid<?= $links ? '' : ' site-grid-single' ?>">
        <div class="site-content"><?= $content ?></div>
        <?php if ($links): ?>
        <aside class="site-aside" aria-label="Links und Partner">
            <section class="panel">
                <h2 class="panel-title">Links &amp; Partner</h2>
                <ul class="partnerlist">
                    <?php foreach ($links as $l): ?>
                    <li>
                        <a href="<?= e($l['url']) ?>" target="_blank" rel="noopener noreferrer"<?= $l['banner'] ? ' title="' . e($l['name']) . '"' : '' ?>>
                            <?php if ($l['banner']): ?>
                            <img src="<?= e(Site::fileUrl($l['banner'])) ?>" alt="<?= e($l['name']) ?>" width="<?= Site::BANNER_W ?>" height="<?= Site::BANNER_H ?>" loading="lazy">
                            <?php else: ?>
                            ▸ <?= e($l['name']) ?>
                            <?php endif; ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        </aside>
        <?php endif; ?>
    </div>
</main>

<footer class="shell footer site-footer">
    <span class="blink">▮</span> <?= e($site) ?> – The Retro Collector
    <?php foreach (Site::legalLinks() as [$href, $label]): ?> · <a href="<?= e($href) ?>"><?= e($label) ?></a><?php endforeach; ?>
    · Spieldaten von <a href="https://thegamesdb.net" rel="noopener noreferrer" target="_blank">TheGamesDB</a>
    · <a href="<?= e(\Amigo\Service\SiteDefaults::REPO) ?>" rel="noopener noreferrer" target="_blank">Open Source (GPL-3.0)</a>
</footer>
</body>
</html>
