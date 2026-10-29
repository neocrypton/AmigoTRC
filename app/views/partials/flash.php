<?php foreach (flashes() as $f): ?>
<div class="flash flash-<?= e($f['type']) ?>" role="<?= $f['type'] === 'error' ? 'alert' : 'status' ?>"><?= e($f['msg']) ?></div>
<?php endforeach; ?>
<?php if (!empty($errors)): ?>
<div class="flash flash-error" role="alert">
    <?php if (count($errors) === 1): ?>
        <?= e($errors[0]) ?>
    <?php else: ?>
        <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
    <?php endif; ?>
</div>
<?php endif; ?>
