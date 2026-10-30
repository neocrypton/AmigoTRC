<div class="pagehead">
    <h1 class="h1">Plattformen verwalten</h1>
    <a class="btn" href="<?= e(url('admin/platform/edit')) ?>">+ Eigene Plattform</a>
</div>

<section class="panel">
    <h2 class="panel-title">Import aus TheGamesDB</h2>
    <?php if (!$enabled): ?>
        <p>Kein API-Key konfiguriert. Hinterlege deinen Key unter <a href="<?= e(url('admin/settings')) ?>">Admin › System</a> (kostenlos im <a href="https://forums.thegamesdb.net/" target="_blank" rel="noopener noreferrer">TheGamesDB-Forum</a> anfragen).</p>
    <?php elseif ($error): ?>
        <div class="flash flash-error"><?= e($error) ?></div>
    <?php else: ?>
        <form method="post" action="<?= e(url('admin/platforms')) ?>">
            <?= csrf_field() ?>
            <div class="filters">
                <input type="search" placeholder="Liste filtern… (z. B. Nintendo, Sega, Amiga)" data-filter-list="#tgdb-platforms" aria-label="Liste filtern">
                <button class="btn btn-primary" type="submit">📥 Ausgewählte importieren</button>
                <button class="btn" type="submit" name="all" value="1" data-confirm="Alle <?= count($remote) ?> Plattformen importieren/aktualisieren?">Alle importieren</button>
            </div>
            <ul class="checklist" id="tgdb-platforms">
                <?php foreach ($remote as $p): $has = isset($imported[$p['tgdb_id']]); ?>
                <li data-filter-text="<?= e(mb_strtolower($p['name'] . ' ' . $p['manufacturer'] . ' ' . $p['alias'])) ?>">
                    <label class="check">
                        <input type="checkbox" name="ids[]" value="<?= (int)$p['tgdb_id'] ?>">
                        <span><?= e($p['name']) ?> <small class="muted"><?= e($p['manufacturer'] ?? '') ?></small></span>
                        <?php if ($has): ?><span class="chip">✓ vorhanden</span><?php endif; ?>
                    </label>
                </li>
                <?php endforeach; ?>
            </ul>
        </form>
    <?php endif; ?>
</section>

<section class="panel">
    <h2 class="panel-title">Lokale Plattformen <span class="count"><?= count($local) ?></span></h2>
    <?php if (!$local): ?>
        <p class="empty">Noch keine Plattformen.</p>
    <?php else: ?>
    <div class="tablewrap">
    <table class="tbl">
        <thead><tr><th>Name</th><th>Hersteller</th><th>Quelle</th><th>Einträge</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($local as $p): ?>
            <tr>
                <td data-label="Name"><a href="<?= e(url('platform', ['id' => $p['id']])) ?>"><?= e($p['name']) ?></a></td>
                <td data-label="Hersteller"><?= e($p['manufacturer'] ?? '') ?></td>
                <td data-label="Quelle"><?= $p['tgdb_id'] ? 'TGDB #' . (int)$p['tgdb_id'] : 'eigene' ?></td>
                <td data-label="Einträge"><?= (int)$p['used'] ?></td>
                <td data-label="Aktionen">
                    <div class="btnrow">
                        <a class="btn btn-sm" href="<?= e(url('admin/platform/edit', ['id' => $p['id']])) ?>">✎</a>
                        <form method="post" action="<?= e(url('admin/platform/delete')) ?>" class="inline" data-confirm="Plattform „<?= e($p['name']) ?>“ entfernen?">
                            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
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
</section>
