<?php use Amigo\Core\Auth; ?>
<div class="pagehead">
    <h1 class="h1">Mein Konto</h1>
</div>

<nav class="menu-grid" aria-label="Bereiche">
    <a href="<?= e(url('platforms')) ?>">🕹<span>Plattformen</span></a>
    <a href="<?= e(url('export', ['type' => 'collection'])) ?>">⤓<span>Sammlung als CSV</span></a>
    <a href="<?= e(url('export', ['type' => 'plays'])) ?>">⤓<span>Durchgespielt als CSV</span></a>
    <?php if (Auth::isAdmin()): ?>
    <a href="<?= e(url('admin/settings')) ?>">⚙<span>System</span></a>
    <a href="<?= e(url('admin/users')) ?>">👥<span>Benutzer</span></a>
    <a href="<?= e(url('admin/platforms')) ?>">📥<span>Plattform-Import</span></a>
    <?php endif; ?>
</nav>

<div class="cols">
    <section class="panel">
        <h2 class="panel-title">Profil</h2>
        <dl class="specs">
            <dt>Benutzer</dt><dd><?= e($user['username']) ?> <?= $user['role'] === 'admin' ? '<span class="chip">Admin</span>' : '' ?></dd>
            <dt>Dabei seit</dt><dd><?= e(fmt_date($user['created_at'])) ?></dd>
        </dl>
        <form method="post" action="<?= e(url('account/profile')) ?>">
            <?= csrf_field() ?>
            <div class="field">
                <label for="email">E-Mail <span class="muted">(optional)</span></label>
                <input id="email" name="email" type="email" value="<?= e($user['email']) ?>" maxlength="190" autocomplete="email">
            </div>
            <button class="btn" type="submit">Speichern</button>
        </form>
    </section>

    <section class="panel">
        <h2 class="panel-title">Zwei-Faktor-Anmeldung</h2>
        <?php if ((int)$user['totp_enabled']): ?>
            <p><span class="cond cond-5">AKTIV</span> Dein Konto ist mit einer Authenticator-App geschützt.</p>
            <p class="muted">Verbleibende Recovery-Codes: <strong><?= (int)$recoveryLeft ?></strong></p>
            <details>
                <summary>Neue Recovery-Codes erzeugen</summary>
                <form method="post" action="<?= e(url('account/recovery')) ?>">
                    <?= csrf_field() ?>
                    <div class="field"><label for="rc-pw">Passwort</label><input id="rc-pw" type="password" name="current" autocomplete="current-password" required></div>
                    <button class="btn" type="submit">Codes erzeugen</button>
                </form>
            </details>
            <details>
                <summary>2FA deaktivieren</summary>
                <form method="post" action="<?= e(url('account/2fa/off')) ?>">
                    <?= csrf_field() ?>
                    <div class="field"><label for="off-pw">Passwort</label><input id="off-pw" type="password" name="current" autocomplete="current-password" required></div>
                    <div class="field"><label for="off-code">Aktueller 2FA-Code</label><input id="off-code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required></div>
                    <button class="btn btn-danger" type="submit">2FA ausschalten</button>
                </form>
            </details>
        <?php else: ?>
            <p><span class="cond cond-1">AUS</span> Mit 2FA brauchst du beim Login zusätzlich einen Code aus einer App wie Aegis, 2FAS, Bitwarden oder Google Authenticator.</p>
            <a class="btn btn-primary" href="<?= e(url('account/2fa')) ?>">🔐 2FA einrichten</a>
        <?php endif; ?>
    </section>
</div>

<section class="panel">
    <h2 class="panel-title">Passwort ändern</h2>
    <form method="post" action="<?= e(url('account/password')) ?>" class="row3">
        <?= csrf_field() ?>
        <input type="hidden" name="username" value="<?= e($user['username']) ?>" autocomplete="username">
        <div class="field"><label for="pw-cur">Aktuelles Passwort</label><input id="pw-cur" type="password" name="current" autocomplete="current-password" required></div>
        <div class="field"><label for="pw-new">Neues Passwort</label><input id="pw-new" type="password" name="password" autocomplete="new-password" minlength="10" required></div>
        <div class="field"><label for="pw-new2">Wiederholen</label><input id="pw-new2" type="password" name="password2" autocomplete="new-password" minlength="10" required></div>
        <div><button class="btn" type="submit">Passwort ändern</button></div>
    </form>
</section>

<section class="panel">
    <h2 class="panel-title">Farbschema</h2>
    <div class="theme-pick" data-theme-pick>
        <?php foreach (\Amigo\Core\Options::THEMES as $k => $label): ?>
        <button type="button" class="btn theme-swatch theme-<?= e($k) ?>" data-theme-set="<?= e($k) ?>"><?= e($label) ?></button>
        <?php endforeach; ?>
    </div>
</section>

<form method="post" action="<?= e(url('logout')) ?>" class="center">
    <?= csrf_field() ?>
    <button class="btn btn-ghost" type="submit">⏻ Logout</button>
</form>
