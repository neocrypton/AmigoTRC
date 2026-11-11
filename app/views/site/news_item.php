<?php use Amigo\Controller\SiteController; ?>
<p class="crumbs"><a href="<?= e(url('news')) ?>">News</a></p>
<article class="panel">
    <h1 class="h1 news-title"><?= e($item['title']) ?></h1>
    <p class="muted news-date"><?= e(fmt_date($item['published_at'])) ?><?= $item['username'] ? ' · von ' . e($item['username']) : '' ?></p>
    <div class="prose"><?= SiteController::html($item['body']) ?></div>
</article>
