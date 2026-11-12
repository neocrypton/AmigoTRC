<?php
use Amigo\Core\Options;

$colPublic = (int)$owner['collection_public'] === 1;
$playsPublic = (int)$owner['plays_public'] === 1;
/** Aktion auf der Profilseite (Freundschaft, Like, Blockieren). */
$act = static function (string $action, string $label, string $cls = 'btn', ?string $confirm = null) use ($owner): string {
    return '<form method="post" action="' . e(url('friend/action')) . '" class="inline"' . ($confirm ? ' data-confirm="' . e($confirm) . '"' : '') . '>'
        . csrf_field() . '<input type="hidden" name="id" value="' . (int)$owner['id'] . '"><input type="hidden" name="back" value="profile">'
        . '<input type="hidden" name="action" value="' . e($action) . '"><button class="' . e($cls) . '" type="submit">' . $label . '</button></form>';
};
?>
<div class="pagehead">
    <div>
        <p class="crumbs"><a href="<?= e(url('community')) ?>">Community</a></p>
        <h1 class="h1"><?= avatar($owner, 56, 'player-avatar player-avatar-lg') ?> <?= e($owner['username']) ?></h1>
        <p class="muted">dabei seit <?= e(fmt_date($owner['created_at'])) ?></p>
    </div>
    <div class="btnrow">
    <?php if ($self): ?><a class="btn btn-ghost" href="<?= e(url('account')) ?>#sichtbarkeit">Sichtbarkeit ändern</a>
    <?php else: ?>
        <?php if (\Amigo\Service\Messages::enabled()): ?><a class="btn btn-primary" href="<?= e(url('message/new', ['to' => $owner['username']])) ?>">✉ Nachricht senden</a><?php endif; ?>
        <?php if ($social): ?>
            <?php if ($friendState === 'friends'): ?>
                <span class="chip chip-friend">👥 Ihr seid befreundet</span>
            <?php elseif ($friendState === 'outgoing'): ?>
                <?= $act('cancel', '⏳ Anfrage gesendet – zurückziehen', 'btn btn-ghost') ?>
            <?php elseif ($friendState === 'incoming'): ?>
                <?= $act('accept', '✓ Freundschaftsanfrage annehmen', 'btn btn-primary') ?>
            <?php elseif (!$requestProblem): ?>
                <?= $act('request', '👥 Freundschaftsanfrage senden') ?>
            <?php endif; ?>
        <?php endif; ?>
        <?= $act('block', '⛔ Blockieren', 'btn btn-ghost btn-sm', $owner['username'] . ' blockieren? Ihr seht euch dann gegenseitig nicht mehr, könnt euch nicht schreiben und nicht liken. Eine Freundschaft wird beendet.') ?>
    <?php endif; ?>
    </div>
</div>

<?php if ($self): ?>
<div class="flash flash-info">So sehen andere Nutzer dein Profil.</div>
<?php endif; ?>

<?php if ($stats): ?>
<section class="stats stats-sm">
    <?php if (isset($stats['items'])): ?><a class="stat" href="<?= e(url('u/collection', ['name' => $owner['username']])) ?>"><span class="stat-num"><?= fmt_num($stats['items']) ?></span><span class="stat-label">Einträge</span></a><?php endif; ?>
    <?php if (isset($stats['platforms'])): ?><div class="stat"><span class="stat-num"><?= fmt_num($stats['platforms']) ?></span><span class="stat-label">Plattformen</span></div><?php endif; ?>
    <?php if (isset($stats['plays'])): ?><a class="stat" href="<?= e(url('u/plays', ['name' => $owner['username']])) ?>"><span class="stat-num"><?= fmt_num($stats['plays']) ?></span><span class="stat-label">Durchgespielt</span></a><?php endif; ?>
    <?php if (isset($stats['minutes'])): ?><div class="stat"><span class="stat-num"><?= fmt_num(intdiv($stats['minutes'], 60)) ?>h</span><span class="stat-label">Spielzeit</span></div><?php endif; ?>
</section>
<?php endif; ?>

<div class="cols">
    <section class="panel">
        <h2 class="panel-title panel-title-row">Sammlung
            <?php if ($colPublic && $social && ((int)($owner['allow_likes'] ?? 1) === 1 || $likes)): ?>
                <?php if ($canLike): ?>
                    <?= $act('like', pixel_heart($liked) . ' <span>' . (int)$likes . '</span>', 'likebtn' . ($liked ? ' is-on' : '')) ?>
                <?php else: ?>
                    <span class="likebtn is-static" title="Likes"><?= pixel_heart($likes > 0) ?> <span><?= (int)$likes ?></span></span>
                <?php endif; ?>
            <?php endif; ?>
        </h2>
        <?php if (!$colPublic): ?>
            <p class="empty">🔒 Die Sammlung ist privat.</p>
        <?php elseif (!$items): ?>
            <p class="empty">Noch keine Einträge.</p>
        <?php else: ?>
            <ul class="minilist">
                <?php foreach ($items as $it): ?>
                <li>
                    <a href="<?= e(url($self ? 'item' : 'u/item', $self ? ['id' => $it['id']] : ['name' => $owner['username'], 'id' => $it['id']])) ?>">
                        <?php if ($it['photo_id']): ?>
                            <img src="<?= e(url('photo', ['id' => $it['photo_id'], 's' => 't'])) ?>" alt="" width="40" height="40" loading="lazy">
                        <?php elseif ($thumb = tgdb_image($it['boxart_thumb'])): ?>
                            <img src="<?= e($thumb) ?>" alt="" width="40" height="40" loading="lazy">
                        <?php else: ?>
                            <span class="noimg" aria-hidden="true">?</span>
                        <?php endif; ?>
                        <span><strong><?= e($it['title']) ?></strong><small><?= e($it['platform_name'] ?? Options::KINDS[$it['kind']] ?? '') ?></small></span>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
            <p><a class="btn btn-sm" href="<?= e(url('u/collection', ['name' => $owner['username']])) ?>">Ganze Sammlung ansehen ▶</a></p>
        <?php endif; ?>
    </section>

    <section class="panel">
        <h2 class="panel-title">Zuletzt durchgespielt</h2>
        <?php if (!$playsPublic): ?>
            <p class="empty">🔒 Die Durchgespielt-Liste ist privat.</p>
        <?php elseif (!$plays): ?>
            <p class="empty">Noch nichts eingetragen.</p>
        <?php else: ?>
            <ul class="minilist">
                <?php foreach ($plays as $pl): ?>
                <li><span class="minilist-row"><span class="noimg" aria-hidden="true">🏆</span><span><strong><?= e($pl['title']) ?></strong><small><?= e(implode(' · ', array_filter([$pl['platform'] ?? '', fmt_play_date($pl['finished_on'], $pl['finished_precision'] ?? null)]))) ?></small></span><?= stars($pl['rating'] !== null ? (int)$pl['rating'] : null, false) ?></span></li>
                <?php endforeach; ?>
            </ul>
            <p><a class="btn btn-sm" href="<?= e(url('u/plays', ['name' => $owner['username']])) ?>">Ganze Liste ansehen ▶</a></p>
        <?php endif; ?>
    </section>
</div>

<?php if ($social || $friendList): ?>
<section class="panel">
    <h2 class="panel-title">Freunde<?php if ($friendList): ?> <span class="count"><?= count($friendList) ?></span><?php endif; ?></h2>
    <?php if ($friendList === null): ?>
        <p class="empty">🔒 Die Freundesliste ist privat.</p>
    <?php elseif (!$friendList): ?>
        <p class="empty">Noch keine Freunde.</p>
    <?php else: ?>
        <ul class="friendgrid">
            <?php foreach ($friendList as $f): ?>
            <li><a href="<?= e(url('u', ['name' => $f['username']])) ?>" title="<?= e($f['username']) ?>"><?= avatar($f, 48, 'player-avatar') ?><span><?= e($f['username']) ?></span></a></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <?php if ($self): ?><p class="hint"><a href="<?= e(url('friends')) ?>">Freunde verwalten ▶</a><?= (int)$owner['friends_public'] === 1 ? '' : ' · Deine Freundesliste ist privat, nur du siehst sie hier.' ?></p><?php endif; ?>
</section>
<?php endif; ?>
