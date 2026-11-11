<?php
/** Sortierbare Liste (FAQ, Links). @var array $items @var string $kind @var callable $label @var string $edit */
$last = count($items) - 1;
$btn = static function (string $action, int $id, string $text, string $title, string $cls = '', string $confirm = ''): string {
    return '<form method="post" action="' . e(url('admin/site/action')) . '" class="inline"' . ($confirm ? ' data-confirm="' . e($confirm) . '"' : '') . '>'
        . csrf_field() . '<input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="action" value="' . e($action) . '">'
        . '<button class="btn btn-sm' . $cls . '" type="submit" title="' . e($title) . '">' . $text . '</button></form>';
};
?>
<?php if (!$items): ?>
<div class="panel empty-state"><p class="logo-type">EMPTY</p><p>Noch keine Einträge.</p></div>
<?php else: ?>
<ul class="sortlist">
    <?php foreach ($items as $i => $r): ?>
    <li class="panel<?= (int)$r['enabled'] ? '' : ' is-off' ?>">
        <?php if (!empty($r['banner'])): ?><img src="<?= e(\Amigo\Service\Site::fileUrl($r['banner'])) ?>" alt="" width="160" height="50" class="banner-thumb"><?php endif; ?>
        <span class="sortlist-text"><strong><?= e($label($r)) ?></strong><?php if (!empty($r['url'])): ?><small class="muted"><?= e($r['url']) ?></small><?php endif; ?></span>
        <span class="btnrow">
            <?= $i > 0 ? $btn($kind . '_up', (int)$r['id'], '▲', 'Nach oben') : '' ?>
            <?= $i < $last ? $btn($kind . '_down', (int)$r['id'], '▼', 'Nach unten') : '' ?>
            <?= $btn($kind . '_toggle', (int)$r['id'], (int)$r['enabled'] ? '👁' : '🚫', (int)$r['enabled'] ? 'Ausblenden' : 'Einblenden') ?>
            <a class="btn btn-sm" href="<?= e(url($edit, ['id' => $r['id']])) ?>" title="Bearbeiten">✎</a>
            <?= $btn($kind . '_delete', (int)$r['id'], '🗑', 'Löschen', ' btn-danger', '„' . $label($r) . '“ löschen?') ?>
        </span>
    </li>
    <?php endforeach; ?>
</ul>
<?php endif; ?>
