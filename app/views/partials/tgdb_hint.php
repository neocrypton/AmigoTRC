<?php
/**
 * Hinweis „eigener TheGamesDB-API-Key nötig“ – nur wenn der angemeldete Nutzer
 * weder einen eigenen Key hat noch den System-Key mitnutzen darf.
 */
use Amigo\Core\Auth;
use Amigo\Service\Catalog;

$hintUser = Auth::user();
if (!$hintUser || Catalog::keySourceFor($hintUser) !== 'none') {
    return;
}
?>
<div class="flash flash-warn tgdb-hint">
    <strong>🔑 TheGamesDB-API-Key fehlt</strong><br>
    Für den vollen Funktionsumfang – Spielesuche mit Vorschlägen, Cover, Entwickler, Genre und Erscheinungsdatum – brauchst du einen eigenen, kostenlosen API-Key von TheGamesDB.
    <ol>
        <li><a href="https://thegamesdb.net/register.php" target="_blank" rel="noopener noreferrer">Bei TheGamesDB registrieren</a> und dort einen API-Key anfordern.</li>
        <li>Den Key unter <a href="<?= e(url('account')) ?>#tgdb">Mein Konto › TheGamesDB-API-Key</a> eintragen.</li>
    </ol>
    <?php if (Auth::isAdmin()): ?><p class="hint">Als Admin kannst du alternativ einen System-Key unter <a href="<?= e(url('admin/settings')) ?>">Admin › System</a> hinterlegen.</p><?php endif; ?>
</div>
