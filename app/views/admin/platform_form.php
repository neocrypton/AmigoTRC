<?php $v = static fn (string $k) => $platform[$k] ?? ''; ?>
<div class="pagehead">
    <h1 class="h1"><?= e($title) ?></h1>
    <a class="btn btn-ghost" href="<?= e(url('admin/platforms')) ?>">Abbrechen</a>
</div>

<form method="post" action="<?= e(url('admin/platform/edit')) ?>" class="panel">
    <?= csrf_field() ?>
    <?php if (!empty($platform['id'])): ?><input type="hidden" name="id" value="<?= (int)$platform['id'] ?>"><?php endif; ?>
    <?php if (!empty($platform['tgdb_id'])): ?><p class="hint">Stammt aus TheGamesDB – ein erneuter Import überschreibt Änderungen.</p><?php endif; ?>
    <div class="row2">
        <div class="field"><label for="p-name">Name <span class="req">*</span></label><input id="p-name" name="name" value="<?= e($v('name')) ?>" maxlength="150" required></div>
        <div class="field"><label for="p-alias">Alias</label><input id="p-alias" name="alias" value="<?= e($v('alias')) ?>" maxlength="150"></div>
        <div class="field"><label for="p-man">Hersteller</label><input id="p-man" name="manufacturer" value="<?= e($v('manufacturer')) ?>" maxlength="150"></div>
        <div class="field"><label for="p-dev">Entwickler</label><input id="p-dev" name="developer" value="<?= e($v('developer')) ?>" maxlength="150"></div>
        <div class="field"><label for="p-media">Medium</label><input id="p-media" name="media" value="<?= e($v('media')) ?>" maxlength="100" placeholder="Modul, Diskette, CD…"></div>
        <div class="field"><label for="p-cpu">CPU</label><input id="p-cpu" name="cpu" value="<?= e($v('cpu')) ?>" maxlength="255"></div>
        <div class="field"><label for="p-mem">Speicher</label><input id="p-mem" name="memory" value="<?= e($v('memory')) ?>" maxlength="255"></div>
    </div>
    <div class="field"><label for="p-ov">Beschreibung</label><textarea id="p-ov" name="overview" rows="5" maxlength="10000"><?= e($v('overview')) ?></textarea></div>
    <button class="btn btn-primary" type="submit">💾 Speichern</button>
</form>
