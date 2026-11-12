<div class="pagehead">
    <h1 class="h1">System</h1>
    <div class="btnrow">
        <a class="btn" href="<?= e(url('admin/users')) ?>">👥 Benutzer</a>
        <a class="btn" href="<?= e(url('admin/platforms')) ?>">📥 Plattformen</a>
        <a class="btn" href="<?= e(url('admin/site')) ?>">🌐 Website</a>
        <a class="btn" href="<?= e(url('admin/stats')) ?>">📊 Statistik</a>
        <a class="btn" href="<?= e(url('admin/accessories')) ?>">🎮 Zubehör-Katalog</a>
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
            <p class="hint">Solange die Registrierung offen ist, sind auch private Nachrichten (PN) aktiv.</p>
            <?php if (!\Amigo\Service\Site::visible(\Amigo\Service\Site::page('privacy'))): ?>
            <p class="hint hint-warn">⚠ Die <a href="<?= e(url('admin/site/page', ['p' => 'privacy'])) ?>">Datenschutzerklärung</a> ist noch nicht aktiv – auf der Registrierungsseite fehlt dann der Link dorthin.</p>
            <?php endif; ?>
            <div class="field subfield">
                <label for="activation_mode">Neue Konten aktivieren</label>
                <select id="activation_mode" name="activation_mode">
                    <?php foreach (\Amigo\Service\Accounts::MODES as $k => $label): ?>
                    <option value="<?= e($k) ?>"<?= selected($k, $mode) ?><?= !$mailOk && str_contains($k, 'email') ? ' disabled' : '' ?>><?= e($label) ?><?= !$mailOk && str_contains($k, 'email') ? ' – E-Mail-Versand einrichten' : '' ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($pendingCount > 0): ?><p class="hint">⏳ <a href="<?= e(url('admin/users')) ?>"><?= $pendingCount ?> Konto/Konten warten auf Freischaltung</a></p><?php endif; ?>
            </div>
            <label class="check subfield"><input type="checkbox" name="require_email" value="1"<?= checked($requireEmail || str_contains($mode, 'email')) ?><?= str_contains($mode, 'email') ? ' disabled' : '' ?>> E-Mail-Adresse ist bei der Registrierung Pflicht<?= str_contains($mode, 'email') ? ' (durch E-Mail-Bestätigung automatisch)' : '' ?></label>
            <label class="check"><input type="checkbox" name="require_2fa" value="1"<?= checked($require2fa) ?>> 2FA für alle Konten verpflichtend</label>
            <label class="check"><input type="checkbox" name="clear_cache" value="1"> TheGamesDB-Cache leeren (<?= (int)$counts['cache'] ?> Einträge)</label>
            <button class="btn btn-primary" type="submit">Speichern</button>
        </form>
    </section>

    <section class="panel" id="mail">
        <h2 class="panel-title">E-Mail-Versand</h2>
        <p>
            <?php if ($mailOk): ?>
                <span class="cond cond-5">AKTIV</span> Absender <code><?= e($mail['from']) ?></code> über <?= $mail['transport'] === 'smtp' ? 'SMTP (' . e($mail['host']) . ')' : 'PHP mail()' ?>.
            <?php else: ?>
                <span class="cond cond-1">AUS</span> Wird für E-Mail-Bestätigung und Benachrichtigungen gebraucht.
            <?php endif; ?>
        </p>
        <form method="post" action="<?= e(url('admin/mail')) ?>" autocomplete="off">
            <?= csrf_field() ?>
            <div class="row2">
                <div class="field"><label for="m-from">Absender-Adresse</label><input id="m-from" type="email" name="from" value="<?= e($mail['from']) ?>" maxlength="190" placeholder="noreply@deine-domain.de"></div>
                <div class="field"><label for="m-name">Absender-Name</label><input id="m-name" name="from_name" value="<?= e($mail['from_name']) ?>" maxlength="80"></div>
            </div>
            <div class="field">
                <label for="m-base">Adresse der Seite (für Links in Mails)</label>
                <input id="m-base" name="base_url" value="<?= e($baseUrl) ?>" maxlength="255">
            </div>
            <div class="field">
                <label for="m-transport">Versandart</label>
                <select id="m-transport" name="transport" data-toggle-target="#smtp-fields" data-toggle-value="smtp">
                    <?php foreach (\Amigo\Service\Mailer::TRANSPORTS as $k => $label): ?><option value="<?= e($k) ?>"<?= selected($k, $mail['transport']) ?>><?= e($label) ?></option><?php endforeach; ?>
                </select>
                <p class="hint">SMTP ist zuverlässiger (weniger Spam-Ordner). Zugangsdaten findest du bei deinem Hoster beim E-Mail-Postfach.</p>
            </div>
            <fieldset id="smtp-fields" class="dbfields">
                <div class="row2">
                    <div class="field"><label for="m-host">SMTP-Server</label><input id="m-host" name="host" value="<?= e($mail['host']) ?>" maxlength="253" placeholder="smtp.deine-domain.de"></div>
                    <div class="field"><label for="m-port">Port</label><input id="m-port" name="port" inputmode="numeric" value="<?= (int)$mail['port'] ?>" maxlength="5"></div>
                    <div class="field"><label for="m-secure">Verschlüsselung</label>
                        <select id="m-secure" name="secure"><?php foreach (\Amigo\Service\Mailer::SECURE as $k => $label): ?><option value="<?= e($k) ?>"<?= selected($k, $mail['secure']) ?>><?= e($label) ?></option><?php endforeach; ?></select>
                    </div>
                    <div class="field"><label for="m-user">Benutzername</label><input id="m-user" name="user" value="<?= e($mail['user']) ?>" maxlength="190" autocomplete="off"></div>
                    <div class="field"><label for="m-pass">Passwort</label><input id="m-pass" type="password" name="pass" maxlength="200" autocomplete="new-password" placeholder="<?= $mail['has_pass'] ? 'leer lassen = unverändert' : '' ?>"></div>
                </div>
                <?php if ($mail['has_pass']): ?><label class="check"><input type="checkbox" name="clear_pass" value="1"> gespeichertes Passwort löschen</label><?php endif; ?>
            </fieldset>
            <button class="btn btn-primary" type="submit" name="action" value="save">Speichern</button>
        </form>
        <?php if ($mailOk): ?>
        <form method="post" action="<?= e(url('admin/mail')) ?>" class="subform">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="test">
            <div class="field"><label for="m-test">Testmail an</label><input id="m-test" type="email" name="test_to" value="<?= e(\Amigo\Core\Auth::user()['email'] ?? '') ?>" maxlength="190"></div>
            <button class="btn btn-sm" type="submit">✉ Testmail senden</button>
        </form>
        <?php endif; ?>
    </section>

    <section class="panel" id="botschutz">
        <h2 class="panel-title">Bot-Schutz · Registrierung</h2>
        <form method="post" action="<?= e(url('admin/recaptcha')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="mode">
            <label class="check"><input type="radio" name="bot_protection" value="builtin"<?= checked(!$useRecaptcha) ?>> <span><strong>Eingebaut</strong> (empfohlen)</span></label>
            <p class="hint">Der Browser löst eine kleine Rechenaufgabe (ca. 1 Sekunde), dazu ein unsichtbares Fallen-Feld und Rate-Limits je IP. Alles läuft auf diesem Server – ohne Google, ohne Cookies, ohne Daten an Dritte.</p>
            <label class="check"><input type="radio" name="bot_protection" value="recaptcha"<?= checked($useRecaptcha) ?><?= $captchaOk ? '' : ' disabled' ?>> <span><strong>Google reCAPTCHA v2</strong> („Ich bin kein Roboter“)</span></label>
            <p class="hint"><?= $captchaOk ? 'Stärker gegen gezielte Angriffe, überträgt aber Daten an Google – dann bitte die Datenschutzerklärung um reCAPTCHA ergänzen.' : 'Erst wählbar, wenn unten die reCAPTCHA-Schlüssel eingetragen sind.' ?></p>
            <button class="btn btn-primary" type="submit">Speichern</button>
        </form>
        <details class="subdetails"<?= $captchaOk ? ' open' : '' ?>>
            <summary>reCAPTCHA-Schlüssel <?= $captchaOk ? '<span class="cond cond-5">HINTERLEGT</span>' : '<span class="cond cond-na">KEINE</span>' ?></summary>
            <form method="post" action="<?= e(url('admin/recaptcha')) ?>">
                <?= csrf_field() ?>
                <div class="field">
                    <label for="rc-site">Website-Schlüssel (Site Key)</label>
                    <input id="rc-site" name="site_key" value="<?= e($captchaSite) ?>" autocomplete="off" spellcheck="false" maxlength="120">
                </div>
                <div class="field">
                    <label for="rc-secret">Geheimschlüssel (Secret Key)</label>
                    <input id="rc-secret" name="secret_key" type="password" autocomplete="off" spellcheck="false" maxlength="120" placeholder="<?= $captchaOk ? 'leer lassen = unverändert' : 'Schlüssel einfügen' ?>">
                    <p class="hint">Schlüssel für <strong>reCAPTCHA v2 – Kästchen „Ich bin kein Roboter“</strong> erstellst du unter <a href="https://www.google.com/recaptcha/admin/create" target="_blank" rel="noopener noreferrer">google.com/recaptcha/admin</a>. Als Domain deine Seite eintragen (z. B. <code><?= e($_SERVER['HTTP_HOST'] ?? '') ?></code>).</p>
                </div>
                <div class="btnrow">
                    <button class="btn" type="submit" name="action" value="save">Speichern &amp; prüfen</button>
                    <?php if ($captchaOk): ?>
                    <button class="btn" type="submit" name="action" value="test">Prüfen</button>
                    <button class="btn btn-danger" type="submit" name="action" value="remove" data-confirm="reCAPTCHA-Schlüssel entfernen? Die Registrierung nutzt dann den eingebauten Bot-Schutz.">Entfernen</button>
                    <?php endif; ?>
                </div>
            </form>
        </details>
    </section>

    <section class="panel">
        <h2 class="panel-title">TheGamesDB · System-Key</h2>
        <p class="hint">Jeder Nutzer kann unter <em>Mein Konto</em> seinen eigenen Key hinterlegen (aktuell: <?= (int)$userKeys ?>). Der System-Key wird für Admins und den Plattform-Import genutzt.</p>
        <p>
            <?php if ($keySource === 'admin'): ?>
                <span class="cond cond-5">AKTIV</span> API-Key gesetzt (<code><?= e($keyHint) ?></code>), verschlüsselt gespeichert.
            <?php elseif ($keySource === 'config'): ?>
                <span class="cond cond-4">AKTIV</span> API-Key aus <code>config/config.php</code> (<code><?= e($keyHint) ?></code>). Ein hier gespeicherter Key hat Vorrang.
            <?php else: ?>
                <span class="cond cond-1">AUS</span> Kein API-Key – ohne Key gibt es keine Spielvorschläge und keinen Plattform-Import.
            <?php endif; ?>
        </p>
        <form method="post" action="<?= e(url('admin/tgdb')) ?>">
            <?= csrf_field() ?>
            <div class="field">
                <label for="tgdb_api_key"><?= $keySource === 'none' ? 'API-Key' : 'Neuen API-Key setzen' ?></label>
                <input id="tgdb_api_key" name="tgdb_api_key" type="password" autocomplete="off" spellcheck="false" maxlength="200" placeholder="Key einfügen">
                <p class="hint">Kostenlos über das <a href="https://forums.thegamesdb.net/" target="_blank" rel="noopener noreferrer">TheGamesDB-Forum</a> erhältlich. Der Key verlässt nie den Server.</p>
            </div>
            <div class="btnrow">
                <button class="btn btn-primary" type="submit" name="action" value="save">Speichern &amp; testen</button>
                <?php if ($keySource !== 'none'): ?>
                <button class="btn" type="submit" name="action" value="test" formnovalidate>Verbindung testen</button>
                <?php endif; ?>
                <?php if ($keySource === 'admin'): ?>
                <button class="btn btn-danger" type="submit" name="action" value="remove" formnovalidate data-confirm="API-Key wirklich entfernen?">Entfernen</button>
                <?php endif; ?>
            </div>
        </form>
        <form method="post" action="<?= e(url('admin/tgdb')) ?>" class="subform">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="shared">
            <label class="check"><input type="checkbox" name="tgdb_shared" value="1"<?= checked($tgdbShared) ?>> Nutzer ohne eigenen Key dürfen den System-Key mitnutzen</label>
            <p class="hint">Aus = jeder Nutzer verbraucht nur sein eigenes Kontingent. Bereits importierte Plattformen und Spiele stehen allen trotzdem zur Verfügung.</p>
            <button class="btn btn-sm" type="submit">Übernehmen</button>
        </form>
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
