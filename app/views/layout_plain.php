<?php
use Amigo\Core\App;
use Amigo\Core\Options;

$theme = $_COOKIE['amigo_theme'] ?? 'crt';
$theme = isset(Options::THEMES[$theme]) ? $theme : 'crt';
$site = (string)App::config('site_name', 'AMIGO!');
?>
<!doctype html>
<html lang="de" data-theme="<?= e($theme) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#120f24">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e(($title ?? '') . ' · ' . $site) ?></title>
    <link rel="icon" href="<?= e(asset('img/icon-95.png')) ?>">
    <link rel="manifest" href="manifest.webmanifest">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <script src="<?= e(asset('js/app.js')) ?>" defer></script>
</head>
<body class="plain">
<main class="boot">
    <div class="boot-screen">
        <div class="boot-logo">
            <img src="<?= e(asset('img/logo.png')) ?>" alt="" width="72" height="72">
            <h1 class="logo-type"><?= e($site) ?></h1>
            <p class="boot-sub">The Retro Collector</p>
        </div>
        <?php require __DIR__ . '/partials/flash.php'; ?>
        <?= $content ?>
    </div>
    <p class="boot-foot">© <?= date('Y') ?> · <span class="blink">PRESS START</span></p>
</main>
</body>
</html>
