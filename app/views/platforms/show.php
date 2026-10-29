<?php use Amigo\Core\Auth; ?>
<div class="pagehead">
    <div>
        <p class="crumbs"><a href="<?= e(url('platforms')) ?>">Plattformen</a></p>
        <h1 class="h1">
            <?php if ($icon = platform_icon($platform['icon'])): ?><img src="<?= e($icon) ?>" alt="" width="40" height="40" class="h1-icon"><?php endif; ?>
            <?= e($platform['name']) ?>
        </h1>
    </div>
    <div class="btnrow">
        <a class="btn btn-primary" href="<?= e(url('item/edit', ['platform' => $platform['id']])) ?>">+ Eintrag</a>
        <?php if (Auth::isAdmin()): ?><a class="btn btn-ghost" href="<?= e(url('admin/platform/edit', ['id' => $platform['id']])) ?>">✎</a><?php endif; ?>
    </div>
</div>

<section class="stats stats-sm">
    <a class="stat" href="<?= e(url('collection', ['platform' => $platform['id']])) ?>"><span class="stat-num"><?= $counts['items'] ?></span><span class="stat-label">In der Sammlung</span></a>
    <a class="stat" href="<?= e(url('plays', ['platform' => $platform['id']])) ?>"><span class="stat-num"><?= $counts['plays'] ?></span><span class="stat-label">Durchgespielt</span></a>
</section>

<section class="panel">
    <h2 class="panel-title">Technik</h2>
    <dl class="specs">
        <?php foreach (['manufacturer' => 'Hersteller', 'developer' => 'Entwickler', 'alias' => 'Auch bekannt als', 'media' => 'Medium', 'cpu' => 'CPU', 'memory' => 'Speicher'] as $k => $label): ?>
            <?php if (!empty($platform[$k])): ?><dt><?= e($label) ?></dt><dd><?= e($platform[$k]) ?></dd><?php endif; ?>
        <?php endforeach; ?>
    </dl>
    <?php if ($platform['overview']): ?><details><summary>Beschreibung</summary><div class="text"><?= nl2br(e($platform['overview'])) ?></div></details><?php endif; ?>
    <?php if ($platform['tgdb_id']): ?><p><a href="https://thegamesdb.net/platform.php?id=<?= (int)$platform['tgdb_id'] ?>" target="_blank" rel="noopener noreferrer">Auf TheGamesDB ansehen ↗</a></p><?php endif; ?>
</section>
