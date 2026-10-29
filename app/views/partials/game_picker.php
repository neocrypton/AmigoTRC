<?php
/**
 * Spielauswahl mit Live-Suche (TheGamesDB + lokaler Katalog).
 * Ohne JavaScript bleibt ein normales Titel-Feld + Plattform-Auswahl.
 *
 * @var array $record  Datensatz (title, game_id, platform_id, _game)
 * @var array $platforms
 * @var bool  $tgdb
 */
$g = $record['_game'] ?? null;
?>
<div class="picker" data-picker data-api="<?= e(url('api/games')) ?>">
    <div class="field">
        <label for="f-title">Titel <span class="req">*</span></label>
        <input id="f-title" name="title" value="<?= e($record['title'] ?? '') ?>" maxlength="255" autocomplete="off"
               placeholder="<?= $tgdb ? 'Titel eingeben – Vorschläge von TheGamesDB' : 'Titel eingeben' ?>" data-picker-input required>
        <input type="hidden" name="game_id" value="<?= e($record['game_id'] ?? '') ?>" data-picker-game>
        <input type="hidden" name="tgdb_game_id" value="" data-picker-tgdb>
        <div class="picker-list" data-picker-list hidden role="listbox"></div>
        <p class="hint" data-picker-status><?= $tgdb ? 'Ab 2 Buchstaben wird gesucht. Auswahl übernimmt Plattform & Cover.' : 'Tipp: Mit einem TheGamesDB-API-Key gibt es Vorschläge.' ?></p>
    </div>
    <div class="picker-linked<?= $g ? '' : ' is-empty' ?>" data-picker-linked>
        <?php $thumb = tgdb_image($g['boxart_thumb'] ?? null); ?>
        <img<?= $thumb ? ' src="' . e($thumb) . '"' : ' hidden' ?> alt="" width="48" height="64" loading="lazy" data-picker-thumb>
        <span>Verknüpft mit: <strong data-picker-name><?= e($g['title'] ?? '') ?></strong></span>
        <button type="button" class="btn btn-sm btn-ghost" data-picker-clear>Lösen</button>
    </div>
    <div class="field">
        <label for="f-platform">Plattform</label>
        <select id="f-platform" name="platform_id" data-picker-platform>
            <option value="">– keine / unbekannt –</option>
            <?php foreach ($platforms as $p): ?>
            <option value="<?= (int)$p['id'] ?>"<?= selected($p['id'], $record['platform_id'] ?? '') ?>><?= e($p['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if (!$platforms): ?><p class="hint">Noch keine Plattformen vorhanden – sie werden beim Wählen eines Spiels automatisch angelegt<?= \Amigo\Core\Auth::isAdmin() ? ' oder unter Admin › Plattformen importiert' : '' ?>.</p><?php endif; ?>
    </div>
</div>
