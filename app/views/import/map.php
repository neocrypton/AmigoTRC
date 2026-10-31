<?php $delimiters = [';' => 'Semikolon ;', ',' => 'Komma ,', "\t" => 'Tabulator', '|' => 'Senkrechter Strich |']; ?>
<div class="pagehead">
    <div>
        <p class="crumbs"><a href="<?= e(url('import')) ?>">CSV-Import</a> › <?= e($state['name']) ?></p>
        <h1 class="h1">Spalten zuordnen</h1>
    </div>
    <form method="post" action="<?= e(url('import/cancel')) ?>"><?= csrf_field() ?><button class="btn btn-ghost" type="submit">Abbrechen</button></form>
</div>

<ol class="steps" aria-label="Ablauf">
    <li><span>1</span> Hochladen</li>
    <li aria-current="step"><span>2</span> Spalten zuordnen</li>
    <li><span>3</span> Vorschau &amp; Import</li>
</ol>

<p>Ziel: <strong><?= e($targetLabel) ?></strong> · <?= fmt_num($total) ?> Zeilen · Zeichensatz <?= e($state['encoding']) ?></p>
<?php if ($tooMany): ?><div class="flash flash-warn">Die Datei hat mehr als <?= fmt_num(\Amigo\Service\CsvImport::MAX_ROWS) ?> Zeilen – es werden nur die ersten <?= fmt_num(\Amigo\Service\CsvImport::MAX_ROWS) ?> importiert. Teile größere Dateien bitte auf.</div><?php endif; ?>

<form method="post" action="<?= e(url('import/map')) ?>" class="panel">
    <?= csrf_field() ?>
    <h2 class="panel-title">Spalte → AMIGO-Feld</h2>
    <p class="hint">Für jede Spalte deiner Datei das passende Feld wählen. Spalten mit „– ignorieren –“ werden nicht übernommen. Pflicht ist nur der <strong>Titel</strong>.</p>
    <div class="mapgrid">
        <?php foreach ($cols as $i => $col): $current = $state['mapping'][$i] ?? ''; ?>
        <div class="mapcol<?= $current !== '' ? ' is-mapped' : '' ?>">
            <label for="map-<?= $i ?>" class="mapcol-head"><?= e($col) ?></label>
            <ul class="mapcol-samples">
                <?php foreach ($samples as $row): $v = (string)($row[$i] ?? ''); ?>
                <li><?= $v === '' ? '<span class="muted">leer</span>' : e(mb_strimwidth($v, 0, 40, '…')) ?></li>
                <?php endforeach; ?>
            </ul>
            <select id="map-<?= $i ?>" name="map[<?= $i ?>]" data-map-select>
                <option value="">– ignorieren –</option>
                <?php foreach ($fields as $key => [$label]): ?>
                <option value="<?= e($key) ?>"<?= selected($key, $current) ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="formbar">
        <button class="btn btn-primary" type="submit">Weiter zur Vorschau ▶</button>
    </div>
</form>

<details class="panel">
    <summary>Spalten sehen falsch aus? Trennzeichen ändern</summary>
    <form method="post" action="<?= e(url('import/map')) ?>" class="filters">
        <?= csrf_field() ?>
        <select name="delimiter" aria-label="Trennzeichen">
            <?php foreach ($delimiters as $d => $label): ?>
            <option value="<?= e($d) ?>"<?= selected($d, $state['delimiter']) ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn" type="submit">Neu einlesen</button>
    </form>
</details>
