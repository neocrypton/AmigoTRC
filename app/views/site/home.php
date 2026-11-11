<?php
use Amigo\Controller\SiteController;
use Amigo\Service\Html;
?>
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
