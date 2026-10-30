<form method="post" action="<?= e(url('login')) ?>" class="panel form-narrow">
    <?= csrf_field() ?>
    <h2 class="panel-title">PLAYER LOGIN</h2>
    <?php if ($error): ?><div class="flash flash-error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <?php if ($canResend): ?>
    <p><button class="btn btn-sm" type="submit" form="resend-form">✉ Bestätigungs-Mail erneut senden</button></p>
    <?php endif; ?>
    <div class="field">
        <label for="username">Benutzername</label>
        <input id="username" name="username" value="<?= e($username) ?>" autocomplete="username" autocapitalize="none" spellcheck="false" maxlength="40" required autofocus>
    </div>
    <div class="field">
        <label for="password">Passwort</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
    </div>
    <button class="btn btn-primary btn-block" type="submit">▶ START</button>
    <?php if ($registration): ?>
    <p class="center muted">Neu hier? <a href="<?= e(url('register')) ?>">Konto erstellen</a></p>
    <?php endif; ?>
</form>
<?php if ($canResend): ?>
<form method="post" action="<?= e(url('verify/resend')) ?>" id="resend-form" hidden><?= csrf_field() ?></form>
<?php endif; ?>
