<?php
use Amigo\Core\Auth;
use Amigo\Core\Options;

$u = Auth::user();
?>
<section class="hero">
    <h1 class="logo-type">HI, <?= e(mb_strtoupper($u['username'])) ?>!</h1>
    <p class="hero-sub typewriter">READY PLAYER ONE<span class="blink">_</span></p>
    <div class="hero-actions">
        <a class="btn btn-primary" href="<?= e(url('item/edit')) ?>">+ Spiel/Hardware eintragen</a>
        <a class="btn" href="<?= e(url('play/edit')) ?>">🏆 Durchgespielt eintragen</a>
    </div>
</section>

<?php if (\Amigo\Core\Auth::isAdmin() && ($pc = \Amigo\Service\Accounts::pendingCount()) > 0): ?>
<div class="flash flash-warn">⏳ <a href="<?= e(url('admin/users')) ?>#pending"><?= $pc ?> neue(s) Konto/Konten warten auf Freischaltung</a></div>
<?php endif; ?>

<?php if (!(int)$u['totp_enabled']): ?>
<div class="flash flash-info">Tipp: Schütze dein Konto mit <a href="<?= e(url('account/2fa')) ?>">Zwei-Faktor-Anmeldung</a>.</div>
<?php endif; ?>

<section class="stats" aria-label="Statistik">
    <a class="stat" href="<?= e(url('collection')) ?>"><span class="stat-num"><?= fmt_num($stats['items']) ?></span><span class="stat-label">Einträge</span></a>
    <a class="stat" href="<?= e(url('collection', ['kind' => 'game'])) ?>"><span class="stat-num"><?= fmt_num($stats['games']) ?></span><span class="stat-label">Spiele</span></a>
    <a class="stat" href="<?= e(url('platforms', ['mine' => 1])) ?>"><span class="stat-num"><?= fmt_num($stats['platforms']) ?></span><span class="stat-label">Plattformen</span></a>
    <a class="stat" href="<?= e(url('plays')) ?>"><span class="stat-num"><?= fmt_num($stats['plays']) ?></span><span class="stat-label">Durchgespielt</span></a>
    <div class="stat"><span class="stat-num"><?= fmt_num(intdiv($stats['minutes'], 60)) ?>h</span><span class="stat-label">Spielzeit</span></div>
    <div class="stat"><span class="stat-num"><?= $stats['rating'] !== null ? number_format((float)$stats['rating'], 1, ',', '') : '–' ?></span><span class="stat-label">Ø Sterne</span></div>
</section>

<div class="cols">
    <section class="panel">
        <h2 class="panel-title">Neu in der Sammlung</h2>
        <?php if (!$recentItems): ?>
            <p class="empty">Noch leer. <a href="<?= e(url('item/edit')) ?>">Ersten Eintrag anlegen</a> – INSERT COIN!</p>
        <?php else: ?>
        <ul class="minilist">
            <?php foreach ($recentItems as $it): ?>
            <li>
                <a href="<?= e(url('item', ['id' => $it['id']])) ?>">
                    <?php if ($it['photo_id']): ?>
                        <img src="<?= e(url('photo', ['id' => $it['photo_id'], 's' => 't'])) ?>" alt="" width="40" height="40" loading="lazy">
                    <?php elseif ($thumb = tgdb_image($it['boxart_thumb'])): ?>
                        <img src="<?= e($thumb) ?>" alt="" width="40" height="40" loading="lazy">
                    <?php else: ?>
                        <span class="noimg" aria-hidden="true">?</span>
                    <?php endif; ?>
                    <span><strong><?= e($it['title']) ?></strong><small><?= e($it['platform'] ?? Options::KINDS[$it['kind']] ?? '') ?></small></span>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </section>

    <section class="panel">
        <h2 class="panel-title">Zuletzt durchgespielt</h2>
        <?php if (!$recentPlays): ?>
            <p class="empty">Noch nichts eingetragen. <a href="<?= e(url('play/edit')) ?>">STAGE CLEAR melden</a></p>
        <?php else: ?>
        <ul class="minilist">
            <?php foreach ($recentPlays as $pl): ?>
            <li>
                <a href="<?= e(url('play/edit', ['id' => $pl['id']])) ?>">
                    <span class="noimg" aria-hidden="true">🏆</span>
                    <span><strong><?= e($pl['title']) ?></strong><small><?= e($pl['platform'] ?? '') ?><?= $pl['finished_on'] ? ' · ' . e(fmt_date($pl['finished_on'])) : '' ?></small></span>
                    <?= stars($pl['rating'] !== null ? (int)$pl['rating'] : null, false) ?>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </section>
</div>

<?php if ($topPlatforms): ?>
<section class="panel">
    <h2 class="panel-title">Top-Plattformen</h2>
    <ul class="bars">
        <?php $max = max(array_column($topPlatforms, 'cnt')); foreach ($topPlatforms as $tp): ?>
        <li>
            <a href="<?= e(url('collection', ['platform' => $tp['id']])) ?>"><?= e($tp['name']) ?></a>
            <meter min="0" max="<?= (int)$max ?>" value="<?= (int)$tp['cnt'] ?>"><?= (int)$tp['cnt'] ?></meter>
            <span class="bars-num"><?= (int)$tp['cnt'] ?></span>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php if ($stats['value'] > 0): ?><p class="muted">Gesamter Kaufwert: <strong><?= e(fmt_money($stats['value'])) ?></strong></p><?php endif; ?>
</section>
<?php endif; ?>
