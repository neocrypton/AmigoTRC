<?php use Amigo\Service\Site; ?>
<div class="pagehead">
    <div>
        <p class="crumbs"><a href="<?= e(url('admin/site')) ?>">Website</a> › <a href="<?= e(url('admin/site/links')) ?>">Links &amp; Partner</a></p>
        <h1 class="h1"><?= e($title) ?></h1>
    </div>
</div>
<?php require __DIR__ . '/_errors.php'; ?>
<form method="post" action="<?= e(url('admin/site/link/edit')) ?>" enctype="multipart/form-data" class="panel form-narrow-wide">
    <?= csrf_field() ?>
    <?php if (!empty($item['id'])): ?><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><?php endif; ?>
    <div class="field">
        <label for="l-name">Name</label>
        <input id="l-name" name="name" value="<?= e($item['name'] ?? '') ?>" maxlength="100" required>
        <p class="hint">Wird als Textlink angezeigt bzw. als Alternativtext des Banners.</p>
    </div>
    <div class="field">
        <label for="l-url">Adresse</label>
        <input id="l-url" name="url" type="url" value="<?= e($item['url'] ?? '') ?>" maxlength="500" placeholder="https://" required>
    </div>
    <div class="field">
        <label for="l-banner">Banner <span class="muted">(optional, 160×50 px)</span></label>
        <?php if (!empty($item['banner'])): ?>
        <p><img src="<?= e(Site::fileUrl($item['banner'])) ?>" alt="Aktueller Banner" width="160" height="50" class="banner-thumb"></p>
        <label class="check"><input type="checkbox" name="remove_banner" value="1"> Banner entfernen (dann Textlink)</label>
        <?php endif; ?>
        <input id="l-banner" name="banner" type="file" accept="image/png,image/jpeg,image/gif,image/webp">
        <p class="hint">PNG, JPEG, GIF oder WebP bis 512 KB. Andere Größen werden mittig auf 160×50 zugeschnitten. Animierte GIFs in genau 160×50 bleiben animiert.</p>
    </div>
    <label class="check"><input type="checkbox" name="enabled" value="1"<?= checked((int)($item['enabled'] ?? 1) === 1) ?>> Link anzeigen</label>
    <div class="btnrow">
        <button class="btn btn-primary" type="submit">💾 Speichern</button>
        <a class="btn btn-ghost" href="<?= e(url('admin/site/links')) ?>">Abbrechen</a>
    </div>
</form>
