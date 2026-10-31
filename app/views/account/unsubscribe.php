<section class="panel form-narrow">
    <h2 class="panel-title">E-Mail-Benachrichtigungen</h2>
    <?php if (!$valid): ?>
        <div class="flash flash-error">Der Link ist ungültig. Du kannst die Einstellung nach dem Login unter „Mein Konto“ ändern.</div>
    <?php elseif ($done): ?>
        <div class="flash flash-ok">Erledigt – du bekommst keine E-Mails mehr zu neuen privaten Nachrichten.</div>
        <p class="muted">Wieder einschalten kannst du das jederzeit unter „Mein Konto › Benachrichtigungen“.</p>
    <?php else: ?>
        <p>Möchtest du keine E-Mails mehr erhalten, wenn dir jemand eine private Nachricht schreibt?</p>
        <form method="post" action="<?= e(url('pm/unsubscribe')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="u" value="<?= (int)$uid ?>">
            <input type="hidden" name="t" value="<?= e($token) ?>">
            <button class="btn btn-primary btn-block" type="submit">Ja, abmelden</button>
        </form>
    <?php endif; ?>
    <p class="center muted"><a href="<?= e(url('login')) ?>">Zum Login</a></p>
</section>
