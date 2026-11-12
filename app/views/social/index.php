<?php
use Amigo\Service\Social;

/** Kleines Formular für eine Aktion auf der Freunde-Seite. */
$btn = static function (int $id, string $action, string $label, string $cls = 'btn btn-sm', ?string $confirm = null): string {
    return '<form method="post" action="' . e(url('friend/action')) . '" class="inline"' . ($confirm ? ' data-confirm="' . e($confirm) . '"' : '') . '>'
        . csrf_field() . '<input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="action" value="' . e($action) . '">'
        . '<button class="' . e($cls) . '" type="submit">' . e($label) . '</button></form>';
};
$online = count(array_filter($friends, fn ($f) => $f['online']));
?>
<div class="pagehead">
    <h1 class="h1">Freunde <span class="count"><?= count($friends) ?></span></h1>
    <div class="btnrow">
        <a class="btn btn-ghost" href="<?= e(url('community')) ?>">👥 Community</a>
        <a class="btn btn-ghost" href="<?= e(url('account')) ?>#sichtbarkeit">⚙ Einstellungen</a>
    </div>
</div>

<?php if (!$enabled): ?>
<div class="flash flash-info">Die Community ist derzeit geschlossen – Freundschaftsanfragen und Likes sind pausiert. Deine Freundesliste und Blockierungen bleiben erhalten.</div>
<?php elseif ((int)$user['allow_friend_requests'] !== 1): ?>
<div class="flash flash-info">Du nimmst derzeit keine Freundschaftsanfragen an. Das kannst du unter <a href="<?= e(url('account')) ?>#sichtbarkeit">Mein Konto › Sichtbarkeit</a> ändern.</div>
<?php endif; ?>

<?php if ($incoming): ?>
<section class="panel">
    <h2 class="panel-title">Neue Anfragen <span class="count"><?= count($incoming) ?></span></h2>
    <ul class="friendlist">
        <?php foreach ($incoming as $u): ?>
        <li>
            <a class="friend" href="<?= e(url('u', ['name' => $u['username']])) ?>"><?= avatar($u, 40, 'player-avatar') ?><span><strong><?= e($u['username']) ?></strong><small class="muted">möchte mit dir befreundet sein · <?= e(fmt_datetime($u['created_at'])) ?></small></span></a>
            <span class="btnrow">
                <?= $enabled ? $btn((int)$u['id'], 'accept', '✓ Annehmen', 'btn btn-sm btn-primary') : '' ?>
                <?= $btn((int)$u['id'], 'decline', '✕ Ablehnen') ?>
            </span>
        </li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>

<section class="panel">
    <h2 class="panel-title">Meine Freunde <?php if ($friends): ?><small class="muted"><?= $online ?> online</small><?php endif; ?></h2>
    <?php if (!$friends): ?>
        <p class="empty">Noch keine Freunde. Schick jemandem über sein Profil in der <a href="<?= e(url('community')) ?>">Community</a> eine Anfrage.</p>
    <?php else: ?>
    <ul class="friendlist">
        <?php foreach ($friends as $u): ?>
        <li>
            <a class="friend" href="<?= e(url('u', ['name' => $u['username']])) ?>">
                <span class="friend-ava"><?= avatar($u, 40, 'player-avatar') ?><span class="dot<?= $u['online'] ? ' is-online' : '' ?>" title="<?= $u['online'] ? 'online' : 'offline' ?>"></span></span>
                <span><strong><?= e($u['username']) ?></strong>
                <small class="<?= $u['online'] ? 'online-text' : 'muted' ?>"><?= $u['online'] ? '● online' : ($u['last_seen_at'] ? 'zuletzt ' . e(fmt_datetime(gmdate('Y-m-d H:i:s', (int)$u['last_seen_at']))) : 'offline') ?></small></span>
            </a>
            <span class="btnrow">
                <?php if (\Amigo\Service\Messages::enabled()): ?><a class="btn btn-sm" href="<?= e(url('message/new', ['to' => $u['username']])) ?>">✉</a><?php endif; ?>
                <?= $btn((int)$u['id'], 'remove', 'Entfernen', 'btn btn-sm btn-ghost', $u['username'] . ' aus deiner Freundesliste entfernen?') ?>
            </span>
        </li>
        <?php endforeach; ?>
    </ul>
    <p class="hint">Den Online-Status deiner Freunde siehst nur du („online“ = in den letzten <?= intdiv(Social::ONLINE_SECONDS, 60) ?> Minuten aktiv).</p>
    <?php endif; ?>
</section>

<?php if ($outgoing): ?>
<section class="panel">
    <h2 class="panel-title">Gesendete Anfragen</h2>
    <ul class="friendlist">
        <?php foreach ($outgoing as $u): ?>
        <li>
            <a class="friend" href="<?= e(url('u', ['name' => $u['username']])) ?>"><?= avatar($u, 40, 'player-avatar') ?><span><strong><?= e($u['username']) ?></strong><small class="muted">wartet auf Antwort · <?= e(fmt_datetime($u['created_at'])) ?></small></span></a>
            <span class="btnrow"><?= $btn((int)$u['id'], 'cancel', 'Zurückziehen') ?></span>
        </li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>

<section class="panel">
    <h2 class="panel-title">Blockiert <span class="count"><?= count($blocked) ?></span></h2>
    <?php if (!$blocked): ?>
        <p class="empty">Du hast niemanden blockiert. Blockieren kannst du über das Profil eines Nutzers.</p>
    <?php else: ?>
    <ul class="friendlist">
        <?php foreach ($blocked as $u): ?>
        <li>
            <span class="friend"><?= avatar($u, 40, 'player-avatar') ?><span><strong><?= e($u['username']) ?></strong><small class="muted">blockiert seit <?= e(fmt_date($u['created_at'])) ?></small></span></span>
            <span class="btnrow"><?= $btn((int)$u['id'], 'unblock', 'Blockierung aufheben', 'btn btn-sm', 'Blockierung von ' . $u['username'] . ' aufheben?') ?></span>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
    <p class="hint">Blockierte Nutzer und du seht euch gegenseitig nicht mehr (Community, Profil, Sammlung), könnt euch nicht schreiben, nicht liken und keine Anfragen senden. Sie erfahren davon nichts.</p>
</section>
