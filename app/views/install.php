<?php if ($done): ?>
<section class="panel">
    <h2 class="panel-title">INSTALLATION COMPLETE</h2>
    <p>AMIGO! ist startklar. Logge dich mit deinem Admin-Konto ein und richte als Erstes die Zwei-Faktor-Anmeldung ein.</p>
    <?php if ($probe): ?>
    <div class="flash flash-warn" data-probe="<?= e($probe) ?>" data-probe-ok="Datenverzeichnis ist korrekt gegen Webzugriff gesperrt." data-probe-fail="ACHTUNG: data/ ist über den Browser erreichbar! Bitte Zugriff sperren (siehe README, z. B. nginx) oder data/ außerhalb des Webroots verschieben.">Prüfe Schutz des Datenverzeichnisses…</div>
    <?php endif; ?>
    <ul>
        <li>Der Installer ist jetzt gesperrt (<code>config/config.php</code> existiert). Du kannst <code>install.php</code> zusätzlich löschen.</li>
        <li>Sichere regelmäßig <code>config/config.php</code> und das Datenverzeichnis.</li>
    </ul>
    <a class="btn btn-primary btn-block" href="index.php">▶ PRESS START</a>
</section>
<?php else: ?>
<form method="post" action="install.php" class="panel" autocomplete="off">
    <?= csrf_field() ?>
    <h2 class="panel-title">SETUP</h2>

    <h3 class="h3">Voraussetzungen</h3>
    <dl class="specs">
        <?php foreach ($requirements as [$label, $value, $ok]): ?>
        <dt><?= e($label) ?></dt><dd class="<?= $ok ? 'check-ok' : 'check-bad' ?>"><?= $ok ? '✓' : '✗' ?> <?= e($value) ?></dd>
        <?php endforeach; ?>
    </dl>

    <h3 class="h3">Installations-Token</h3>
    <?php if ($tokenExists): ?>
    <p class="hint">Öffne per FTP/Dateimanager die Datei <code>config/INSTALL_TOKEN.txt</code> und kopiere den Inhalt hierher. So kann niemand anderes deine Installation übernehmen.</p>
    <?php else: ?>
    <p class="flash flash-error">config/ ist nicht beschreibbar – Token konnte nicht erzeugt werden.</p>
    <?php endif; ?>
    <div class="field"><label for="token">Token</label><input id="token" name="token" required maxlength="64" spellcheck="false"></div>

    <h3 class="h3">Datenbank</h3>
    <div class="db-choice">
        <label>
            <input type="radio" name="driver" value="sqlite"<?= checked($form['driver'] === 'sqlite') ?><?= in_array('sqlite', $drivers, true) ? '' : ' disabled' ?> data-db-switch>
            <strong>SQLite (empfohlen)</strong><br>
            <small>Kein Datenbank-Server nötig: alles liegt in einer Datei im Datenverzeichnis – idealerweise außerhalb des Webroots, damit sie per Browser grundsätzlich nicht erreichbar ist. Verbraucht keine deiner MySQL-Datenbanken, Backup = Ordner kopieren.</small>
        </label>
        <label>
            <input type="radio" name="driver" value="mysql"<?= checked($form['driver'] === 'mysql') ?><?= in_array('mysql', $drivers, true) ? '' : ' disabled' ?> data-db-switch>
            <strong>MySQL / MariaDB</strong><br>
            <small>Klassisch über den Datenbank-Server. Mit Tabellen-Präfix kann eine bereits vorhandene Datenbank mitgenutzt werden.</small>
        </label>
    </div>

    <div class="field">
        <label for="data_dir">Datenverzeichnis (Fotos, SQLite-Datei, Sitzungen, Logs)</label>
        <input id="data_dir" name="data_dir" value="<?= e($form['data_dir']) ?>" required>
        <p class="hint">Absoluter Pfad. Am sichersten außerhalb des öffentlichen Ordners, z. B. <code><?= e(dirname(AMIGO_ROOT)) ?>/amigo-data</code>. Wenn das dein Hoster nicht erlaubt: <code><?= e(AMIGO_ROOT) ?>/data</code> (per .htaccess gesperrt).</p>
    </div>

    <fieldset class="dbfields" data-db-fields="mysql">
        <div class="row2">
            <div class="field"><label for="db_host">Host</label><input id="db_host" name="db_host" value="<?= e($form['db_host']) ?>"></div>
            <div class="field"><label for="db_port">Port</label><input id="db_port" name="db_port" value="<?= e($form['db_port']) ?>" inputmode="numeric"></div>
            <div class="field"><label for="db_name">Datenbankname</label><input id="db_name" name="db_name" value="<?= e($form['db_name']) ?>"></div>
            <div class="field"><label for="db_prefix">Tabellen-Präfix</label><input id="db_prefix" name="db_prefix" value="<?= e($form['db_prefix']) ?>" maxlength="20"></div>
            <div class="field"><label for="db_user">Benutzer</label><input id="db_user" name="db_user" value="<?= e($form['db_user']) ?>"></div>
            <div class="field"><label for="db_pass">Passwort</label><input id="db_pass" name="db_pass" type="password"></div>
        </div>
    </fieldset>

    <h3 class="h3">Admin-Konto</h3>
    <div class="row2">
        <div class="field"><label for="username">Benutzername</label><input id="username" name="username" value="<?= e($form['username']) ?>" required maxlength="40" autocomplete="username"></div>
        <div class="field"><label for="email">E-Mail (optional)</label><input id="email" name="email" type="email" value="<?= e($form['email']) ?>"></div>
        <div class="field"><label for="password">Passwort (min. 10 Zeichen)</label><input id="password" name="password" type="password" minlength="10" required autocomplete="new-password"></div>
        <div class="field"><label for="password2">Passwort wiederholen</label><input id="password2" name="password2" type="password" minlength="10" required autocomplete="new-password"></div>
    </div>

    <h3 class="h3">Sonstiges</h3>
    <div class="row2">
        <div class="field"><label for="site_name">Name der Seite</label><input id="site_name" name="site_name" value="<?= e($form['site_name']) ?>" maxlength="40"></div>
        <div class="field"><label for="timezone">Zeitzone</label><input id="timezone" name="timezone" value="<?= e($form['timezone']) ?>"></div>
    </div>
    <div class="field">
        <label for="tgdb_key">TheGamesDB API-Key (optional, später in config/config.php änderbar)</label>
        <input id="tgdb_key" name="tgdb_key" value="<?= e($form['tgdb_key']) ?>" spellcheck="false">
        <p class="hint">Einen kostenlosen Key gibt es über das <a href="https://forums.thegamesdb.net/" target="_blank" rel="noopener noreferrer">TheGamesDB-Forum</a>. Ohne Key funktioniert alles – nur ohne Vorschläge und Plattform-Import.</p>
    </div>

    <button class="btn btn-primary btn-block" type="submit"<?= $requirementsOk ? '' : ' disabled' ?>>▶ INSTALLIEREN</button>
</form>
<?php endif; ?>
