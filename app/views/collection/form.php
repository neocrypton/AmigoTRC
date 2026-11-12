<?php
use Amigo\Core\Options;

$condSelect = static function (string $name, ?int $value, bool $allowMissing = true): string {
    $html = '<select name="' . e($name) . '" id="f-' . e($name) . '">';
    $html .= '<option value="">' . ($allowMissing ? '– nicht vorhanden –' : '– keine Angabe –') . '</option>';
    foreach (Options::CONDITIONS as $k => $label) {
        $html .= '<option value="' . $k . '"' . selected($k, $value ?? '') . '>' . $k . ' · ' . e($label) . '</option>';
    }
    return $html . '</select>';
};
$v = static fn (string $k) => $item[$k] ?? null;
$int = static fn (string $k) => isset($item[$k]) && $item[$k] !== null && $item[$k] !== '' ? (int)$item[$k] : null;
?>
<div class="pagehead">
    <h1 class="h1"><?= e($title) ?></h1>
    <?php if (!empty($item['id'])): ?><a class="btn btn-ghost" href="<?= e(url('item', ['id' => $item['id']])) ?>">Abbrechen</a><?php endif; ?>
</div>

<?php require __DIR__ . '/../partials/tgdb_hint.php'; ?>

<form method="post" action="<?= e(url('item/edit')) ?>" enctype="multipart/form-data" class="form">
    <?= csrf_field() ?>
    <?php if (!empty($item['id'])): ?><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><?php endif; ?>

    <section class="panel">
        <h2 class="panel-title">1 · Was ist es?</h2>
        <fieldset class="segmented">
            <legend class="sr">Art</legend>
            <?php foreach (Options::KINDS as $k => $label): ?>
            <label><input type="radio" name="kind" value="<?= e($k) ?>"<?= checked(($v('kind') ?? 'game') === $k) ?>><span><?= e($label) ?></span></label>
            <?php endforeach; ?>
        </fieldset>

        <?php $record = $item; $withAccessory = true; require __DIR__ . '/../partials/game_picker.php'; ?>

        <div class="row2">
            <div class="field">
                <label for="f-region">Region</label>
                <select id="f-region" name="region">
                    <option value="">– keine Angabe –</option>
                    <?php foreach (Options::REGIONS as $k => $label): ?>
                    <option value="<?= e($k) ?>"<?= selected($k, $v('region')) ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="f-completeness">Umfang</label>
                <select id="f-completeness" name="completeness">
                    <option value="">– keine Angabe –</option>
                    <?php foreach (Options::COMPLETENESS as $k => $label): ?>
                    <option value="<?= e($k) ?>"<?= selected($k, $v('completeness')) ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </section>

    <section class="panel">
        <h2 class="panel-title">2 · Zustand</h2>
        <div class="row2">
            <div class="field"><label for="f-cond_overall">Gesamtzustand</label><?= $condSelect('cond_overall', $int('cond_overall'), false) ?></div>
            <div class="field"><label for="f-cond_media">Modul / Disc / Gerät</label><?= $condSelect('cond_media', $int('cond_media')) ?></div>
            <div class="field"><label for="f-cond_box">Verpackung</label><?= $condSelect('cond_box', $int('cond_box')) ?></div>
            <div class="field"><label for="f-cond_manual">Anleitung</label><?= $condSelect('cond_manual', $int('cond_manual')) ?></div>
        </div>
        <div class="field">
            <label for="f-condition_notes">Zustandsbeschreibung</label>
            <textarea id="f-condition_notes" name="condition_notes" rows="3" maxlength="5000" placeholder="z. B. Label leicht ausgeblichen, Box mit Knick oben links, Batterie 2024 getauscht…"><?= e($v('condition_notes')) ?></textarea>
        </div>
    </section>

    <section class="panel" id="photos">
        <h2 class="panel-title">3 · Fotos</h2>
        <?php if ($photos): ?>
        <ul class="photo-edit">
            <?php foreach ($photos as $ph): ?>
            <li>
                <img src="<?= e(url('photo', ['id' => $ph['id'], 's' => 't'])) ?>" alt="" loading="lazy">
                <div class="photo-edit-fields">
                    <label class="sr" for="cap-<?= (int)$ph['id'] ?>">Beschreibung</label>
                    <input id="cap-<?= (int)$ph['id'] ?>" name="captions[<?= (int)$ph['id'] ?>]" value="<?= e($ph['caption']) ?>" maxlength="500" placeholder="Beschreibung zum Foto">
                    <div class="photo-edit-row">
                        <label>Pos. <input type="number" name="sorts[<?= (int)$ph['id'] ?>]" value="<?= (int)$ph['sort'] ?>" min="0" max="999" class="input-xs"></label>
                        <label class="check danger"><input type="checkbox" name="delete_photo[]" value="<?= (int)$ph['id'] ?>"> löschen</label>
                    </div>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
        <div class="field">
            <label for="f-photos" class="dropzone">
                <span class="dropzone-icon" aria-hidden="true">📷</span>
                <span data-dropzone-text>Fotos auswählen oder aufnehmen</span>
                <small class="muted">JPEG/PNG/WebP · max. <?= (int)$maxUpload['file'] ?> MB je Bild · bis <?= (int)$maxUpload['files'] ?> Bilder pro Speichern.<br>Mehrere Fotos nacheinander aufnehmen, dann einmal speichern. Große Fotos werden vor dem Hochladen automatisch verkleinert.</small>
            </label>
            <input id="f-photos" type="file" name="photos[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple class="file-input" data-photo-input data-max-files="<?= max(1, (int)$maxUpload['files']) ?>">
            <ul class="photo-new" data-photo-preview></ul>
            <noscript><p class="hint">Bildbeschreibungen können nach dem Speichern ergänzt werden.</p></noscript>
        </div>
        <div class="only-accessory catalog-photo-note">
            <?php if ($accessory && $accessory['image'] && !$photos): ?>
            <p class="hint">🖼 Dein Eintrag zeigt das <strong>Standardbild aus dem gemeinsamen Katalog</strong>. Eigene Fotos ersetzen es – nur für deinen Eintrag.</p>
            <?php elseif (!$accessory || !$accessory['image']): ?>
            <p class="hint">🖼 Hat dieses Zubehör im gemeinsamen Katalog noch kein Bild, wird dein <strong>erstes Foto</strong> zum Standardbild – sichtbar für alle angemeldeten Nutzer, die das Teil eintragen.</p>
            <label class="check"><input type="checkbox" name="no_share" value="1"> Meine Fotos nicht als Standardbild verwenden</label>
            <?php endif; ?>
        </div>
    </section>

    <section class="panel">
        <h2 class="panel-title">4 · Kauf &amp; Notizen <small class="muted">optional · immer privat</small></h2>
        <div class="row3">
            <div class="field"><label for="f-purchase_date">Kaufdatum</label><input id="f-purchase_date" type="date" name="purchase_date" value="<?= e($v('purchase_date')) ?>"></div>
            <div class="field"><label for="f-price">Preis (€)</label><input id="f-price" name="price" inputmode="decimal" value="<?= e(isset($item['price_cents']) ? money_input($int('price_cents')) : '') ?>" placeholder="0,00" maxlength="20"></div>
            <div class="field"><label for="f-place">Gekauft bei</label><input id="f-place" name="purchase_place" value="<?= e($v('purchase_place')) ?>" maxlength="150" placeholder="Flohmarkt, eBay, …"></div>
        </div>
        <div class="field">
            <label for="f-notes">Notizen</label>
            <textarea id="f-notes" name="notes" rows="3" maxlength="10000"><?= e($v('notes')) ?></textarea>
        </div>
    </section>

    <div class="formbar">
        <button class="btn btn-primary" type="submit">💾 Speichern</button>
        <span class="muted" data-upload-status></span>
    </div>
</form>
