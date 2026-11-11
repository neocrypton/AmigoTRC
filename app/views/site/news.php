<?php use Amigo\Service\Html; ?>
<div class="pagehead"><h1 class="h1">News</h1></div>
<?php if (!$news): ?>
<div class="panel empty-state"><p class="logo-type">NO NEWS</p><p>Noch keine Neuigkeiten.</p></div>
<?php else: ?>
<?php foreach ($news as $n): ?>
<article class="panel newscard">
    <h2 class="news-title"><a href="<?= e(url('news/item', ['id' => $n['id']])) ?>"><?= e($n['title']) ?></a></h2>
    <p class="muted news-date"><?= e(fmt_date($n['published_at'])) ?></p>
    <p><?= e(Html::text($n['body'], 300)) ?></p>
    <a class="btn btn-sm" href="<?= e(url('news/item', ['id' => $n['id']])) ?>">Weiterlesen ▶</a>
</article>
<?php endforeach; ?>
<?php $page = $pageNo; $route = 'news'; $params = []; require __DIR__ . '/../partials/pager.php'; ?>
<?php endif; ?>
