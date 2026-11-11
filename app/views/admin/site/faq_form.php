<div class="pagehead">
    <div>
        <p class="crumbs"><a href="<?= e(url('admin/site')) ?>">Website</a> › <a href="<?= e(url('admin/site/faq')) ?>">FAQ</a></p>
        <h1 class="h1"><?= e($title) ?></h1>
    </div>
</div>
<?php require __DIR__ . '/_errors.php'; ?>
<form method="post" action="<?= e(url('admin/site/faq/edit')) ?>" class="panel">
    <?= csrf_field() ?>
    <?php if (!empty($item['id'])): ?><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><?php endif; ?>
    <div class="field">
        <label for="f-q">Frage</label>
        <input id="f-q" name="question" value="<?= e($item['question'] ?? '') ?>" maxlength="255" required>
    </div>
    <?php $editorName = 'answer'; $editorLabel = 'Antwort'; $editorValue = (string)($item['answer'] ?? ''); require __DIR__ . '/../../partials/editor.php'; ?>
    <label class="check"><input type="checkbox" name="enabled" value="1"<?= checked((int)($item['enabled'] ?? 1) === 1) ?>> Frage anzeigen</label>
    <div class="btnrow">
        <button class="btn btn-primary" type="submit">💾 Speichern</button>
        <a class="btn btn-ghost" href="<?= e(url('admin/site/faq')) ?>">Abbrechen</a>
    </div>
</form>
