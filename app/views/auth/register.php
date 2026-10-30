<form method="post" action="<?= e(url('register')) ?>" class="panel form-narrow">
    <?= csrf_field() ?>
    <h2 class="panel-title">NEW PLAYER</h2>
    <div class="field">
        <label for="username">Benutzername</label>
        <input id="username" name="username" value="<?= e($data['username']) ?>" autocomplete="username" autocapitalize="none" pattern="[A-Za-z0-9_.\-]{3,40}" maxlength="40" required>
    </div>
    <div class="field">
        <label for="email">E-Mail <?= $emailRequired ? '<span class="req">*</span>' : '<span class="muted">(optional)</span>' ?></label>
        <input id="email" name="email" type="email" value="<?= e($data['email']) ?>" autocomplete="email" maxlength="190"<?= $emailRequired ? ' required' : '' ?>>
        <?php if ($mode === 'email' || $mode === 'email_admin'): ?><p class="hint">Du bekommst einen Link, um die Adresse zu bestätigen.</p><?php endif; ?>
    </div>
    <div class="field">
        <label for="password">Passwort <span class="muted">(min. 10 Zeichen)</span></label>
        <input id="password" name="password" type="password" autocomplete="new-password" minlength="10" required>
    </div>
    <div class="field">
        <label for="password2">Passwort wiederholen</label>
        <input id="password2" name="password2" type="password" autocomplete="new-password" minlength="10" required>
    </div>
    <div class="field captcha">
        <div class="g-recaptcha" data-sitekey="<?= e($siteKey) ?>" data-theme="<?= e($theme) ?>"></div>
        <noscript><p class="flash flash-warn">Für die Registrierung wird JavaScript benötigt (Bot-Schutz).</p></noscript>
        <p class="hint">Zum Schutz vor Bots wird auf dieser Seite Google reCAPTCHA geladen.</p>
    </div>
    <?php if ($mode === 'admin' || $mode === 'email_admin'): ?><p class="hint">Neue Konten werden von einem Admin freigeschaltet.</p><?php endif; ?>
    <button class="btn btn-primary btn-block" type="submit">▶ KONTO ERSTELLEN</button>
    <script src="https://www.google.com/recaptcha/api.js?hl=de" async defer></script>
    <p class="center muted"><a href="<?= e(url('login')) ?>">Zurück zum Login</a></p>
</form>
