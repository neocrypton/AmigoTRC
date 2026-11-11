<div class="pagehead">
    <div>
        <p class="crumbs"><a href="<?= e(url('admin/settings')) ?>">Admin</a> › <a href="<?= e(url('admin/site')) ?>">Website</a></p>
        <h1 class="h1">Links &amp; Partner <span class="count"><?= count($items) ?></span></h1>
    </div>
    <a class="btn btn-primary" href="<?= e(url('admin/site/link/edit')) ?>">+ Neuer Link</a>
</div>
<?php $siteTab = 'links'; require __DIR__ . '/_nav.php'; ?>
<?php $kind = 'link'; $label = static fn ($r) => $r['name']; $edit = 'admin/site/link/edit'; require __DIR__ . '/_sortlist.php'; ?>
<p class="hint">Erscheinen in der Seitenleiste der Website – mit Banner (160×50 px) als Bild-Button, sonst als Textlink.</p>
