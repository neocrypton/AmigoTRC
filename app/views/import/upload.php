<div class="pagehead">
    <div>
        <p class="crumbs"><a href="<?= e(url('account')) ?>">Mein Konto</a></p>
        <h1 class="h1">CSV-Import</h1>
    </div>
</div>

<ol class="steps" aria-label="Ablauf">
    <li aria-current="step"><span>1</span> Hochladen</li>
    <li><span>2</span> Spalten zuordnen</li>
    <li><span>3</span> Vorschau &amp; Import</li>
</ol>

<div class="cols">
    <form method="post" action="<?= e(url('import')) ?>" enctype="multipart/form-data" class="panel">
        <?= csrf_field() ?>
        <h2 class="panel-title">Datei hochladen</h2>
        <fieldset class="segmented">
            <legend class="label">Importieren in</legend>
            <?php foreach ($targets as $k => $label): ?>
            <label><input type="radio" name="target" value="<?= e($k) ?>"<?= checked($target === $k) ?>><span><?= e($label) ?></span></label>
            <?php endforeach; ?>
        </fieldset>
        <div class="field">
            <label for="csv">CSV-Datei</label>
            <input id="csv" type="file" name="csv" accept=".csv,.txt,.tsv,text/csv,text/plain" required>
            <p class="hint">Max. 5 MB und 5.000 Zeilen. Trennzeichen (; , Tab |) und Zeichensatz (UTF-8 oder Excel/Windows) werden automatisch erkannt.</p>
        </div>
        <label class="check"><input type="checkbox" name="has_header" value="1" checked> Erste Zeile enthält die Spaltennamen</label>
        <button class="btn btn-primary" type="submit">Weiter ▶</button>
    </form>

    <section class="panel">
        <h2 class="panel-title">Tipps</h2>
        <ul class="tips">
            <li><strong>Excel/LibreOffice/Google Sheets:</strong> „Speichern unter“ bzw. „Herunterladen als“ → <em>CSV</em>.</li>
            <li>Im nächsten Schritt ordnest du jede Spalte einem AMIGO-Feld zu – bekannte Spaltennamen (deutsch und englisch) werden vorgeschlagen.</li>
            <li>Werte wie „sehr gut“, „Mint“, „PAL“, „US“, „24,90 €“, „01.05.2024“, „4/5“ oder „12:30 h“ werden automatisch übersetzt.</li>
            <li>Plattformen werden mit den vorhandenen abgeglichen (auch „SNES“, „PS1“, „N64“ …). Unbekannte ordnest du in der Vorschau selbst zu.</li>
            <li>Ein AMIGO-Export (Mein Konto › CSV) lässt sich ohne Anpassung wieder importieren.</li>
            <li>Fotos lassen sich per CSV nicht übertragen – die kannst du danach bei den Einträgen ergänzen.</li>
        </ul>
        <details>
            <summary>Welche Felder gibt es?</summary>
            <?php foreach ($fields as $t => $list): ?>
            <p class="h3"><?= $t === 'collection' ? 'Sammlung' : 'Durchgespielt' ?></p>
            <p class="muted"><?= e(implode(' · ', array_map(fn ($f) => rtrim($f[0], ' *'), $list))) ?></p>
            <?php endforeach; ?>
        </details>
    </section>
</div>
