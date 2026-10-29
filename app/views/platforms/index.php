<?php use Amigo\Core\Auth; ?>
<div class="pagehead">
    <h1 class="h1">Plattformen <span class="count"><?= count($platforms) ?></span></h1>
    <?php if (Auth::isAdmin()): ?><a class="btn" href="<?= e(url('admin/platforms')) ?>">⚙ Importieren / verwalten</a><?php endif; ?>
</div>

<form class="filters" method="get" action="index.php" role="search">
    <input type="hidden" name="r" value="platforms">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Plattform oder Hersteller…" aria-label="Suchbegriff">
    <label class="check"><input type="checkbox" name="mine" value="1"<?= checked($mine) ?>> nur meine</label>
    <button class="btn" type="submit">Filtern</button>
</form>

<?php if (!$platforms): ?>
<div class="panel empty-state">
    <p class="logo-type">NO SIGNAL</p>
    <p>Noch keine Plattformen vorhanden.<?= Auth::isAdmin() ? '' : ' Sie werden automatisch angelegt, sobald du ein Spiel aus TheGamesDB wählst.' ?></p>
    <?php if (Auth::isAdmin()): ?><a class="btn btn-primary" href="<?= e(url('admin/platforms')) ?>">Plattformen aus TheGamesDB importieren</a><?php endif; ?>
</div>
<?php else: ?>
<ul class="platformlist">
    <?php foreach ($platforms as $p): ?>
    <li>
        <a href="<?= e(url('platform', ['id' => $p['id']])) ?>">
            <?php if ($icon = platform_icon($p['icon'])): ?>
                <img src="<?= e($icon) ?>" alt="" width="32" height="32" loading="lazy">
            <?php else: ?>
                <span class="noimg" aria-hidden="true">🕹</span>
            <?php endif; ?>
            <span><strong><?= e($p['name']) ?></strong><small><?= e($p['manufacturer'] ?? '') ?></small></span>
            <span class="platform-counts">
                <?php if ($p['items']): ?><span class="chip" title="Einträge in der Sammlung">📦 <?= (int)$p['items'] ?></span><?php endif; ?>
                <?php if ($p['plays']): ?><span class="chip" title="Durchgespielt">🏆 <?= (int)$p['plays'] ?></span><?php endif; ?>
            </span>
        </a>
    </li>
    <?php endforeach; ?>
</ul>
<?php endif; ?>
