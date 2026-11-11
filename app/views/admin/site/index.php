<?php use Amigo\Service\Site; ?>
<div class="pagehead">
    <div>
        <p class="crumbs"><a href="<?= e(url('admin/settings')) ?>">Admin</a></p>
        <h1 class="h1">Website</h1>
    </div>
</div>
<?php $siteTab = 'index'; require __DIR__ . '/_nav.php'; ?>

<?php
$legalOff = array_filter(['imprint', 'privacy'], static fn ($s) => empty($pages[$s]['enabled']));
if ($siteEnabled && $legalOff): ?>
<div class="flash flash-warn">⚠ Die Website ist öffentlich, aber <?= e(implode(' und ', array_map(static fn ($s) => $pages[$s]['title'] ?? $s, $legalOff))) ?> <?= count($legalOff) > 1 ? 'sind' : 'ist' ?> noch ausgeschaltet. In Deutschland sind Impressum und Datenschutzerklärung meist Pflicht – bitte ausfüllen und aktivieren.</div>
<?php endif; ?>

<div class="cols">
    <section class="panel">
        <h2 class="panel-title">Einstellungen</h2>
        <form method="post" action="<?= e(url('admin/site')) ?>">
            <?= csrf_field() ?>
            <label class="check"><input type="checkbox" name="site_enabled" value="1"<?= checked($siteEnabled) ?>> Öffentliche Website einschalten</label>
            <p class="hint">Eingeschaltet sehen Besucher auf der Startadresse die Website (Start, Installation, FAQ, News, Links). Ausgeschaltet erscheint wie bisher nur die Anmeldung. Impressum und Datenschutz sind unabhängig davon erreichbar, sobald sie aktiviert sind, und stehen dann auch im Login und in der App unten.</p>
            <label class="check"><input type="checkbox" name="news_enabled" value="1"<?= checked($newsEnabled) ?>> News-Bereich anzeigen</label>
            <p class="hint">Zeigt den Menüpunkt „News“ und die neuesten drei Meldungen auf der Startseite.</p>
            <button class="btn btn-primary" type="submit">Speichern</button>
        </form>
    </section>

    <section class="panel">
        <h2 class="panel-title">Inhalte</h2>
        <ul class="minilist">
            <li><a href="<?= e(url('admin/site/faq')) ?>"><span><strong>❓ FAQ</strong><small><?= (int)$counts['faq'] ?> <?= $counts['faq'] === 1 ? 'Frage' : 'Fragen' ?></small></span></a></li>
            <li><a href="<?= e(url('admin/site/news')) ?>"><span><strong>📰 News</strong><small><?= (int)$counts['news'] ?> <?= $counts['news'] === 1 ? 'Meldung' : 'Meldungen' ?><?= $newsEnabled ? '' : ' · Bereich ausgeschaltet' ?></small></span></a></li>
            <li><a href="<?= e(url('admin/site/links')) ?>"><span><strong>🔗 Links &amp; Partner</strong><small><?= (int)$counts['links'] ?> <?= $counts['links'] === 1 ? 'Eintrag' : 'Einträge' ?> in der Seitenleiste</small></span></a></li>
        </ul>
    </section>
</div>

<section class="panel">
    <h2 class="panel-title">Seiten</h2>
    <div class="tablewrap">
    <table class="tbl">
        <thead><tr><th>Seite</th><th>Bereich</th><th>Status</th><th>Geändert</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($pages as $slug => $p): ?>
        <tr<?= (int)$p['enabled'] ? '' : ' class="is-off"' ?>>
            <td data-label="Seite"><strong><?= e($p['title']) ?></strong></td>
            <td data-label="Bereich"><?= Site::PAGES[$slug][1] ? 'Website' : 'Rechtliches' ?></td>
            <td data-label="Status"><?= (int)$p['enabled'] ? '<span class="cond cond-5">AKTIV</span>' : '<span class="cond cond-1">AUS</span>' ?></td>
            <td data-label="Geändert"><?= e(fmt_datetime($p['updated_at'])) ?></td>
            <td data-label="Aktionen">
                <div class="btnrow">
                    <a class="btn btn-sm" href="<?= e(url('admin/site/page', ['p' => $slug])) ?>">✎ Bearbeiten</a>
                    <a class="btn btn-sm btn-ghost" href="<?= e($slug === 'home' ? url('site') : url('page', ['p' => $slug])) ?>" target="_blank">👁</a>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <p class="hint">Der Titel ist zugleich der Menüpunkt. Impressum und Datenschutz sind Vorlagen – bitte die Angaben in [eckigen Klammern] ersetzen, bevor du sie aktivierst.</p>
</section>
