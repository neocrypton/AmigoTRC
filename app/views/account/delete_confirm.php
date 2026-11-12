<form method="post" action="<?= e(url('account/delete/confirm')) ?>" class="panel form-narrow danger-panel">
    <?= csrf_field() ?>
    <input type="hidden" name="u" value="<?= (int)$user['id'] ?>">
    <input type="hidden" name="t" value="<?= e($token) ?>">
    <h2 class="panel-title">⚠ Konto löschen</h2>
    <?php if ($error): ?><div class="flash flash-error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <p>Hallo <strong><?= e($user['username']) ?></strong>, mit der Bestätigung wird dein Konto <strong>sofort und endgültig</strong> gelöscht:</p>
    <ul>
        <li>dein Konto mit Profil, Avatar, Freunden und Likes</li>
        <li>deine Sammlung (<?= (int)$counts['items'] ?> Einträge) mit <?= (int)$counts['photos'] ?> Foto(s)</li>
        <li>deine Durchgespielt-Liste (<?= (int)$counts['plays'] ?> Einträge)</li>
        <li>von dir hochgeladene Standardbilder im Zubehör-Katalog</li>
    </ul>
    <p class="hint">Erhalten bleiben die Einträge im gemeinsamen Zubehör-Katalog (ohne deine Bilder) sowie deine Nachrichten in Unterhaltungen anderer – sie erscheinen dort als „gelöschter Nutzer“. Tipp: Exportiere vorher deine Sammlung als CSV (Mein Konto).</p>
    <div class="field">
        <label for="del-pw">Aktuelles Passwort</label>
        <input id="del-pw" name="password" type="password" autocomplete="current-password" required autofocus>
    </div>
    <?php if ((int)$user['totp_enabled'] === 1): ?>
    <div class="field">
        <label for="del-code">2FA-Code</label>
        <input id="del-code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="40" required placeholder="6-stelliger Code oder Recovery-Code">
    </div>
    <?php endif; ?>
    <button class="btn btn-danger btn-block" type="submit" data-confirm="Konto jetzt endgültig löschen? Das kann nicht rückgängig gemacht werden.">🗑 Konto endgültig löschen</button>
    <p class="center"><a href="<?= e(url()) ?>">Abbrechen – Konto behalten</a></p>
</form>
