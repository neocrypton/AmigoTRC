<div class="pagehead">
    <div>
        <p class="crumbs"><a href="<?= e(url('admin/settings')) ?>">Admin</a> › <a href="<?= e(url('admin/site')) ?>">Website</a></p>
        <h1 class="h1"><?= e($page['title']) ?></h1>
    </div>
    <a class="btn btn-ghost" href="<?= e($page['slug'] === 'home' ? url('site') : url('page', ['p' => $page['slug']])) ?>" target="_blank">👁 Ansehen</a>
</div>
<?php require __DIR__ . '/_errors.php'; ?>
<form method="post" action="<?= e(url('admin/site/page')) ?>" class="panel">
    <?= csrf_field() ?>
    <input type="hidden" name="slug" value="<?= e($page['slug']) ?>">
    <div class="row2">
        <div class="field">
            <label for="s-title">Titel / Menüpunkt</label>
            <input id="s-title" name="title" value="<?= e($page['title']) ?>" maxlength="150" required>
        </div>
        <div class="field">
            <span class="label">Sichtbarkeit</span>
            <label class="check"><input type="checkbox" name="enabled" value="1"<?= checked((int)$page['enabled'] === 1) ?>> Seite ist aktiv</label>
        </div>
    </div>
    <?php if ($page['slug'] === 'faq'): ?><p class="hint">Dieser Text steht über den Fragen. Die Fragen selbst pflegst du unter <a href="<?= e(url('admin/site/faq')) ?>">FAQ</a>.</p><?php endif; ?>
    <?php $editorName = 'body'; $editorLabel = 'Inhalt'; $editorValue = (string)$page['body']; require __DIR__ . '/../../partials/editor.php'; ?>
    <div class="btnrow">
        <button class="btn btn-primary" type="submit">💾 Speichern</button>
        <a class="btn btn-ghost" href="<?= e(url('admin/site')) ?>">Zurück</a>
    </div>
</form>
