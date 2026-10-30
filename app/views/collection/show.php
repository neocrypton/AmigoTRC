<?php
use Amigo\Core\Options;

$owner ??= null;
$cover = tgdb_image($game['boxart_large'] ?? null) ?? tgdb_image($game['boxart_thumb'] ?? null);
?>
<div class="pagehead">
    <div>
        <?php if ($owner): ?>
        <p class="crumbs"><a href="<?= e(url('community')) ?>">Community</a> › <a href="<?= e(url('u', ['name' => $owner['username']])) ?>"><?= e($owner['username']) ?></a> › <a href="<?= e(url('u/collection', ['name' => $owner['username']])) ?>">Sammlung</a></p>
        <?php else: ?>
        <p class="crumbs"><a href="<?= e(url('collection')) ?>">Sammlung</a><?php if ($platform): ?> › <a href="<?= e(url('collection', ['platform' => $platform['id']])) ?>"><?= e($platform['name']) ?></a><?php endif; ?></p>
        <?php endif; ?>
        <h1 class="h1"><?= e($item['title']) ?></h1>
    </div>
    <?php if (!$owner): ?>
    <div class="btnrow">
        <a class="btn btn-primary" href="<?= e(url('item/edit', ['id' => $item['id']])) ?>">✎ Bearbeiten</a>
        <?php if ($item['kind'] === 'game'): ?>
        <a class="btn" href="<?= e(url('play/edit', ['game' => $item['game_id'], 'platform' => $item['platform_id']])) ?>">🏆 Durchgespielt</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<div class="detail">
    <section class="panel">
        <h2 class="panel-title">Fotos <span class="count"><?= count($photos) ?></span></h2>
        <?php if (!$photos): ?>
            <p class="empty">Keine Fotos.<?php if (!$owner): ?> <a href="<?= e(url('item/edit', ['id' => $item['id']])) ?>#photos">Fotos hinzufügen</a><?php endif; ?></p>
            <?php if ($cover): ?><img class="cover" src="<?= e($cover) ?>" alt="Cover von <?= e($item['title']) ?> (TheGamesDB)" loading="lazy"><?php endif; ?>
        <?php else: ?>
        <ul class="gallery">
            <?php foreach ($photos as $ph): ?>
            <li>
                <a href="#ph-<?= (int)$ph['id'] ?>">
                    <img src="<?= e(url('photo', ['id' => $ph['id'], 's' => 't'])) ?>" alt="<?= e($ph['caption'] ?? 'Foto') ?>" loading="lazy" decoding="async">
                </a>
                <?php if ($ph['caption']): ?><p class="caption"><?= e($ph['caption']) ?></p><?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php foreach ($photos as $i => $ph): ?>
        <div class="lightbox" id="ph-<?= (int)$ph['id'] ?>" role="dialog" aria-label="Foto <?= $i + 1 ?> von <?= count($photos) ?>">
            <a class="lightbox-close" href="#_" aria-label="Schließen">✕</a>
            <figure>
                <img src="<?= e(url('photo', ['id' => $ph['id']])) ?>" alt="<?= e($ph['caption'] ?? 'Foto') ?>" loading="lazy" width="<?= (int)$ph['width'] ?>" height="<?= (int)$ph['height'] ?>">
                <figcaption>
                    <?php if ($i > 0): ?><a href="#ph-<?= (int)$photos[$i - 1]['id'] ?>" aria-label="Vorheriges">◀</a><?php endif; ?>
                    <span><?= e($ph['caption'] ?? '') ?> <small class="muted"><?= $i + 1 ?>/<?= count($photos) ?></small></span>
                    <?php if ($i < count($photos) - 1): ?><a href="#ph-<?= (int)$photos[$i + 1]['id'] ?>" aria-label="Nächstes">▶</a><?php endif; ?>
                </figcaption>
            </figure>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <section class="panel">
        <h2 class="panel-title">Daten</h2>
        <dl class="specs">
            <dt>Art</dt><dd><?= e(Options::KINDS[$item['kind']] ?? $item['kind']) ?></dd>
            <?php if ($platform): ?><dt>Plattform</dt><dd><a href="<?= e(url('platform', ['id' => $platform['id']])) ?>"><?= e($platform['name']) ?></a></dd><?php endif; ?>
            <?php if ($item['region']): ?><dt>Region</dt><dd><?= e(Options::REGIONS[$item['region']] ?? $item['region']) ?></dd><?php endif; ?>
            <?php if ($item['completeness']): ?><dt>Umfang</dt><dd><?= e(Options::COMPLETENESS[$item['completeness']] ?? '') ?></dd><?php endif; ?>
            <?php if ($item['purchase_date']): ?><dt>Gekauft am</dt><dd><?= e(fmt_date($item['purchase_date'])) ?></dd><?php endif; ?>
            <?php if ($item['price_cents'] !== null): ?><dt>Preis</dt><dd><?= e(fmt_money((int)$item['price_cents'])) ?></dd><?php endif; ?>
            <?php if ($item['purchase_place']): ?><dt>Gekauft bei</dt><dd><?= e($item['purchase_place']) ?></dd><?php endif; ?>
        </dl>

        <h3 class="h3">Zustand</h3>
        <dl class="specs">
            <dt>Gesamt</dt><dd><?= $item['cond_overall'] !== null ? cond_badge((int)$item['cond_overall']) : '<span class="muted">–</span>' ?></dd>
            <dt><?= $item['kind'] === 'game' ? 'Modul/Disc' : 'Gerät' ?></dt><dd><?= cond_badge($item['cond_media'] !== null ? (int)$item['cond_media'] : null) ?></dd>
            <dt>Verpackung</dt><dd><?= cond_badge($item['cond_box'] !== null ? (int)$item['cond_box'] : null) ?></dd>
            <dt>Anleitung</dt><dd><?= cond_badge($item['cond_manual'] !== null ? (int)$item['cond_manual'] : null) ?></dd>
        </dl>
        <?php if ($item['condition_notes']): ?><div class="text"><?= nl2br(e($item['condition_notes'])) ?></div><?php endif; ?>

        <?php if ($item['notes']): ?>
        <h3 class="h3">Notizen</h3>
        <div class="text"><?= nl2br(e($item['notes'])) ?></div>
        <?php endif; ?>
    </section>
</div>

<?php if ($game): ?>
<section class="panel gameinfo">
    <h2 class="panel-title">Spielinfo <small class="muted">TheGamesDB</small></h2>
    <div class="gameinfo-body">
        <?php if ($cover && $photos): ?><img class="cover" src="<?= e($cover) ?>" alt="" loading="lazy"><?php endif; ?>
        <div>
            <dl class="specs">
                <?php if ($game['release_date']): ?><dt>Erschienen</dt><dd><?= e(fmt_date($game['release_date'])) ?></dd><?php endif; ?>
                <?php if ($game['developers']): ?><dt>Entwickler</dt><dd><?= e($game['developers']) ?></dd><?php endif; ?>
                <?php if ($game['publishers']): ?><dt>Publisher</dt><dd><?= e($game['publishers']) ?></dd><?php endif; ?>
                <?php if ($game['genres']): ?><dt>Genre</dt><dd><?= e($game['genres']) ?></dd><?php endif; ?>
                <?php if ($game['players']): ?><dt>Spieler</dt><dd><?= (int)$game['players'] ?></dd><?php endif; ?>
            </dl>
            <?php if ($game['overview']): ?><details><summary>Beschreibung</summary><div class="text"><?= nl2br(e($game['overview'])) ?></div></details><?php endif; ?>
            <?php if ($game['tgdb_id']): ?><p><a href="https://thegamesdb.net/game.php?id=<?= (int)$game['tgdb_id'] ?>" target="_blank" rel="noopener noreferrer">Auf TheGamesDB ansehen ↗</a></p><?php endif; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($plays): ?>
<section class="panel">
    <h2 class="panel-title">Durchgespielt</h2>
    <ul class="minilist">
        <?php foreach ($plays as $pl): ?>
        <li><a href="<?= e($owner ? url('u/plays', ['name' => $owner['username']]) : url('play/edit', ['id' => $pl['id']])) ?>"><span class="noimg">🏆</span><span><strong><?= e(fmt_date($pl['finished_on'])) ?: 'ohne Datum' ?></strong><small><?= e(fmt_playtime($pl['playtime_minutes'] !== null ? (int)$pl['playtime_minutes'] : null)) ?></small></span><?= stars($pl['rating'] !== null ? (int)$pl['rating'] : null, false) ?></a></li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>

<?php if (!$owner): ?>
<form method="post" action="<?= e(url('item/delete')) ?>" class="danger-zone" data-confirm="Diesen Eintrag inkl. aller Fotos wirklich löschen?">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
    <button class="btn btn-danger btn-sm" type="submit">🗑 Eintrag löschen</button>
</form>
<?php endif; ?>
