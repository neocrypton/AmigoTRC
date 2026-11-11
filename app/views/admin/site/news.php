<div class="pagehead">
    <div>
        <p class="crumbs"><a href="<?= e(url('admin/settings')) ?>">Admin</a> › <a href="<?= e(url('admin/site')) ?>">Website</a></p>
        <h1 class="h1">News <span class="count"><?= count($items) ?></span></h1>
    </div>
    <a class="btn btn-primary" href="<?= e(url('admin/site/news/edit')) ?>">+ Neue News</a>
</div>
<?php $siteTab = 'news'; require __DIR__ . '/_nav.php'; ?>
<?php if (!$newsEnabled): ?>
<div class="flash flash-info">Der News-Bereich ist ausgeschaltet<?= \Amigo\Service\Site::enabled() ? '' : ' (bzw. die ganze Website)' ?>. Du kannst trotzdem schon Meldungen vorbereiten – einschalten unter <a href="<?= e(url('admin/site')) ?>">Übersicht</a>.</div>
<?php endif; ?>
<?php if (!$items): ?>
<div class="panel empty-state"><p class="logo-type">NO NEWS</p><p>Noch keine Meldungen.</p></div>
<?php else: ?>
<div class="tablewrap">
<table class="tbl">
    <thead><tr><th>Titel</th><th>Datum</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($items as $n): $planned = (int)$n['published'] && $n['published_at'] > $now; ?>
    <tr<?= (int)$n['published'] ? '' : ' class="is-off"' ?>>
        <td data-label="Titel"><a href="<?= e(url('news/item', ['id' => $n['id']])) ?>" target="_blank"><?= e($n['title']) ?></a></td>
        <td data-label="Datum"><?= e(fmt_datetime($n['published_at'])) ?></td>
        <td data-label="Status"><?= !(int)$n['published'] ? '<span class="cond cond-1">ENTWURF</span>' : ($planned ? '<span class="cond cond-3">GEPLANT</span>' : '<span class="cond cond-5">ONLINE</span>') ?></td>
        <td data-label="Aktionen">
            <div class="btnrow">
                <a class="btn btn-sm" href="<?= e(url('admin/site/news/edit', ['id' => $n['id']])) ?>">✎</a>
                <form method="post" action="<?= e(url('admin/site/action')) ?>" class="inline">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$n['id'] ?>"><input type="hidden" name="action" value="news_toggle">
                    <button class="btn btn-sm" type="submit"><?= (int)$n['published'] ? 'Zurückziehen' : 'Veröffentlichen' ?></button>
                </form>
                <form method="post" action="<?= e(url('admin/site/action')) ?>" class="inline" data-confirm="„<?= e($n['title']) ?>“ löschen?">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$n['id'] ?>"><input type="hidden" name="action" value="news_delete">
                    <button class="btn btn-sm btn-danger" type="submit">🗑</button>
                </form>
            </div>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
