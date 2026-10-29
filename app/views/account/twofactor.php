<div class="pagehead">
    <h1 class="h1">2FA einrichten</h1>
    <a class="btn btn-ghost" href="<?= e(url('account')) ?>">Abbrechen</a>
</div>

<div class="cols">
    <section class="panel">
        <h2 class="panel-title">1 · QR-Code scannen</h2>
        <p>Öffne deine Authenticator-App (z. B. Aegis, 2FAS, Bitwarden, Google/Microsoft Authenticator) und scanne den Code.</p>
        <div class="qr" data-qr="<?= e($uri) ?>" aria-label="QR-Code für die Authenticator-App">
            <noscript>QR-Code benötigt JavaScript – bitte den Schlüssel unten manuell eintippen.</noscript>
        </div>
        <p class="muted">Kein Scan möglich? Schlüssel manuell eingeben:</p>
        <p class="secret"><code><?= e(trim(chunk_split($secret, 4, ' '))) ?></code></p>
        <p class="muted"><a href="<?= e($uri) ?>">Direkt in App öffnen</a> (am Handy)</p>
    </section>

    <section class="panel">
        <h2 class="panel-title">2 · Code bestätigen</h2>
        <?php if ($error): ?><div class="flash flash-error" role="alert"><?= e($error) ?></div><?php endif; ?>
        <form method="post" action="<?= e(url('account/2fa')) ?>">
            <?= csrf_field() ?>
            <div class="field">
                <label for="code">6-stelliger Code aus der App</label>
                <input id="code" name="code" class="code-input" inputmode="numeric" pattern="[0-9 ]{6,7}" autocomplete="one-time-code" maxlength="7" required>
            </div>
            <button class="btn btn-primary btn-block" type="submit">🔐 Aktivieren</button>
        </form>
        <p class="muted">Danach erhältst du einmalig 10 Recovery-Codes für den Notfall (Handy verloren).</p>
    </section>
</div>
