<?php
/**
 * Auswahl mit Live-Suche: Spiele (TheGamesDB + lokaler Katalog) bzw. – in der Sammlung bei
 * Art „Zubehör“ – der gemeinsame Zubehör-Katalog. Bei „Konsole“ genügt die Plattform-Auswahl.
 * Ohne JavaScript bleibt ein normales Titel-Feld + Plattform-Auswahl.
 *
 * @var array $record         Datensatz (title, game_id, platform_id, _game, accessory_id)
 * @var array $platforms
 * @var bool  $tgdb
 * @var bool  $withAccessory  (optional) Sammlung: Konsole/Zubehör-Modus aktiv
 * @var ?array $accessory     (optional) verknüpfter Katalog-Eintrag
 */
$g = $record['_game'] ?? null;
$withAccessory ??= false;
$accessory ??= null;
$linkedName = $g['title'] ?? ($accessory['name'] ?? '');
$thumb = tgdb_image($g['boxart_thumb'] ?? null) ?? ($accessory ? \Amigo\Service\AccessoryCatalog::imageUrl($accessory) : null);
?>
<div class="picker" data-picker data-api="<?= e(url('api/games')) ?>"<?= $withAccessory ? ' data-api-accessory="' . e(url('api/accessories')) . '"' : '' ?>>
    <div class="field">
        <label for="f-title">
            <span class="not-console not-accessory">Titel <span class="req">*</span></span>
            <?php if ($withAccessory): ?>
            <span class="only-console">Bezeichnung <small class="muted">(optional, z. B. „SNES Mini“ – sonst der Name der Plattform)</small></span>
            <span class="only-accessory">Zubehör <span class="req">*</span></span>
            <?php endif; ?>
        </label>
        <input id="f-title" name="title" value="<?= e($record['title'] ?? '') ?>" maxlength="255" autocomplete="off"
               placeholder="<?= $tgdb ? 'Titel eingeben – Vorschläge von TheGamesDB' : 'Titel eingeben' ?>" data-picker-input<?= $withAccessory ? ' data-ph-console="leer lassen = Name der Plattform" data-ph-accessory="z. B. DualShock 2, Memory Card, Rumble Pak"' : ' required' ?>>
        <input type="hidden" name="game_id" value="<?= e($record['game_id'] ?? '') ?>" data-picker-game>
        <input type="hidden" name="tgdb_game_id" value="" data-picker-tgdb>
        <?php if ($withAccessory): ?><input type="hidden" name="accessory_id" value="<?= e($record['accessory_id'] ?? '') ?>" data-picker-accessory><?php endif; ?>
        <div class="picker-list" data-picker-list hidden role="listbox"></div>
        <p class="hint" data-picker-status><?= $tgdb ? 'Ab 2 Buchstaben wird gesucht. Auswahl übernimmt Plattform & Cover.' : 'Vorschläge aus dem gemeinsamen Katalog. Für neue Spiele aus TheGamesDB unter „Mein Konto“ einen eigenen API-Key hinterlegen.' ?></p>
        <?php if ($withAccessory): ?>
        <p class="hint only-console">Konsolen und Handhelds wählst du unten über die <strong>Plattform</strong> – Daten und Bild kommen von TheGamesDB.</p>
        <p class="hint only-accessory">Tippe z. B. „DualShock 2“ – Vorschläge kommen aus dem <strong>gemeinsamen Zubehör-Katalog</strong>. Gibt es das Teil noch nicht, wird es beim Speichern angelegt und steht danach allen zur Verfügung.</p>
        <?php endif; ?>
    </div>
    <div class="picker-linked<?= $linkedName !== '' && ($g || $accessory) ? '' : ' is-empty' ?>" data-picker-linked>
        <img<?= $thumb ? ' src="' . e($thumb) . '"' : ' hidden' ?> alt="" width="48" height="64" loading="lazy" data-picker-thumb>
        <span>Verknüpft mit: <strong data-picker-name><?= e($linkedName . (($accessory['platform_name'] ?? '') ? ' (' . $accessory['platform_name'] . ')' : '')) ?></strong></span>
        <button type="button" class="btn btn-sm btn-ghost" data-picker-clear>Lösen</button>
    </div>
    <div class="field">
        <label for="f-platform">Plattform<?php if ($withAccessory): ?><span class="only-console"> / Konsole <span class="req">*</span></span><?php endif; ?></label>
        <select id="f-platform" name="platform_id" data-picker-platform>
            <option value="">– keine / unbekannt –</option>
            <?php foreach ($platforms as $p): ?>
            <option value="<?= (int)$p['id'] ?>"<?= selected($p['id'], $record['platform_id'] ?? '') ?>><?= e($p['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if (!$platforms): ?><p class="hint">Noch keine Plattformen vorhanden – sie werden beim Wählen eines Spiels automatisch angelegt<?= \Amigo\Core\Auth::isAdmin() ? ' oder unter Admin › Plattformen importiert' : '' ?>.</p><?php endif; ?>
        <?php if ($withAccessory): ?><p class="hint only-console">Konsole fehlt in der Liste? <?= \Amigo\Core\Auth::isAdmin() ? 'Unter Admin › Plattformen aus TheGamesDB importieren.' : 'Ein Admin kann sie aus TheGamesDB importieren.' ?></p><?php endif; ?>
    </div>
    <?php if ($withAccessory): ?>
    <div class="field only-accessory">
        <label for="f-acc-maker">Hersteller <small class="muted">(nur für neue Katalog-Einträge)</small></label>
        <input id="f-acc-maker" name="accessory_manufacturer" maxlength="150" value="<?= e($accessory['manufacturer'] ?? '') ?>" placeholder="z. B. Sony, Nintendo, Hori, Mad Catz">
    </div>
    <?php endif; ?>
</div>
