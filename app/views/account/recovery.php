<section class="panel form-narrow">
    <h1 class="panel-title">Recovery-Codes</h1>
    <div class="flash flash-ok" role="status"><?= e($message) ?></div>
    <p>Jeder Code funktioniert <strong>genau einmal</strong> anstelle des App-Codes. Diese Liste wird nur jetzt angezeigt.</p>
    <ol class="codes">
        <?php foreach ($codes as $c): ?><li><code><?= e($c) ?></code></li><?php endforeach; ?>
    </ol>
    <div class="btnrow">
        <button type="button" class="btn" data-copy="<?= e(implode("\n", $codes)) ?>">📋 Kopieren</button>
        <button type="button" class="btn" data-print>🖨 Drucken</button>
        <a class="btn btn-primary" href="<?= e(url('account')) ?>">Gespeichert – weiter</a>
    </div>
</section>
