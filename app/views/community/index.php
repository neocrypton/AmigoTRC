<?php use Amigo\Core\Auth; ?>
<div class="pagehead">
    <h1 class="h1">Community <span class="count"><?= count($users) ?></span></h1>
    <div class="btnrow">
        <a class="btn" href="<?= e(url('friends')) ?>">👥 Freunde<?= $incomingCount ? ' <span class="count">' . (int)$incomingCount . '</span>' : '' ?></a>
        <a class="btn btn-ghost" href="<?= e(url('account')) ?>#sichtbarkeit">Meine Sichtbarkeit</a>
    </div>
</div>
<?php if ($incomingCount): ?>
<div class="flash flash-info">👥 Du hast <?= (int)$incomingCount ?> neue Freundschaftsanfrage<?= $incomingCount > 1 ? 'n' : '' ?>. <a href="<?= e(url('friends')) ?>">Jetzt ansehen</a></div>
<?php endif; ?>

<form class="filters" method="get" action="index.php" role="search">
    <input type="hidden" name="r" value="community">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Spieler suchen…" aria-label="Spieler suchen">
    <button class="btn" type="submit">Suchen</button>
</form>

<?php if (!$users): ?>
<div class="panel empty-state"><p class="logo-type">NO PLAYERS</p><p>Niemand gefunden.</p></div>
<?php else: ?>
<ul class="players">
    <?php foreach ($users as $u): $self = (int)$u['id'] === Auth::id(); ?>
    <li>
        <a class="player" href="<?= e(url('u', ['name' => $u['username']])) ?>">
            <?= avatar($u, 48, 'player-avatar') ?>
            <span class="player-body">
                <strong><?= e($u['username']) ?><?= $self ? ' <small class="muted">(du)</small>' : '' ?><?= in_array((int)$u['id'], $friendIds, true) ? ' <small class="chip chip-friend">👥 Freund</small>' : '' ?></strong>
                <small>dabei seit <?= e(fmt_date($u['created_at'])) ?></small>
            </span>
            <span class="player-badges">
                <?php if ((int)$u['collection_public']): ?><span class="chip" title="Sammlung öffentlich">📦 <?= (int)$u['items'] ?></span><?php endif; ?>
                <?php if ((int)$u['plays_public']): ?><span class="chip" title="Durchgespielt öffentlich">🏆 <?= (int)$u['plays'] ?></span><?php endif; ?>
                <?php if (!(int)$u['collection_public'] && !(int)$u['plays_public']): ?><span class="chip muted">🔒 privat</span><?php endif; ?>
            </span>
        </a>
    </li>
    <?php endforeach; ?>
</ul>
<?php endif; ?>
