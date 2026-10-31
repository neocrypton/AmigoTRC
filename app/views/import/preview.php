<?php
use Amigo\Core\Options;

$isCollection = $state['target'] === 'collection';
$skip = $state['skip_duplicates'] ?? true;
?>
<div class="pagehead">
    <div>
        <p class="crumbs"><a href="<?= e(url('import')) ?>">CSV-Import</a> › <?= e($state['name']) ?></p>
        <h1 class="h1">Vorschau</h1>
    </div>
    <form method="post" action="<?= e(url('import/cancel')) ?>"><?= csrf_field() ?><button class="btn btn-ghost" type="submit">Abbrechen</button></form>
</div>

<ol class="steps" aria-label="Ablauf">
    <li><span>1</span> Hochladen</li>
    <li><a href="<?= e(url('import/map')) ?>"><span>2</span> Spalten zuordnen</a></li>
    <li aria-current="step"><span>3</span> Vorschau &amp; Import</li>
</ol>

<section class="stats stats-sm">
    <div class="stat"><span class="stat-num"><?= fmt_num($total - $errCount) ?></span><span class="stat-label">Zeilen importierbar</span></div>
    <div class="stat"><span class="stat-num"><?= fmt_num($warnCount) ?></span><span class="stat-label">mit Hinweisen</span></div>
    <div class="stat"><span class="stat-num"><?= fmt_num($errCount) ?></span><span class="stat-label">ohne Titel (übersprungen)</span></div>
</section>

<form method="post" action="<?= e(url('import/preview')) ?>">
    <?= csrf_field() ?>

    <?php if ($unknown): ?>
    <section class="panel">
        <h2 class="panel-title">Unbekannte Plattformen zuordnen <span class="count"><?= count($unknown) ?></span></h2>
        <p class="hint">Diese Plattform-Namen aus deiner Datei wurden nicht automatisch erkannt. Wähle die passende Plattform – oder lass sie leer, dann landet der Name in den <?= $isCollection ? 'Notizen' : 'Bewertungstext' ?>.<?= $platforms ? '' : ' Es sind noch keine Plattformen angelegt.' ?></p>
        <div class="pmap">
            <?php $n = 0; foreach ($unknown as $value => $count): $choice = $state['platforms'][$value] ?? 'note'; ?>
            <div class="pmap-row">
                <label for="pmap-<?= $n ?>"><strong><?= e($value) ?></strong> <small class="muted"><?= (int)$count ?>×</small></label>
                <select id="pmap-<?= $n ?>" name="pmap[<?= $n ?>]">
                    <option value="note">– keine Plattform, Namen in Notiz –</option>
                    <?php foreach ($platforms as $p): ?>
                    <option value="<?= (int)$p['id'] ?>"<?= selected($p['id'], $choice) ?>><?= e($p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php $n++; endforeach; ?>
        </div>
        <?php if (\Amigo\Core\Auth::isAdmin()): ?><p class="hint">Fehlt eine Plattform? Unter <a href="<?= e(url('admin/platforms')) ?>" target="_blank">Admin › Plattformen</a> importieren oder anlegen, dann diese Seite neu laden.</p><?php endif; ?>
        <button class="btn" type="submit" name="action" value="update">Zuordnung übernehmen &amp; Vorschau aktualisieren</button>
    </section>
    <?php endif; ?>

    <section class="panel">
        <h2 class="panel-title">So werden die ersten Zeilen importiert</h2>
        <div class="tablewrap">
        <table class="tbl">
            <thead>
                <tr>
                    <th>Zeile</th><th>Titel</th><th>Plattform</th>
                    <?php if ($isCollection): ?><th>Region</th><th>Umfang</th><th>Zustand</th><th>Preis</th><th>Kaufdatum</th>
                    <?php else: ?><th>Datum</th><th>Spielzeit</th><th>Punkte</th><th>Wertung</th><?php endif; ?>
                    <th>Hinweise</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($preview as $i => $rec): $d = $rec['data']; ?>
                <tr<?= $rec['error'] ? ' class="is-off"' : '' ?>>
                    <td data-label="Zeile"><?= $i + $offset ?></td>
                    <?php if ($rec['error']): ?>
                    <td data-label="Titel" colspan="<?= $isCollection ? 8 : 7 ?>"><span class="cond cond-0">übersprungen: <?= e($rec['error']) ?></span></td>
                    <?php else: ?>
                    <td data-label="Titel"><?= e($d['title']) ?><?= $d['game_id'] ? ' <small class="chip" title="Mit Spiel im Katalog verknüpft">🔗</small>' : '' ?></td>
                    <td data-label="Plattform"><?= e($d['platform_id'] ? ($platformNames[$d['platform_id']] ?? '') : '–') ?></td>
                    <?php if ($isCollection): ?>
                    <td data-label="Region"><?= e($d['region'] ?? '') ?></td>
                    <td data-label="Umfang"><?= e(Options::COMPLETENESS[$d['completeness']] ?? '') ?></td>
                    <td data-label="Zustand"><?= $d['cond_overall'] !== null ? cond_badge($d['cond_overall']) : '' ?></td>
                    <td data-label="Preis"><?= e(fmt_money($d['price_cents'])) ?></td>
                    <td data-label="Kaufdatum"><?= e(fmt_date($d['purchase_date'])) ?></td>
                    <?php else: ?>
                    <td data-label="Datum"><?= e(fmt_date($d['finished_on'])) ?></td>
                    <td data-label="Spielzeit"><?= e(fmt_playtime($d['playtime_minutes'])) ?></td>
                    <td data-label="Punkte"><?= $d['score'] !== null ? e(fmt_num($d['score'])) : '' ?></td>
                    <td data-label="Wertung"><?= stars($d['rating'], false) ?></td>
                    <?php endif; ?>
                    <td data-label="Hinweise"><?php foreach ($rec['warnings'] as $w): ?><small class="warnline">⚠ <?= e($w) ?></small><?php endforeach; ?></td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <p class="hint">Gezeigt werden die ersten <?= count($preview) ?> von <?= fmt_num($total) ?> Zeilen. Werte mit Hinweis werden leer gelassen, der Rest der Zeile wird trotzdem importiert.</p>
    </section>

    <section class="panel">
        <h2 class="panel-title">Importieren</h2>
        <label class="check"><input type="checkbox" name="skip_duplicates" value="1"<?= checked($skip) ?>> Einträge überspringen, die es in meiner <?= e($targetLabel) ?> schon gibt (gleicher Titel + Plattform)</label>
        <div class="btnrow">
            <button class="btn btn-primary" type="submit" name="action" value="run" data-confirm="Jetzt <?= fmt_num($total - $errCount) ?> Zeilen in deine <?= e($targetLabel) ?> importieren?">▶ Jetzt importieren</button>
            <a class="btn btn-ghost" href="<?= e(url('import/map')) ?>">◀ Zuordnung ändern</a>
        </div>
    </section>
</form>
