<section class="panel form-narrow center">
    <?php if ($ok): ?>
        <h2 class="panel-title">GAME OVER</h2>
        <p class="logo-type">BYE<span class="blink">_</span></p>
        <p>Dein Konto <strong><?= e($name) ?></strong> wurde soeben <strong>gelöscht</strong> – zusammen mit deiner Sammlung, deiner Durchgespielt-Liste und allen von dir hochgeladenen Bildern.</p>
        <p class="muted">Eine Bestätigung haben wir dir per E-Mail geschickt. Danke fürs Mitspielen!</p>
        <p><a class="btn" href="<?= e(url()) ?>">▶ Zur Startseite</a></p>
    <?php else: ?>
        <h2 class="panel-title">Link ungültig</h2>
        <p>Dieser Lösch-Link ist ungültig oder abgelaufen (er gilt 1 Stunde und nur, solange das Konto besteht).</p>
        <p class="muted">Du kannst die Löschung unter <strong>Mein Konto › Konto löschen</strong> erneut anfordern.</p>
        <p><a class="btn" href="<?= e(url('login')) ?>">▶ Zum Login</a></p>
    <?php endif; ?>
</section>
