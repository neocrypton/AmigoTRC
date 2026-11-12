<?php
use Amigo\Core\Badges;
use Amigo\Core\Options;

$fmt1 = fn (float $v): string => number_format($v, 1, ',', '.');
/** Säulendiagramm (SVG, ohne Inline-Styles wegen CSP): ein Balken pro Tag, Tooltip per <title>. */
$chart = function (array $series, string $label) use ($fmt1): string {
    $n = count($series);
    $max = max(1, max($series));
    $w = 10; $gap = 2; $h = 80;
    $out = '<svg class="daychart" viewBox="0 0 ' . ($n * $w) . ' ' . ($h + 2) . '" preserveAspectRatio="none" role="img" aria-label="' . e($label) . ' pro Tag, letzte ' . $n . ' Tage">';
    $i = 0;
    foreach ($series as $day => $v) {
        $bh = $v > 0 ? max(2, round($v / $max * $h, 1)) : 0;
        $x = $i * $w + $gap / 2;
        $title = e(date('d.m.Y', strtotime($day)) . ': ' . $v . ' ' . $label);
        // unsichtbare Trefferfläche über die ganze Höhe, damit auch kleine Balken antippbar sind
        $out .= '<g><title>' . $title . '</title><rect class="daychart-hit" x="' . ($i * $w) . '" y="0" width="' . $w . '" height="' . ($h + 2) . '"/>'
            . ($bh > 0 ? '<rect class="daychart-bar" x="' . $x . '" y="' . ($h - $bh) . '" width="' . ($w - $gap) . '" height="' . $bh . '"/>' : '')
            . '</g>';
        $i++;
    }
    $out .= '<line class="daychart-base" x1="0" y1="' . $h . '" x2="' . ($n * $w) . '" y2="' . $h . '"/></svg>';
    return $out;
};
$sum = fn (array $s): int => array_sum($s);
$firstDay = array_key_first($users);
$lastDay = array_key_last($users);
$daysTxt = date('d.m.', strtotime($firstDay)) . ' – ' . date('d.m.Y', strtotime($lastDay));
?>
<div class="pagehead">
    <h1 class="h1">Statistik</h1>
    <a class="btn btn-ghost" href="<?= e(url('admin/settings')) ?>">⚙ System</a>
</div>

<div class="stats">
    <div class="stat"><span class="stat-num"><?= fmt_num($userTotal) ?></span><span class="stat-label">Nutzer</span></div>
    <div class="stat"><span class="stat-num"><?= $fmt1($avgUsers['avg']) ?></span><span class="stat-label">Ø neue Nutzer / Tag</span></div>
    <div class="stat"><span class="stat-num"><?= fmt_num($avgItems['total']) ?></span><span class="stat-label">Sammlungs-Einträge</span></div>
    <div class="stat"><span class="stat-num"><?= $fmt1($avgItems['avg']) ?></span><span class="stat-label">Ø Sammlung / Tag</span></div>
    <div class="stat"><span class="stat-num"><?= fmt_num($avgPlays['total']) ?></span><span class="stat-label">Durchgespielt</span></div>
    <div class="stat"><span class="stat-num"><?= $fmt1($avgPlays['avg']) ?></span><span class="stat-label">Ø Durchgespielt / Tag</span></div>
</div>
<p class="hint">Ø pro Tag = alle Einträge geteilt durch die Tage seit dem ersten Eintrag (Sammlung: <?= (int)$avgItems['days'] ?> Tage, Durchgespielt: <?= (int)$avgPlays['days'] ?> Tage, Nutzer: <?= (int)$avgUsers['days'] ?> Tage).</p>

<section class="panel">
    <h2 class="panel-title">Letzte <?= (int)$days ?> Tage <small class="muted"><?= e($daysTxt) ?></small></h2>
    <?php foreach ([['Neue Nutzer', $users, 'Nutzer'], ['Neue Sammlungs-Einträge', $items, 'Einträge'], ['Neue Durchgespielt-Einträge', $plays, 'Einträge']] as [$title, $series, $unit]): ?>
    <figure class="daychart-fig">
        <figcaption>
            <strong><?= e($title) ?></strong>
            <span class="muted"><?= fmt_num($sum($series)) ?> gesamt · Ø <?= $fmt1($sum($series) / count($series)) ?> / Tag · max. <?= fmt_num(max($series)) ?></span>
        </figcaption>
        <?= $chart($series, $unit) ?>
        <div class="daychart-axis muted"><span><?= e(date('d.m.', strtotime($firstDay))) ?></span><span>heute</span></div>
    </figure>
    <?php endforeach; ?>
    <details class="daychart-table">
        <summary>Als Tabelle anzeigen</summary>
        <table class="tbl">
            <thead><tr><th>Tag</th><th>Nutzer</th><th>Sammlung</th><th>Durchgespielt</th></tr></thead>
            <tbody>
            <?php foreach (array_reverse(array_keys($users)) as $day): ?>
                <tr><td><?= e(date('d.m.Y', strtotime($day))) ?></td><td><?= (int)$users[$day] ?></td><td><?= (int)$items[$day] ?></td><td><?= (int)$plays[$day] ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </details>
</section>

<div class="cols">
    <section class="panel">
        <h2 class="panel-title">Farbschemata</h2>
        <?php $tmax = max(1, max($themes), $themeUnknown); ?>
        <ul class="bars">
            <?php foreach ($themes as $key => $n): ?>
            <li><span><?= e(Options::THEMES[$key]) ?></span><meter min="0" max="<?= $tmax ?>" value="<?= (int)$n ?>"></meter><span class="bars-num"><?= (int)$n ?></span></li>
            <?php endforeach; ?>
            <?php if ($themeUnknown): ?>
            <li class="muted"><span>noch unbekannt</span><meter min="0" max="<?= $tmax ?>" value="<?= (int)$themeUnknown ?>"></meter><span class="bars-num"><?= (int)$themeUnknown ?></span></li>
            <?php endif; ?>
        </ul>
        <p class="hint">Zuletzt genutztes Farbschema je Nutzer. „Noch unbekannt“ = seit dem Update nicht angemeldet.</p>
    </section>

    <section class="panel">
        <h2 class="panel-title">Avatare</h2>
        <?php
        $chosen = array_filter($badges);
        $bmax = max(1, $chosen ? max($chosen) : 0, $custom, $auto);
        ?>
        <ul class="bars bars-badges">
            <?php foreach ($chosen as $key => $n): ?>
            <li>
                <span class="bars-badge"><svg width="24" height="24" aria-hidden="true"><use href="<?= e(asset('img/badges.svg')) ?>#<?= e($key) ?>"></use></svg><?= e(Badges::ALL[$key]) ?></span>
                <meter min="0" max="<?= $bmax ?>" value="<?= (int)$n ?>"></meter><span class="bars-num"><?= (int)$n ?></span>
            </li>
            <?php endforeach; ?>
            <li><span class="bars-badge"><span class="bars-badge-ico" aria-hidden="true">🖼</span>Eigenes Bild</span><meter min="0" max="<?= $bmax ?>" value="<?= (int)$custom ?>"></meter><span class="bars-num"><?= (int)$custom ?></span></li>
            <li class="muted"><span class="bars-badge"><span class="bars-badge-ico" aria-hidden="true">🎲</span>Automatisch</span><meter min="0" max="<?= $bmax ?>" value="<?= (int)$auto ?>"></meter><span class="bars-num"><?= (int)$auto ?></span></li>
        </ul>
        <p class="hint"><?= count($chosen) ?> von <?= count($badges) ?> Badges werden genutzt.</p>
    </section>
</div>
