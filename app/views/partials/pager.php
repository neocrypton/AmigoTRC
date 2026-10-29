<?php
/** @var int $page @var int $pages @var string $route @var array $params */
if ($pages <= 1) {
    return;
}
$link = static fn (int $p) => url($route, ['page' => $p > 1 ? $p : null] + $params);
?>
<nav class="pager" aria-label="Seiten">
    <?php if ($page > 1): ?><a class="btn btn-sm" href="<?= e($link($page - 1)) ?>" rel="prev">◀ Zurück</a><?php endif; ?>
    <span class="pager-info">Level <?= $page ?> / <?= $pages ?></span>
    <?php if ($page < $pages): ?><a class="btn btn-sm" href="<?= e($link($page + 1)) ?>" rel="next">Weiter ▶</a><?php endif; ?>
</nav>
