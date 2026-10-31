<?php /** @var string $target ID der Textarea */ ?>
<div class="smileybar" data-smiley-target="#<?= e($target) ?>" role="group" aria-label="Smilies einfügen">
    <?php foreach (\Amigo\Core\Smilies::palette() as $id => [$code, $label]): ?>
    <button type="button" class="smiley-btn" data-code="<?= e($code) ?>" title="<?= e($label . '  ' . $code) ?>">
        <svg width="16" height="16" aria-hidden="true"><use href="<?= e(asset('img/smilies.svg')) ?>#<?= e($id) ?>"></use></svg>
        <span class="sr"><?= e($label) ?></span>
    </button>
    <?php endforeach; ?>
</div>
