<?php
use Amigo\Controller\SiteController;
use Amigo\Core\Auth;
use Amigo\Service\Html;

$site = (string)\Amigo\Core\App::config('site_name', 'AMIGO!');
?>
<section class="hero site-hero">
    <h1 class="logo-type"><?= e($site) ?></h1>
    <p class="hero-sub typewriter">THE RETRO COLLECTOR<span class="blink">_</span></p>
    <div class="hero-actions">
        <?php if (Auth::user()): ?>
        <a class="btn btn-primary" href="<?= e(url()) ?>">▶ Zur Sammlung</a>
        <?php else: ?>
        <a class="btn btn-primary" href="<?= e(url('login')) ?>">▶ Login</a>
        <?php if ($registration): ?><a class="btn" href="<?= e(url('register')) ?>">Konto erstellen</a><?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<article class="panel prose"><?= SiteController::html($page['body']) ?></article>

<?php if ($news): ?>
<section class="panel">
    <h2 class="panel-title">Neuigkeiten</h2>
    <ul class="newslist">
        <?php foreach ($news as $n): ?>
        <li>
            <a href="<?= e(url('news/item', ['id' => $n['id']])) ?>"><strong><?= e($n['title']) ?></strong></a>
            <small class="muted"><?= e(fmt_date($n['published_at'])) ?></small>
            <p><?= e(Html::text($n['body'], 180)) ?></p>
        </li>
        <?php endforeach; ?>
    </ul>
    <p><a href="<?= e(url('news')) ?>">Alle News ▶</a></p>
</section>
<?php endif; ?>
