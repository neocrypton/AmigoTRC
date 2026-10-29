<form method="post" action="<?= e(url('login/2fa')) ?>" class="panel form-narrow">
    <?= csrf_field() ?>
    <h2 class="panel-title">ENTER CODE</h2>
    <?php if ($error): ?><div class="flash flash-error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <p>Gib den 6-stelligen Code aus deiner Authenticator-App ein – oder einen deiner Recovery-Codes.</p>
    <div class="field">
        <label for="code">Code</label>
        <input id="code" name="code" class="code-input" inputmode="numeric" autocomplete="one-time-code" maxlength="20" required autofocus>
    </div>
    <button class="btn btn-primary btn-block" type="submit">▶ WEITER</button>
    <p class="center muted"><a href="<?= e(url('login')) ?>">Abbrechen</a></p>
</form>
