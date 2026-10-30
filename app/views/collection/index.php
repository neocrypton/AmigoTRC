<?php
use Amigo\Core\Options;

$owner ??= null;
$listRoute = $owner ? 'u/collection' : 'collection';
$itemUrl = static fn (int $id) => $owner ? url('u/item', ['name' => $owner['username'], 'id' => $id]) : url('item', ['id' => $id]);
$params = ($owner ? ['name' => $owner['username']] : []) + array_filter(['q' => $filter['q'], 'platform' => $filter['platform'], 'kind' => $filter['kind'], 'sort' => $filter['sort'] !== 'title' ? $filter['sort'] : null, 'view' => $filter['view'] === 'list' ? 'list' : null]);
?>
<div class="pagehead">
    <?php if ($owner): ?>
    <div>
        <p class="crumbs"><a href="<?= e(url('community')) ?>">Community</a> › <a href="<?= e(url('u', ['name' => $owner['username']])) ?>"><?= e($owner['username']) ?></a></p>
        <h1 class="h1">Sammlung von <?= e($owner['username']) ?> <span class="count"><?= fmt_num($total) ?></span></h1>
    </div>
    <?php else: ?>
    <h1 class="h1">Sammlung <span class="count"><?= fmt_num($total) ?></span></h1>
    <a class="btn btn-primary" href="<?= e(url('item/edit', ['platform' => $filter['platform'], 'kind' => $filter['kind']])) ?>">+ Neuer Eintrag</a>
    <?php endif; ?>
</div>

<form class="filters" method="get" action="index.php" role="search">
    <input type="hidden" name="r" value="<?= e($listRoute) ?>">
    <?php if ($owner): ?><input type="hidden" name="name" value="<?= e($owner['username']) ?>"><?php endif; ?>
    <input type="search" name="q" value="<?= e($filter['q']) ?>" placeholder="Suchen…" aria-label="Suchbegriff">
    <select name="platform" aria-label="Plattform">
        <option value="">Alle Plattformen</option>
        <?php foreach ($platforms as $p): ?>
        <option value="<?= (int)$p['id'] ?>"<?= selected($p['id'], $filter['platform']) ?>><?= e($p['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="kind" aria-label="Art">
        <option value="">Alles</option>
        <?php foreach (Options::KINDS as $k => $label): ?>
        <option value="<?= e($k) ?>"<?= selected($k, $filter['kind']) ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="sort" aria-label="Sortierung">
        <?php foreach ($sorts as $k => $label): ?>
        <option value="<?= e($k) ?>"<?= selected($k, $filter['sort']) ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="view" aria-label="Ansicht">
        <option value="grid">Kacheln</option>
        <option value="list"<?= selected('list', $filter['view']) ?>>Liste (spart Daten)</option>
    </select>
    <button class="btn" type="submit">Filtern</button>
</form>

<?php if (!$items): ?>
    <div class="panel empty-state">
        <p class="logo-type">NO DATA</p>
        <p><?= $filter['q'] || $filter['platform'] || $filter['kind'] ? 'Keine Treffer für diesen Filter.' : ($owner ? 'Diese Sammlung ist noch leer.' : 'Deine Sammlung ist noch leer.') ?></p>
        <?php if (!$owner): ?><a class="btn btn-primary" href="<?= e(url('item/edit')) ?>">+ Ersten Eintrag anlegen</a><?php endif; ?>
    </div>
<?php elseif ($filter['view'] === 'list'): ?>
    <div class="tablewrap">
    <table class="tbl">
        <thead><tr><th>Titel</th><th>Plattform</th><th>Region</th><th>Zustand</th><th>Umfang</th></tr></thead>
        <tbody>
        <?php foreach ($items as $it): ?>
            <tr>
                <td data-label="Titel"><a href="<?= e($itemUrl((int)$it['id'])) ?>"><?= e($it['title']) ?></a><?= $it['photo_count'] ? ' <small class="muted">📷' . (int)$it['photo_count'] . '</small>' : '' ?></td>
                <td data-label="Plattform"><?= e($it['platform_name'] ?? '–') ?></td>
                <td data-label="Region"><?= e($it['region'] ?? '') ?></td>
                <td data-label="Zustand"><?= $it['cond_overall'] !== null ? cond_badge((int)$it['cond_overall']) : '' ?></td>
                <td data-label="Umfang"><?= e(Options::COMPLETENESS[$it['completeness']] ?? '') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
<?php else: ?>
    <ul class="carts">
        <?php foreach ($items as $it): ?>
        <li class="cart">
            <a href="<?= e($itemUrl((int)$it['id'])) ?>">
                <span class="cart-label">
                    <?php if ($it['photo_id']): ?>
                        <img src="<?= e(url('photo', ['id' => $it['photo_id'], 's' => 't'])) ?>" alt="" loading="lazy" decoding="async">
                    <?php elseif ($thumb = tgdb_image($it['boxart_thumb'])): ?>
                        <img src="<?= e($thumb) ?>" alt="" loading="lazy" decoding="async">
                    <?php else: ?>
                        <span class="cart-noimg" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($it['title'], 0, 1))) ?></span>
                    <?php endif; ?>
                    <?php if ($it['region']): ?><span class="tag tag-region"><?= e($it['region']) ?></span><?php endif; ?>
                </span>
                <span class="cart-title"><?= e($it['title']) ?></span>
                <span class="cart-meta"><?= e($it['platform_name'] ?? Options::KINDS[$it['kind']] ?? '') ?></span>
                <?php if ($it['cond_overall'] !== null): ?><span class="cart-cond"><?= cond_badge((int)$it['cond_overall']) ?></span><?php endif; ?>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php $route = $listRoute; require __DIR__ . '/../partials/pager.php'; ?>
