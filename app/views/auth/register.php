<form method="post" action="<?= e(url('register')) ?>" class="panel form-narrow">
    <?= csrf_field() ?>
    <h2 class="panel-title">NEW PLAYER</h2>
    <div class="field">
        <label for="username">Benutzername</label>
        <input id="username" name="username" value="<?= e($data['username']) ?>" autocomplete="username" autocapitalize="none" pattern="[A-Za-z0-9_.\-]{3,40}" maxlength="40" required>
    </div>
    <div class="field">
        <label for="email">E-Mail <span class="muted">(optional)</span></label>
        <input id="email" name="email" type="email" value="<?= e($data['email']) ?>" autocomplete="email" maxlength="190">
    </div>
    <div class="field">
        <label for="password">Passwort <span class="muted">(min. 10 Zeichen)</span></label>
        <input id="password" name="password" type="password" autocomplete="new-password" minlength="10" required>
    </div>
    <div class="field">
        <label for="password2">Passwort wiederholen</label>
        <input id="password2" name="password2" type="password" autocomplete="new-password" minlength="10" required>
    </div>
    <button class="btn btn-primary btn-block" type="submit">▶ KONTO ERSTELLEN</button>
    <p class="center muted"><a href="<?= e(url('login')) ?>">Zurück zum Login</a></p>
</form>
