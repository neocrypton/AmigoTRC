<div class="pagehead">
    <div>
        <p class="crumbs"><a href="<?= e(url($target === 'collection' ? 'collection' : 'plays')) ?>"><?= e($label) ?></a></p>
        <h1 class="h1">Cover &amp; Daten ergänzen</h1>
    </div>
    <div class="btnrow">
        <a class="btn<?= $target === 'collection' ? ' btn-primary' : '' ?>" href="<?= e(url('scrape', ['target' => 'collection'])) ?>">Sammlung</a>
        <a class="btn<?= $target === 'plays' ? ' btn-primary' : '' ?>" href="<?= e(url('scrape', ['target' => 'plays'])) ?>">Durchgespielt</a>
    </div>
</div>

<section class="stats stats-sm">
    <div class="stat"><span class="stat-num" data-scrape-todo><?= fmt_num($counts['todo']) ?></span><span class="stat-label">noch nicht gesucht</span></div>
    <div class="stat"><span class="stat-num" data-scrape-review><?= fmt_num($counts['review']) ?></span><span class="stat-label">bitte prüfen</span></div>
    <div class="stat"><span class="stat-num" data-scrape-none><?= fmt_num($counts['none']) ?></span><span class="stat-label">nichts gefunden</span></div>
</section>

<?php if (!$enabled): ?>
<div class="flash flash-warn">Für die Suche brauchst du einen TheGamesDB-API-Key – hinterlege ihn unter <a href="<?= e(url('account')) ?>">Mein Konto</a>.</div>
<?php elseif ($counts['open'] === 0): ?>
<div class="panel empty-state">
    <p class="logo-type">ALL CLEAR</p>
    <p>Alle Spiele deiner <?= e($label) ?> sind mit TheGamesDB verknüpft.</p>
</div>
<?php else: ?>
<section class="panel">
    <h2 class="panel-title">Automatisch zuordnen</h2>
    <p>AMIGO sucht für jedes Spiel ohne Verknüpfung bei TheGamesDB. <strong>Eindeutige Treffer</strong> (gleicher Titel auf der gleichen Plattform) werden direkt verknüpft – Cover, Entwickler, Genre und Erscheinungsdatum kommen dazu. Unklare Fälle landen unten zum Prüfen.</p>
    <p class="hint">Pro Spiel ca. 1–2 Anfragen an TheGamesDB<?= $allowance !== null ? ' · dein Restkontingent: <strong>' . fmt_num($allowance) . '</strong>' : '' ?>. Bei weniger als <?= \Amigo\Service\Scraper::MIN_ALLOWANCE ?> übrigen Anfragen wird angehalten.</p>
    <?php if ($counts['todo'] > 0): ?>
    <form method="post" action="<?= e(url('scrape/run')) ?>" data-scrape-run>
        <?= csrf_field() ?>
        <input type="hidden" name="target" value="<?= e($target) ?>">
        <button class="btn btn-primary" type="submit">▶ <?= fmt_num($counts['todo']) ?> Spiele durchsuchen</button>
        <noscript><p class="hint">Ohne JavaScript werden je Klick <?= (int)$batch ?> Spiele verarbeitet.</p></noscript>
    </form>
    <div class="scrape-progress" data-scrape-progress hidden>
        <progress max="<?= (int)$counts['todo'] ?>" value="0"></progress>
        <p class="muted" data-scrape-status>Starte…</p>
    </div>
    <?php else: ?>
    <p class="muted">Alle offenen Spiele wurden bereits einmal gesucht.</p>
    <?php endif; ?>
    <?php if ($counts['review'] + $counts['none'] > 0): ?>
    <form method="post" action="<?= e(url('scrape/reset')) ?>" class="subform">
        <?= csrf_field() ?>
        <input type="hidden" name="target" value="<?= e($target) ?>">
        <button class="btn btn-sm btn-ghost" type="submit">↻ „Prüfen“ und „nichts gefunden“ erneut suchen lassen</button>
    </form>
    <?php endif; ?>
</section>

<?php if ($open): ?>
<section class="panel">
    <h2 class="panel-title">Bitte selbst zuordnen <span class="count"><?= count($open) ?></span></h2>
    <p class="hint">Antippen, passenden Treffer auswählen – fertig. Tipp: Bei „nichts gefunden“ hilft oft ein kürzerer oder englischer Suchbegriff.</p>
    <ul class="minilist">
        <?php foreach ($open as $r): ?>
        <li>
            <a href="<?= e(url('scrape/item', ['target' => $target, 'id' => $r['id']])) ?>">
                <span class="noimg" aria-hidden="true"><?= $r['scrape_status'] === 'review' ? '?' : '✕' ?></span>
                <span><strong><?= e($r['title']) ?></strong><small><?= e($r['platform'] ?? 'ohne Plattform') ?> · <?= $r['scrape_status'] === 'review' ? 'mehrere/ähnliche Treffer' : 'nichts gefunden' ?></small></span>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>
<?php endif; ?>
