<?php
use Amigo\Core\Options;

$colPublic = (int)$owner['collection_public'] === 1;
$playsPublic = (int)$owner['plays_public'] === 1;
?>
<div class="pagehead">
    <div>
        <p class="crumbs"><a href="<?= e(url('community')) ?>">Community</a></p>
        <h1 class="h1"><?= avatar($owner, 56, 'player-avatar player-avatar-lg') ?> <?= e($owner['username']) ?></h1>
        <p class="muted">dabei seit <?= e(fmt_date($owner['created_at'])) ?></p>
    </div>
    <?php if ($self): ?><a class="btn btn-ghost" href="<?= e(url('account')) ?>#sichtbarkeit">Sichtbarkeit ändern</a>
    <?php elseif (\Amigo\Service\Messages::enabled()): ?><a class="btn btn-primary" href="<?= e(url('message/new', ['to' => $owner['username']])) ?>">✉ Nachricht senden</a><?php endif; ?>
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
        <h2 class="panel-title">Sammlung</h2>
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
                <li><span class="minilist-row"><span class="noimg" aria-hidden="true">🏆</span><span><strong><?= e($pl['title']) ?></strong><small><?= e($pl['platform'] ?? '') ?><?= $pl['finished_on'] ? ' · ' . e(fmt_date($pl['finished_on'])) : '' ?></small></span><?= stars($pl['rating'] !== null ? (int)$pl['rating'] : null, false) ?></span></li>
                <?php endforeach; ?>
            </ul>
            <p><a class="btn btn-sm" href="<?= e(url('u/plays', ['name' => $owner['username']])) ?>">Ganze Liste ansehen ▶</a></p>
        <?php endif; ?>
    </section>
</div>
