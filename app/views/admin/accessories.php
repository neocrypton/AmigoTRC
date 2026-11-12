<?php use Amigo\Service\AccessoryCatalog; ?>
<div class="pagehead">
    <h1 class="h1">Zubehör-Katalog <span class="count"><?= count($rows) ?></span></h1>
    <a class="btn btn-ghost" href="<?= e(url('admin/settings')) ?>">⚙ System</a>
</div>

<p class="hint">Gemeinsamer Katalog für Zubehör (Controller, Memory Cards …). Einträge entstehen, wenn Nutzer Zubehör in ihre Sammlung eintragen. Das erste hochgeladene Foto wird Standardbild, sofern der Nutzer es nicht abwählt.</p>

<form method="get" action="<?= e(url()) ?>" class="filters">
    <input type="hidden" name="r" value="admin/accessories">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Name, Hersteller oder Plattform…" aria-label="Suche">
    <button class="btn" type="submit">Suchen</button>
</form>

<?php if (!$rows): ?>
<div class="panel empty-state"><p><?= $q !== '' ? 'Keine Treffer.' : 'Der Katalog ist noch leer – er füllt sich, sobald Zubehör eingetragen wird.' ?></p></div>
<?php else: ?>
<ul class="acc-admin">
    <?php foreach ($rows as $a): ?>
    <li class="panel">
        <?php if ($img = AccessoryCatalog::imageUrl($a)): ?>
            <img src="<?= e($img) ?>" alt="" width="64" height="64" loading="lazy">
        <?php else: ?>
            <span class="noimg" aria-hidden="true">🎮</span>
        <?php endif; ?>
        <div class="acc-admin-body">
            <strong><?= e($a['name']) ?></strong>
            <small class="muted"><?= e(implode(' · ', array_filter([$a['platform_name'], $a['manufacturer'], (int)$a['uses'] . '× in Sammlungen']))) ?></small>
            <small class="muted">angelegt von <?= e($a['creator'] ?? '–') ?><?= $a['image'] ? ' · Bild von ' . e($a['image_by'] ?? '–') : ' · kein Bild' ?></small>
        </div>
        <div class="btnrow">
            <a class="btn btn-sm" href="<?= e(url('admin/accessory/edit', ['id' => $a['id']])) ?>">✎ Bearbeiten</a>
            <form method="post" action="<?= e(url('admin/accessories', array_filter(['q' => $q]))) ?>" class="inline">
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                <?php if ($a['image']): ?><button class="btn btn-sm" name="action" value="remove_image" data-confirm="Standardbild entfernen?">🖼 Bild entfernen</button><?php endif; ?>
                <button class="btn btn-sm btn-danger" name="action" value="delete" data-confirm="„<?= e($a['name']) ?>“ aus dem Katalog löschen? Die Einträge in den Sammlungen bleiben erhalten.">🗑 Löschen</button>
            </form>
        </div>
    </li>
    <?php endforeach; ?>
</ul>
<?php endif; ?>
