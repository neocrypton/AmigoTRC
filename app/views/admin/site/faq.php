<div class="pagehead">
    <div>
        <p class="crumbs"><a href="<?= e(url('admin/settings')) ?>">Admin</a> › <a href="<?= e(url('admin/site')) ?>">Website</a></p>
        <h1 class="h1">FAQ <span class="count"><?= count($items) ?></span></h1>
    </div>
    <a class="btn btn-primary" href="<?= e(url('admin/site/faq/edit')) ?>">+ Neue Frage</a>
</div>
<?php $siteTab = 'faq'; require __DIR__ . '/_nav.php'; ?>
<?php $kind = 'faq'; $label = static fn ($r) => $r['question']; $edit = 'admin/site/faq/edit'; require __DIR__ . '/_sortlist.php'; ?>
<p class="hint">Den Einleitungstext über den Fragen bearbeitest du bei der <a href="<?= e(url('admin/site/page', ['p' => 'faq'])) ?>">Seite „FAQ“</a>.</p>
