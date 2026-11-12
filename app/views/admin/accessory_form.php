<div class="pagehead">
    <h1 class="h1"><?= e($title) ?></h1>
    <a class="btn btn-ghost" href="<?= e(url('admin/accessories')) ?>">Abbrechen</a>
</div>

<?php if ($errors): ?><div class="flash flash-error"><?php foreach ($errors as $err): ?><p><?= e($err) ?></p><?php endforeach; ?></div><?php endif; ?>

<form method="post" action="<?= e(url('admin/accessory/edit')) ?>" class="form panel">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int)$acc['id'] ?>">
    <div class="field">
        <label for="a-name">Name <span class="req">*</span></label>
        <input id="a-name" name="name" value="<?= e($acc['name']) ?>" maxlength="150" required>
    </div>
    <div class="row2">
        <div class="field">
            <label for="a-platform">Plattform</label>
            <select id="a-platform" name="platform_id">
                <option value="">– keine / plattformübergreifend –</option>
                <?php foreach ($platforms as $p): ?>
                <option value="<?= (int)$p['id'] ?>"<?= selected($p['id'], $acc['platform_id'] ?? '') ?>><?= e($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="a-maker">Hersteller</label>
            <input id="a-maker" name="manufacturer" value="<?= e($acc['manufacturer'] ?? '') ?>" maxlength="150">
        </div>
    </div>
    <p class="hint">Änderungen gelten für alle Sammlungen, die diesen Eintrag nutzen. Die Titel in den Sammlungen selbst bleiben, wie die Nutzer sie eingetragen haben.</p>
    <div class="btnrow"><button class="btn btn-primary" type="submit">Speichern</button></div>
</form>
