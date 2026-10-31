<?php use Amigo\Core\Auth; ?>
<div class="pagehead">
    <h1 class="h1">Nachrichten <span class="count"><?= fmt_num($total) ?></span></h1>
    <a class="btn btn-primary" href="<?= e(url('message/new')) ?>">✉ Neue Nachricht</a>
</div>

<?php if (!$conversations): ?>
<div class="panel empty-state">
    <p class="logo-type">NO MAIL</p>
    <p>Noch keine Unterhaltungen. Schreib jemandem aus der <a href="<?= e(url('community')) ?>">Community</a>!</p>
    <a class="btn btn-primary" href="<?= e(url('message/new')) ?>">✉ Neue Nachricht</a>
</div>
<?php else: ?>
<form method="post" action="<?= e(url('messages')) ?>" class="inbox-form">
    <?= csrf_field() ?>
    <div class="inbox-tools">
        <label class="check"><input type="checkbox" data-check-all="ids[]"> Alle</label>
        <button class="btn btn-sm" type="submit" name="action" value="read">✓ Als gelesen</button>
        <button class="btn btn-sm btn-danger" type="submit" name="action" value="leave" data-confirm="Markierte Unterhaltungen löschen? Du trittst damit aus und bekommst dort keine neuen Nachrichten mehr.">🗑 Löschen</button>
    </div>
    <ul class="inbox">
        <?php foreach ($conversations as $c): $others = array_values(array_filter($c['participants'], fn ($p) => (int)$p['id'] !== Auth::id())); ?>
        <li class="conv<?= $c['unread'] ? ' is-unread' : '' ?>">
            <label class="conv-check"><input type="checkbox" name="ids[]" value="<?= (int)$c['id'] ?>"><span class="sr">Auswählen</span></label>
            <a class="conv-link" href="<?= e(url('message', ['id' => $c['id']])) ?>">
                <span class="conv-avatars">
                    <?php foreach (array_slice($others, 0, 3) as $p): ?><?= avatar($p, 32) ?><?php endforeach; ?>
                    <?php if (!$others): ?><span class="noimg" aria-hidden="true">✉</span><?php endif; ?>
                </span>
                <span class="conv-body">
                    <span class="conv-subject"><?= $c['unread'] ? '<span class="dot" aria-label="ungelesen"></span>' : '' ?><?= e($c['subject']) ?></span>
                    <small class="muted">
                        <?= e($others ? implode(', ', array_column(array_slice($others, 0, 4), 'username')) . (count($others) > 4 ? ' +' . (count($others) - 4) : '') : 'nur noch du') ?>
                    </small>
                </span>
                <span class="conv-meta">
                    <small><?= e(fmt_datetime($c['last_message_at'])) ?></small>
                    <small class="muted"><?= (int)$c['message_count'] ?> 💬<?= $c['last_author'] ? ' · ' . e($c['last_author']) : '' ?></small>
                </span>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>
</form>
<?php $route = 'messages'; $params = []; require __DIR__ . '/../partials/pager.php'; ?>
<?php endif; ?>
