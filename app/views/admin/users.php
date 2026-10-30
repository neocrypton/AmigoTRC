<?php use Amigo\Core\Auth; ?>
<div class="pagehead">
    <h1 class="h1">Benutzer <span class="count"><?= count($users) ?></span></h1>
    <a class="btn btn-ghost" href="<?= e(url('admin/settings')) ?>">⚙ System</a>
</div>

<?php if ($pending): ?>
<section class="panel" id="pending">
    <h2 class="panel-title">⏳ Warten auf Freischaltung <span class="count"><?= count($pending) ?></span></h2>
    <ul class="pendinglist">
        <?php foreach ($pending as $u): ?>
        <li>
            <span class="pending-who">
                <strong><?= e($u['username']) ?></strong>
                <small class="muted"><?= e($u['email'] ?? 'ohne E-Mail') ?> · registriert <?= e(fmt_date($u['created_at'])) ?></small>
                <small><?= $u['pending'] === 'email' ? '<span class="cond cond-2">E-Mail noch nicht bestätigt</span>' : ($u['email_verified_at'] ? '<span class="cond cond-5">E-Mail bestätigt</span>' : '') ?></small>
            </span>
            <span class="btnrow">
                <form method="post" action="<?= e(url('admin/user/action')) ?>" class="inline">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><input type="hidden" name="action" value="approve">
                    <button class="btn btn-primary btn-sm" type="submit">✓ Freischalten</button>
                </form>
                <form method="post" action="<?= e(url('admin/user/action')) ?>" class="inline" data-confirm="Registrierung von <?= e($u['username']) ?> ablehnen und löschen?">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><input type="hidden" name="action" value="reject">
                    <button class="btn btn-danger btn-sm" type="submit">✕ Ablehnen</button>
                </form>
            </span>
        </li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>

<div class="tablewrap">
<table class="tbl">
    <thead><tr><th>Benutzer</th><th>Rolle</th><th>2FA</th><th>Einträge</th><th>Letzter Login</th><th>Aktionen</th></tr></thead>
    <tbody>
    <?php foreach ($users as $u): $self = (int)$u['id'] === Auth::id(); ?>
        <tr<?= (int)$u['is_active'] ? '' : ' class="is-off"' ?>>
            <td data-label="Benutzer"><strong><?= e($u['username']) ?></strong><?= $self ? ' <small class="muted">(du)</small>' : '' ?><br><small class="muted"><?= e($u['email'] ?? '') ?></small></td>
            <td data-label="Rolle"><?= $u['role'] === 'admin' ? '<span class="chip">Admin</span>' : 'Benutzer' ?><?= (int)$u['is_active'] ? '' : ' <span class="cond cond-0">gesperrt</span>' ?></td>
            <td data-label="2FA"><?= (int)$u['totp_enabled'] ? '<span class="cond cond-5">an</span>' : '<span class="cond cond-1">aus</span>' ?></td>
            <td data-label="Einträge"><?= (int)$u['items'] ?></td>
            <td data-label="Letzter Login"><?= e($u['last_login_at'] ? fmt_date($u['last_login_at']) : '–') ?></td>
            <td data-label="Aktionen">
                <div class="btnrow">
                <?php if (!$self): ?>
                    <form method="post" action="<?= e(url('admin/user/action')) ?>" class="inline">
                        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><input type="hidden" name="action" value="toggle_active">
                        <button class="btn btn-sm" type="submit"><?= (int)$u['is_active'] ? 'Sperren' : 'Entsperren' ?></button>
                    </form>
                    <form method="post" action="<?= e(url('admin/user/action')) ?>" class="inline">
                        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><input type="hidden" name="action" value="toggle_admin">
                        <button class="btn btn-sm" type="submit"><?= $u['role'] === 'admin' ? 'Zu Benutzer' : 'Zu Admin' ?></button>
                    </form>
                <?php endif; ?>
                <?php if ((int)$u['totp_enabled']): ?>
                    <form method="post" action="<?= e(url('admin/user/action')) ?>" class="inline" data-confirm="2FA für <?= e($u['username']) ?> zurücksetzen?">
                        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><input type="hidden" name="action" value="reset_2fa">
                        <button class="btn btn-sm" type="submit">2FA zurücksetzen</button>
                    </form>
                <?php endif; ?>
                </div>
                <?php if (!$self): ?>
                <details class="mini-danger">
                    <summary>Löschen…</summary>
                    <form method="post" action="<?= e(url('admin/user/action')) ?>">
                        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><input type="hidden" name="action" value="delete">
                        <label>Zur Bestätigung „<?= e($u['username']) ?>“ eintippen:<input name="confirm" autocomplete="off" required></label>
                        <button class="btn btn-danger btn-sm" type="submit">Endgültig löschen</button>
                    </form>
                </details>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<section class="panel">
    <h2 class="panel-title">Neuen Benutzer anlegen</h2>
    <form method="post" action="<?= e(url('admin/users')) ?>" class="row3">
        <?= csrf_field() ?>
        <div class="field"><label for="nu-name">Benutzername</label><input id="nu-name" name="username" value="<?= e($data['username']) ?>" maxlength="40" autocomplete="off" required></div>
        <div class="field"><label for="nu-mail">E-Mail <span class="muted">(optional)</span></label><input id="nu-mail" type="email" name="email" value="<?= e($data['email']) ?>" maxlength="190" autocomplete="off"></div>
        <div class="field"><label for="nu-role">Rolle</label><select id="nu-role" name="role"><option value="user">Benutzer</option><option value="admin"<?= selected('admin', $data['role']) ?>>Admin</option></select></div>
        <div class="field"><label for="nu-pw">Passwort</label><input id="nu-pw" type="password" name="password" minlength="10" autocomplete="new-password" required></div>
        <div class="field"><label for="nu-pw2">Wiederholen</label><input id="nu-pw2" type="password" name="password2" minlength="10" autocomplete="new-password" required></div>
        <div class="field field-end"><button class="btn btn-primary" type="submit">+ Anlegen</button></div>
    </form>
</section>
