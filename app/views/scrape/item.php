<?php use Amigo\Service\Scraper; ?>
<div class="pagehead">
    <div>
        <p class="crumbs"><a href="<?= e($back) ?>"><?= e($record['title']) ?></a> · <a href="<?= e(url('scrape', ['target' => $target])) ?>">Cover &amp; Daten ergänzen</a></p>
        <h1 class="h1">Bei TheGamesDB suchen</h1>
    </div>
    <a class="btn btn-ghost" href="<?= e($back) ?>">Zurück</a>
</div>

<?php if (!$enabled): ?>
<div class="flash flash-warn">Für die Suche brauchst du einen TheGamesDB-API-Key – hinterlege ihn unter <a href="<?= e(url('account')) ?>">Mein Konto</a>.</div>
<?php else: ?>
<form class="filters" method="get" action="index.php" role="search">
    <input type="hidden" name="r" value="scrape/item">
    <input type="hidden" name="target" value="<?= e($target) ?>">
    <input type="hidden" name="id" value="<?= (int)$record['id'] ?>">
    <input type="search" name="q" value="<?= e($q) ?>" aria-label="Suchbegriff" maxlength="100">
    <select name="platform" aria-label="Plattform">
        <option value="">Alle Plattformen</option>
        <?php foreach ($platforms as $p): ?>
        <option value="<?= (int)$p['id'] ?>"<?= selected($p['id'], $platformId ?? '') ?><?= $p['tgdb_id'] ? '' : ' disabled' ?>><?= e($p['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn" type="submit">Suchen</button>
</form>

<?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>

<?php if (!$results && !$error): ?>
<div class="panel empty-state">
    <p class="logo-type">NO MATCH</p>
    <p>Keine Treffer. Versuch einen kürzeren Titel, die englische Schreibweise oder „Alle Plattformen“.</p>
</div>
<?php elseif ($results): ?>
<form method="post" action="<?= e(url('scrape/item')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="target" value="<?= e($target) ?>">
    <input type="hidden" name="id" value="<?= (int)$record['id'] ?>">
    <label class="check"><input type="checkbox" name="adopt_title" value="1"> Titel an TheGamesDB anpassen (statt „<?= e($record['title']) ?>“)</label>
    <ul class="results">
        <?php foreach ($results as $r): $exact = Scraper::normalize($r['title']) === $want; ?>
        <li>
            <button class="result<?= $exact ? ' is-exact' : '' ?>" type="submit" name="tgdb_id" value="<?= (int)$r['tgdb_id'] ?>">
                <?php if ($thumb = tgdb_image($r['thumb'])): ?>
                    <img src="<?= e($thumb) ?>" alt="" width="64" height="88" loading="lazy">
                <?php else: ?>
                    <span class="result-noimg" aria-hidden="true">?</span>
                <?php endif; ?>
                <span class="result-body">
                    <strong><?= e($r['title']) ?></strong>
                    <small><?= e($r['platform']) ?><?= $r['date'] ? ' · ' . e(fmt_date($r['date'])) : '' ?> · TGDB #<?= (int)$r['tgdb_id'] ?></small>
                    <?php if ($exact): ?><span class="chip">✓ gleicher Titel</span><?php endif; ?>
                </span>
                <span class="result-pick">Übernehmen ▶</span>
            </button>
        </li>
        <?php endforeach; ?>
    </ul>
    <p class="hint">Mehrere gleichnamige Treffer? Unterschiedliche Fassungen (z. B. Erst- und Neuauflage) erkennst du am Datum – Details zeigt <a href="https://thegamesdb.net/search.php?name=<?= e(rawurlencode($q)) ?>" target="_blank" rel="noopener noreferrer">TheGamesDB ↗</a>.</p>
</form>
<?php endif; ?>
<?php endif; ?>
