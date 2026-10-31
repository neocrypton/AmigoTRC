<section class="panel gameover">
    <p class="gameover-code"><?= fmt_num($result['imported']) ?></p>
    <h1 class="logo-type">IMPORT COMPLETE</h1>
    <p><?= fmt_num($result['imported']) ?> Einträge importiert<?= $result['skipped'] ? ', ' . fmt_num($result['skipped']) . ' Duplikate übersprungen' : '' ?><?= $result['failed'] ? ', ' . count($result['failed']) . ' Zeilen ohne Titel ausgelassen' : '' ?>.</p>
    <?php if ($result['failed']): ?>
    <details>
        <summary>Ausgelassene Zeilen anzeigen</summary>
        <p class="muted"><?= e(implode(', ', array_map(fn ($i) => (string)($i + $offset), array_slice(array_keys($result['failed']), 0, 200)))) ?></p>
    </details>
    <?php endif; ?>
    <div class="btnrow center-row">
        <a class="btn btn-primary" href="<?= e(url($target === 'collection' ? 'collection' : 'plays', $target === 'collection' ? ['sort' => 'new'] : [])) ?>">▶ Zur <?= $target === 'collection' ? 'Sammlung' : 'Durchgespielt-Liste' ?></a>
        <a class="btn" href="<?= e(url('scrape', ['target' => $target])) ?>">🔍 Cover &amp; Daten von TheGamesDB ergänzen</a>
        <a class="btn" href="<?= e(url('import')) ?>">Weitere Datei importieren</a>
    </div>
</section>
