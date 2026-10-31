<?php
use Amigo\Core\Auth;
use Amigo\Core\Smilies;
use Amigo\Service\Messages;

$me = Auth::id();
$active = array_values(array_filter($participants, fn ($p) => $p['status'] === 'active'));
$gone = array_values(array_filter($participants, fn ($p) => $p['status'] !== 'active'));
$alone = count($active) < 2;
$hasOwn = (bool)array_filter($messages, fn ($m) => (int)$m['user_id'] === $me);
?>
<div class="pagehead">
    <div>
        <p class="crumbs"><a href="<?= e(url('messages')) ?>">Nachrichten</a></p>
        <h1 class="h1 conv-title"><?= e($conv['subject']) ?></h1>
    </div>
    <form method="post" action="<?= e(url('message/action')) ?>" data-confirm="Unterhaltung verlassen? Du bekommst dort keine neuen Nachrichten mehr.">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$conv['id'] ?>"><input type="hidden" name="action" value="leave">
        <button class="btn btn-ghost btn-sm" type="submit">⏏ Verlassen</button>
    </form>
</div>

<div class="conv-layout">
    <div>
        <form method="post" action="<?= e(url('message/action')) ?>" id="msg-form">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$conv['id'] ?>">
            <ol class="thread">
                <?php foreach ($messages as $m): $mine = (int)$m['user_id'] === $me; $new = (int)$m['id'] > $lastRead; ?>
                <li class="msg<?= $mine ? ' is-mine' : '' ?><?= $new && !$mine ? ' is-new' : '' ?>" id="m<?= (int)$m['id'] ?>">
                    <div class="msg-head">
                        <?= $m['username'] !== null ? avatar(['id' => $m['user_id'], 'username' => $m['username'], 'avatar' => $m['avatar']], 32) : '<span class="noimg" aria-hidden="true">?</span>' ?>
                        <strong><?= $m['username'] !== null ? '<a href="' . e(url('u', ['name' => $m['username']])) . '">' . e($m['username']) . '</a>' : '<span class="muted">gelöschter Nutzer</span>' ?></strong>
                        <time datetime="<?= e(str_replace(' ', 'T', $m['created_at'])) ?>Z"><?= e(fmt_datetime($m['created_at'])) ?></time>
                        <?php if ($mine): ?>
                        <label class="msg-check" title="Zum Löschen markieren"><input type="checkbox" name="msg[]" value="<?= (int)$m['id'] ?>"><span class="sr">Markieren</span></label>
                        <?php endif; ?>
                    </div>
                    <div class="msg-body"><?= Smilies::render($m['body']) ?></div>
                </li>
                <?php endforeach; ?>
            </ol>
            <?php if ($hasOwn): ?>
            <p class="right"><button class="btn btn-sm btn-danger" type="submit" name="action" value="delete_messages" data-confirm="Markierte eigene Nachrichten löschen?">🗑 Markierte löschen</button></p>
            <?php endif; ?>
        </form>

        <form method="post" action="<?= e(url('message/reply')) ?>" class="panel" id="ende">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$conv['id'] ?>">
            <h2 class="panel-title">Antworten</h2>
            <?php if ($alone): ?>
            <p class="muted">Alle anderen haben die Unterhaltung verlassen.<?= $isCreator ? ' Lade rechts neue Teilnehmer ein.' : '' ?></p>
            <?php else: ?>
            <?php $target = 'reply-body'; require __DIR__ . '/../partials/smiley_bar.php'; ?>
            <div class="field">
                <label class="sr" for="reply-body">Nachricht</label>
                <textarea id="reply-body" name="body" rows="5" maxlength="<?= Messages::BODY_MAX ?>" required placeholder="Deine Antwort…"></textarea>
            </div>
            <button class="btn btn-primary" type="submit">✉ Senden</button>
            <?php endif; ?>
        </form>
    </div>

    <aside class="panel conv-people">
        <h2 class="panel-title">Teilnehmer <span class="count"><?= count($active) ?>/<?= (int)$max ?></span></h2>
        <ul class="people">
            <?php foreach ($active as $p): ?>
            <li>
                <?= avatar($p, 28) ?>
                <a href="<?= e(url('u', ['name' => $p['username']])) ?>"><?= e($p['username']) ?></a>
                <?php if ((int)$p['id'] === (int)$conv['creator_id']): ?><small class="chip" title="Hat die Unterhaltung gestartet">★</small><?php endif; ?>
                <?php if ($isCreator && (int)$p['id'] !== $me): ?>
                <form method="post" action="<?= e(url('message/action')) ?>" class="inline" data-confirm="<?= e($p['username']) ?> aus der Unterhaltung entfernen?">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$conv['id'] ?>"><input type="hidden" name="action" value="remove"><input type="hidden" name="user" value="<?= (int)$p['id'] ?>">
                    <button class="btn btn-sm btn-ghost" type="submit" title="Entfernen" aria-label="<?= e($p['username']) ?> entfernen">✕</button>
                </form>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($gone): ?>
        <p class="muted small">Nicht mehr dabei: <?= e(implode(', ', array_map(fn ($p) => $p['username'] . ($p['status'] === 'removed' ? ' (entfernt)' : ' (ausgetreten)'), $gone))) ?></p>
        <?php endif; ?>
        <?php if ($isCreator && count($active) < $max): ?>
        <form method="post" action="<?= e(url('message/action')) ?>" class="subform">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$conv['id'] ?>"><input type="hidden" name="action" value="add">
            <div class="field user-picker" data-user-picker data-api="<?= e(url('api/users')) ?>">
                <label for="add-to">Teilnehmer hinzufügen</label>
                <input id="add-to" name="to" maxlength="500" autocomplete="off" autocapitalize="none" placeholder="Benutzername" required>
                <div class="picker-list" data-user-list hidden></div>
            </div>
            <button class="btn btn-sm" type="submit">+ Hinzufügen</button>
        </form>
        <?php endif; ?>
    </aside>
</div>
