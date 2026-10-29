<div class="pagehead">
    <h1 class="h1">System</h1>
    <div class="btnrow">
        <a class="btn" href="<?= e(url('admin/users')) ?>">👥 Benutzer</a>
        <a class="btn" href="<?= e(url('admin/platforms')) ?>">📥 Plattformen</a>
    </div>
</div>

<?php if ($probe): ?>
<div class="flash flash-warn" data-probe="<?= e($probe) ?>" data-probe-ok="Datenverzeichnis ist korrekt gegen Webzugriff gesperrt." data-probe-fail="ACHTUNG: Das Datenverzeichnis ist über den Browser erreichbar! .htaccess wird nicht beachtet (nginx?). Bitte data/ außerhalb des Webroots verschieben oder den Zugriff sperren – siehe README.">
    Das Datenverzeichnis liegt im Webroot und ist nur per <code>.htaccess</code> geschützt. Prüfe…
</div>
<?php endif; ?>

<div class="cols">
    <section class="panel">
        <h2 class="panel-title">Einstellungen</h2>
        <form method="post" action="<?= e(url('admin/settings')) ?>">
            <?= csrf_field() ?>
            <label class="check"><input type="checkbox" name="registration_open" value="1"<?= checked($registration) ?>> Öffentliche Registrierung erlauben</label>
            <label class="check"><input type="checkbox" name="require_2fa" value="1"<?= checked($require2fa) ?>> 2FA für alle Konten verpflichtend</label>
            <label class="check"><input type="checkbox" name="clear_cache" value="1"> TheGamesDB-Cache leeren (<?= (int)$counts['cache'] ?> Einträge)</label>
            <button class="btn btn-primary" type="submit">Speichern</button>
        </form>
        <p class="hint">Der TheGamesDB-API-Key und die Datenbank werden in <code>config/config.php</code> verwaltet.</p>
    </section>

    <section class="panel">
        <h2 class="panel-title">Systemcheck</h2>
        <dl class="specs">
            <?php foreach ($checks as $label => [$value, $ok]): ?>
            <dt><?= e($label) ?></dt><dd><?= $ok ? '' : '⚠ ' ?><?= e($value) ?></dd>
            <?php endforeach; ?>
        </dl>
    </section>
</div>

<section class="stats stats-sm">
    <div class="stat"><span class="stat-num"><?= fmt_num($counts['users']) ?></span><span class="stat-label">Benutzer</span></div>
    <div class="stat"><span class="stat-num"><?= fmt_num($counts['items']) ?></span><span class="stat-label">Einträge</span></div>
    <div class="stat"><span class="stat-num"><?= fmt_num($counts['photos']) ?></span><span class="stat-label">Fotos (<?= number_format($counts['bytes'] / 1048576, 1, ',', '.') ?> MB)</span></div>
    <div class="stat"><span class="stat-num"><?= fmt_num($counts['plays']) ?></span><span class="stat-label">Durchgespielt</span></div>
    <div class="stat"><span class="stat-num"><?= fmt_num($counts['games']) ?></span><span class="stat-label">Spiele im Katalog</span></div>
    <div class="stat"><span class="stat-num"><?= fmt_num($counts['platforms']) ?></span><span class="stat-label">Plattformen</span></div>
</section>
