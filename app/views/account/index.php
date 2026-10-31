<?php use Amigo\Core\Auth; ?>
<div class="pagehead">
    <h1 class="h1">Mein Konto</h1>
</div>

<nav class="menu-grid" aria-label="Bereiche">
    <a href="<?= e(url('community')) ?>">👾<span>Community</span></a>
    <a href="<?= e(url('u', ['name' => $user['username']])) ?>">🪪<span>Mein Profil</span></a>
    <a href="<?= e(url('platforms')) ?>">🕹<span>Plattformen</span></a>
    <a href="<?= e(url('export', ['type' => 'collection'])) ?>">⤓<span>Sammlung als CSV</span></a>
    <a href="<?= e(url('export', ['type' => 'plays'])) ?>">⤓<span>Durchgespielt als CSV</span></a>
    <?php if (Auth::isAdmin()): ?>
    <a href="<?= e(url('admin/settings')) ?>">⚙<span>System</span></a>
    <a href="<?= e(url('admin/users')) ?>">👥<span>Benutzer</span></a>
    <a href="<?= e(url('admin/platforms')) ?>">📥<span>Plattform-Import</span></a>
    <?php endif; ?>
</nav>

<section class="panel" id="avatar">
    <h2 class="panel-title">Avatar</h2>
    <?php $current = \Amigo\Service\Avatar::resolve($user); ?>
    <div class="avatar-current">
        <?= avatar($user, 96, 'avatar-big') ?>
        <p><?= $current['type'] === 'upload' ? 'Eigenes Bild' : 'Badge: ' . e(\Amigo\Core\Badges::ALL[$current['value']] ?? '') ?><?= $user['avatar'] === null ? ' <small class="muted">(automatisch zugewiesen)</small>' : '' ?></p>
    </div>
    <form method="post" action="<?= e(url('account/avatar')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="badge">
        <fieldset class="badge-grid">
            <legend class="label">Pixel-Badge wählen</legend>
            <?php foreach (\Amigo\Core\Badges::ALL as $key => $label): ?>
            <label class="badge-pick" title="<?= e($label) ?>">
                <input type="radio" name="badge" value="<?= e($key) ?>"<?= checked($current['type'] === 'badge' && $current['value'] === $key) ?>>
                <svg width="48" height="48" aria-hidden="true"><use href="<?= e(asset('img/badges.svg')) ?>#<?= e($key) ?>"></use></svg>
                <span class="sr"><?= e($label) ?></span>
            </label>
            <?php endforeach; ?>
        </fieldset>
        <button class="btn btn-primary" type="submit">Badge übernehmen</button>
    </form>
    <form method="post" action="<?= e(url('account/avatar')) ?>" enctype="multipart/form-data" class="subform">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="upload">
        <div class="field">
            <label for="av-file">…oder eigenes Mini-Bild hochladen</label>
            <input id="av-file" type="file" name="avatar" accept="image/png,image/jpeg,image/gif,image/webp" required>
            <p class="hint">PNG, JPEG, GIF oder WebP, max. 512 KB. Wird mittig quadratisch zugeschnitten und auf 128×128 px gebracht – Pixel-Art bleibt scharf.</p>
        </div>
        <div class="btnrow">
            <button class="btn" type="submit">⤒ Hochladen</button>
            <?php if ($user['avatar'] !== null): ?>
            <button class="btn btn-ghost" type="submit" form="avatar-reset">Zurücksetzen</button>
            <?php endif; ?>
        </div>
    </form>
    <form method="post" action="<?= e(url('account/avatar')) ?>" id="avatar-reset" hidden><?= csrf_field() ?><input type="hidden" name="action" value="reset"></form>
</section>

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
                <label for="email">E-Mail <?= \Amigo\Service\Accounts::emailRequired() ? '<span class="req">*</span>' : '<span class="muted">(optional)</span>' ?>
                    <?php if ($user['email']): ?><?= $user['email_verified_at'] ? '<span class="cond cond-5">bestätigt</span>' : '<span class="cond cond-2">unbestätigt</span>' ?><?php endif; ?>
                </label>
                <input id="email" name="email" type="email" value="<?= e($user['email']) ?>" maxlength="190" autocomplete="email"<?= \Amigo\Service\Accounts::emailRequired() && $user['role'] !== 'admin' ? ' required' : '' ?>>
            </div>
            <button class="btn" type="submit">Speichern</button>
        </form>
        <?php if ($user['email'] && !$user['email_verified_at'] && \Amigo\Service\Mailer::configured()): ?>
        <form method="post" action="<?= e(url('account/verify')) ?>" class="subform">
            <?= csrf_field() ?>
            <button class="btn btn-sm" type="submit">✉ Bestätigungs-Mail senden</button>
        </form>
        <?php endif; ?>
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

<div class="cols">
    <section class="panel" id="sichtbarkeit">
        <h2 class="panel-title">Sichtbarkeit</h2>
        <p>Andere angemeldete Nutzer sehen dich in der <a href="<?= e(url('community')) ?>">Community</a>. Was sie von dir ansehen dürfen, legst du hier fest:</p>
        <form method="post" action="<?= e(url('account/privacy')) ?>">
            <?= csrf_field() ?>
            <label class="check"><input type="checkbox" name="collection_public" value="1"<?= checked((int)$user['collection_public'] === 1) ?>> Meine Sammlung ist öffentlich</label>
            <label class="check"><input type="checkbox" name="plays_public" value="1"<?= checked((int)$user['plays_public'] === 1) ?>> Meine Durchgespielt-Liste ist öffentlich</label>
            <p class="hint">Kaufpreis, Kaufdatum, Händler und Notizen bleiben immer privat. Nicht angemeldete Besucher sehen nichts.</p>
            <button class="btn" type="submit">Speichern</button>
        </form>
    </section>

    <section class="panel">
        <h2 class="panel-title">TheGamesDB-API-Key</h2>
        <p>
            <?php if ($keySource === 'user'): ?>
                <span class="cond cond-5">EIGENER KEY</span> <code><?= e($keyHint) ?></code><?= $user['tgdb_allowance'] !== null ? ' · Restkontingent: ' . (int)$user['tgdb_allowance'] : '' ?>
            <?php elseif ($keySource === 'system'): ?>
                <span class="cond cond-4">SYSTEM-KEY</span> Du nutzt den Key des Systems mit. Mit einem eigenen Key hast du dein eigenes Kontingent.
            <?php else: ?>
                <span class="cond cond-1">KEIN KEY</span> Ohne Key gibt es keine neuen Vorschläge aus TheGamesDB – bereits importierte Spiele und Plattformen kannst du trotzdem auswählen.
            <?php endif; ?>
        </p>
        <form method="post" action="<?= e(url('account/tgdb')) ?>">
            <?= csrf_field() ?>
            <div class="field">
                <label for="u-tgdb"><?= $keySource === 'user' ? 'Neuen Key setzen' : 'Eigener API-Key' ?></label>
                <input id="u-tgdb" name="tgdb_api_key" type="password" autocomplete="off" spellcheck="false" maxlength="200" placeholder="Key einfügen">
                <p class="hint">Kostenlos über das <a href="https://forums.thegamesdb.net/" target="_blank" rel="noopener noreferrer">TheGamesDB-Forum</a>. Wird verschlüsselt gespeichert und nur für deine eigenen Suchen genutzt.</p>
            </div>
            <div class="btnrow">
                <button class="btn btn-primary" type="submit" name="action" value="save">Speichern &amp; testen</button>
                <?php if ($keySource === 'user'): ?>
                <button class="btn" type="submit" name="action" value="test">Testen</button>
                <button class="btn btn-danger" type="submit" name="action" value="remove" data-confirm="Deinen API-Key entfernen?">Entfernen</button>
                <?php endif; ?>
            </div>
        </form>
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
