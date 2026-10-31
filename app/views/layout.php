<?php
use Amigo\Core\App;
use Amigo\Core\Auth;
use Amigo\Core\Options;

$user = Auth::user();
$nav ??= '';
$theme = $_COOKIE['amigo_theme'] ?? 'crt';
$theme = isset(Options::THEMES[$theme]) ? $theme : 'crt';
$site = (string)App::config('site_name', 'AMIGO!');
$icon = static fn (string $name): string => '<svg class="ico" aria-hidden="true"><use href="' . e(asset('img/icons.svg')) . '#' . $name . '"></use></svg>';
?>
<!doctype html>
<html lang="de" data-theme="<?= e($theme) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="dark light">
    <meta name="theme-color" content="#120f24">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e(($title ?? '') !== '' ? $title . ' · ' . $site : $site) ?></title>
    <link rel="icon" href="<?= e(asset('img/icon-95.png')) ?>">
    <link rel="apple-touch-icon" href="<?= e(asset('img/icon-200.png')) ?>">
    <link rel="manifest" href="manifest.webmanifest">
    <link rel="preload" href="<?= e(asset('fonts/press-start-2p-latin.woff2')) ?>" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <?php foreach ($scripts ?? [] as $s): ?>
    <script src="<?= e(asset($s)) ?>" defer></script>
    <?php endforeach; ?>
    <script src="<?= e(asset('js/app.js')) ?>" defer></script>
</head>
<body>
<a class="skip" href="#main">Zum Inhalt springen</a>
<header class="topbar">
    <div class="shell topbar-inner">
        <a class="brand" href="<?= e(url()) ?>" aria-label="<?= e($site) ?> – Start">
            <img src="<?= e(asset('img/logo.png')) ?>" alt="" width="40" height="40">
            <span class="brand-text"><span class="brand-name"><?= e($site) ?></span><span class="brand-sub">The Retro Collector</span></span>
        </a>
        <?php if ($user): ?>
        <nav class="mainnav" aria-label="Hauptnavigation">
            <a href="<?= e(url()) ?>"<?= $nav === 'home' ? ' aria-current="page"' : '' ?>>Start</a>
            <a href="<?= e(url('collection')) ?>"<?= $nav === 'collection' ? ' aria-current="page"' : '' ?>>Sammlung</a>
            <a href="<?= e(url('plays')) ?>"<?= $nav === 'plays' ? ' aria-current="page"' : '' ?>>Durchgespielt</a>
            <a href="<?= e(url('platforms')) ?>"<?= $nav === 'platforms' ? ' aria-current="page"' : '' ?>>Plattformen</a>
            <a href="<?= e(url('community')) ?>"<?= $nav === 'community' ? ' aria-current="page"' : '' ?>>Community</a>
            <?php if (Auth::isAdmin()): ?>
            <a href="<?= e(url('admin/settings')) ?>"<?= $nav === 'admin' ? ' aria-current="page"' : '' ?>>Admin</a>
            <?php endif; ?>
        </nav>
        <div class="topbar-actions">
            <a class="btn btn-primary btn-sm hide-sm" href="<?= e(url('item/edit')) ?>"><?= $icon('plus') ?> Neu</a>
            <?php if (\Amigo\Service\Messages::enabled()): $unread = \Amigo\Service\Messages::unreadCount((int)$user['id']); ?>
            <a class="iconbtn iconbtn-badge" href="<?= e(url('messages')) ?>" title="Nachrichten<?= $unread ? ' (' . $unread . ' ungelesen)' : '' ?>"<?= $nav === 'messages' ? ' aria-current="page"' : '' ?>><?= $icon('mail') ?><?php if ($unread): ?><span class="badge"><?= $unread > 99 ? '99+' : $unread ?></span><?php endif; ?><span class="sr">Nachrichten</span></a>
            <?php endif; ?>
            <a class="iconbtn" href="<?= e(url('account')) ?>" title="Mein Konto (<?= e($user['username']) ?>)"><?= avatar($user, 32, 'avatar-mini') ?><span class="sr">Konto</span></a>
            <button class="iconbtn" type="button" data-theme-toggle title="Farbschema wechseln"><?= $icon('palette') ?><span class="sr">Farbschema</span></button>
            <form method="post" action="<?= e(url('logout')) ?>" class="inline">
                <?= csrf_field() ?>
                <button class="iconbtn" type="submit" title="Logout"><?= $icon('power') ?><span class="sr">Logout</span></button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</header>

<main id="main" class="shell main">
    <?php require __DIR__ . '/partials/flash.php'; ?>
    <?= $content ?>
</main>

<footer class="shell footer">
    <span class="blink">▮</span> <?= e($site) ?> v<?= e(AMIGO_VERSION) ?> · Spieldaten von <a href="https://thegamesdb.net" rel="noopener noreferrer" target="_blank">TheGamesDB</a>
</footer>

<?php if ($user): ?>
<nav class="tabbar" aria-label="Schnellnavigation">
    <a href="<?= e(url()) ?>"<?= $nav === 'home' ? ' aria-current="page"' : '' ?>><?= $icon('home') ?><span>Start</span></a>
    <a href="<?= e(url('collection')) ?>"<?= $nav === 'collection' ? ' aria-current="page"' : '' ?>><?= $icon('cart') ?><span>Sammlung</span></a>
    <a class="tab-add" href="<?= e(url('item/edit')) ?>"<?= $nav === 'add' ? ' aria-current="page"' : '' ?>><?= $icon('plus') ?><span>Neu</span></a>
    <a href="<?= e(url('plays')) ?>"<?= $nav === 'plays' ? ' aria-current="page"' : '' ?>><?= $icon('trophy') ?><span>Gespielt</span></a>
    <a href="<?= e(url('account')) ?>"<?= in_array($nav, ['account', 'platforms', 'admin', 'community', 'messages'], true) ? ' aria-current="page"' : '' ?>><?= $icon('menu') ?><span>Mehr</span></a>
</nav>
<?php endif; ?>
</body>
</html>
