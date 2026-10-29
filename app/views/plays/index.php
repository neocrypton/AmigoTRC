<?php
$params = array_filter(['q' => $filter['q'], 'platform' => $filter['platform'], 'rating' => $filter['rating'], 'year' => $filter['year'], 'sort' => $filter['sort'] !== 'date' ? $filter['sort'] : null]);
?>
<div class="pagehead">
    <h1 class="h1">Durchgespielt <span class="count"><?= fmt_num((int)$summary['cnt']) ?></span></h1>
    <a class="btn btn-primary" href="<?= e(url('play/edit')) ?>">+ Eintragen</a>
</div>

<section class="stats stats-sm" aria-label="Zusammenfassung">
    <div class="stat"><span class="stat-num"><?= fmt_num((int)$summary['cnt']) ?></span><span class="stat-label">Spiele</span></div>
    <div class="stat"><span class="stat-num"><?= fmt_num(intdiv((int)$summary['minutes'], 60)) ?>h</span><span class="stat-label">Spielzeit</span></div>
    <div class="stat"><span class="stat-num"><?= $summary['rating'] !== null ? number_format((float)$summary['rating'], 1, ',', '') : '–' ?></span><span class="stat-label">Ø Sterne</span></div>
</section>

<form class="filters" method="get" action="index.php" role="search">
    <input type="hidden" name="r" value="plays">
    <input type="search" name="q" value="<?= e($filter['q']) ?>" placeholder="Titel oder Bewertung…" aria-label="Suchbegriff">
    <select name="platform" aria-label="Plattform">
        <option value="">Alle Plattformen</option>
        <?php foreach ($platforms as $p): ?>
        <option value="<?= (int)$p['id'] ?>"<?= selected($p['id'], $filter['platform']) ?>><?= e($p['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="year" aria-label="Jahr">
        <option value="">Alle Jahre</option>
        <?php foreach ($years as $y): ?><option value="<?= $y ?>"<?= selected($y, $filter['year']) ?>><?= $y ?></option><?php endforeach; ?>
    </select>
    <select name="rating" aria-label="Sterne">
        <option value="">Alle Wertungen</option>
        <?php for ($s = 5; $s >= 1; $s--): ?><option value="<?= $s ?>"<?= selected($s, $filter['rating']) ?>><?= str_repeat('★', $s) ?></option><?php endfor; ?>
    </select>
    <select name="sort" aria-label="Sortierung">
        <?php foreach ($sorts as $k => $label): ?><option value="<?= e($k) ?>"<?= selected($k, $filter['sort']) ?>><?= e($label) ?></option><?php endforeach; ?>
    </select>
    <button class="btn" type="submit">Filtern</button>
</form>

<?php if (!$plays): ?>
    <div class="panel empty-state">
        <p class="logo-type">NO SAVE DATA</p>
        <p>Hier landen alle Spiele, die du durchgespielt hast – mit Spielzeit, Punkten und Wertung.</p>
        <a class="btn btn-primary" href="<?= e(url('play/edit')) ?>">+ Erstes Spiel eintragen</a>
    </div>
<?php else: ?>
<ol class="plays">
    <?php foreach ($plays as $pl): ?>
    <li class="play">
        <a class="play-main" href="<?= e(url('play/edit', ['id' => $pl['id']])) ?>">
            <?php if ($thumb = tgdb_image($pl['boxart_thumb'])): ?>
                <img src="<?= e($thumb) ?>" alt="" width="56" height="74" loading="lazy" decoding="async">
            <?php else: ?>
                <span class="play-noimg" aria-hidden="true">🏆</span>
            <?php endif; ?>
            <span class="play-body">
                <span class="play-title"><?= e($pl['title']) ?></span>
                <span class="play-meta">
                    <?= e($pl['platform_name'] ?? 'ohne Plattform') ?>
                    <?php if ($pl['finished_on']): ?> · <?= e(fmt_date($pl['finished_on'])) ?><?php endif; ?>
                </span>
                <span class="play-facts">
                    <?php if ($pl['playtime_minutes'] !== null): ?><span class="chip">⏱ <?= e(fmt_playtime((int)$pl['playtime_minutes'])) ?></span><?php endif; ?>
                    <?php if ($pl['score'] !== null): ?><span class="chip">◆ <?= e(fmt_num((int)$pl['score'])) ?> Pkt.</span><?php endif; ?>
                    <?= stars($pl['rating'] !== null ? (int)$pl['rating'] : null, false) ?>
                </span>
                <?php if ($pl['review']): ?><span class="play-review"><?= e(mb_strimwidth($pl['review'], 0, 220, '…')) ?></span><?php endif; ?>
            </span>
        </a>
    </li>
    <?php endforeach; ?>
</ol>
<?php endif; ?>

<?php $route = 'plays'; require __DIR__ . '/../partials/pager.php'; ?>

<p class="muted center"><a href="<?= e(url('export', ['type' => 'plays'])) ?>">⤓ Liste als CSV exportieren</a></p>
