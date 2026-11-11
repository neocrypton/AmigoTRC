<?php use Amigo\Controller\SiteAdminController; ?>
<div class="pagehead">
    <div>
        <p class="crumbs"><a href="<?= e(url('admin/site')) ?>">Website</a> › <a href="<?= e(url('admin/site/news')) ?>">News</a></p>
        <h1 class="h1"><?= e($title) ?></h1>
    </div>
</div>
<?php require __DIR__ . '/_errors.php'; ?>
<form method="post" action="<?= e(url('admin/site/news/edit')) ?>" class="panel">
    <?= csrf_field() ?>
    <?php if (!empty($item['id'])): ?><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><?php endif; ?>
    <div class="field">
        <label for="n-title">Titel</label>
        <input id="n-title" name="title" value="<?= e($item['title'] ?? '') ?>" maxlength="200" required>
    </div>
    <div class="row2">
        <div class="field">
            <label for="n-date">Veröffentlichen am</label>
            <input id="n-date" name="published_at" type="datetime-local" value="<?= e(SiteAdminController::utcToLocal($item['published_at'] ?? null)) ?>">
            <p class="hint">Ein Datum in der Zukunft plant die Meldung – sie erscheint dann automatisch.</p>
        </div>
        <div class="field">
            <span class="label">Status</span>
            <label class="check"><input type="checkbox" name="published" value="1"<?= checked((int)($item['published'] ?? 1) === 1) ?>> Veröffentlicht (sonst Entwurf)</label>
        </div>
    </div>
    <?php $editorName = 'body'; $editorLabel = 'Text'; $editorValue = (string)($item['body'] ?? ''); require __DIR__ . '/../../partials/editor.php'; ?>
    <div class="btnrow">
        <button class="btn btn-primary" type="submit">💾 Speichern</button>
        <a class="btn btn-ghost" href="<?= e(url('admin/site/news')) ?>">Abbrechen</a>
    </div>
</form>
