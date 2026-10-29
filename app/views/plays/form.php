<?php
$minutes = isset($play['playtime_minutes']) && $play['playtime_minutes'] !== null ? (int)$play['playtime_minutes'] : null;
$rating = isset($play['rating']) && $play['rating'] !== null ? (int)$play['rating'] : 0;
?>
<div class="pagehead">
    <h1 class="h1"><?= e($title) ?></h1>
    <a class="btn btn-ghost" href="<?= e(url('plays')) ?>">Abbrechen</a>
</div>

<form method="post" action="<?= e(url('play/edit')) ?>" class="form">
    <?= csrf_field() ?>
    <?php if (!empty($play['id'])): ?><input type="hidden" name="id" value="<?= (int)$play['id'] ?>"><?php endif; ?>

    <section class="panel">
        <h2 class="panel-title">Spiel</h2>
        <?php $record = $play; require __DIR__ . '/../partials/game_picker.php'; ?>
    </section>

    <section class="panel">
        <h2 class="panel-title">Ergebnis <small class="muted">alles optional</small></h2>
        <div class="row3">
            <div class="field">
                <label for="f-finished">Durchgespielt am</label>
                <input id="f-finished" type="date" name="finished_on" value="<?= e($play['finished_on'] ?? '') ?>" max="<?= date('Y-m-d') ?>">
            </div>
            <div class="field">
                <span class="label" id="l-time">Spielzeit</span>
                <div class="inputgroup" role="group" aria-labelledby="l-time">
                    <input type="number" name="hours" min="0" max="100000" value="<?= $minutes !== null ? intdiv($minutes, 60) : '' ?>" aria-label="Stunden" placeholder="0"><span>Std</span>
                    <input type="number" name="minutes" min="0" max="59" value="<?= $minutes !== null ? $minutes % 60 : '' ?>" aria-label="Minuten" placeholder="0"><span>Min</span>
                </div>
            </div>
            <div class="field">
                <label for="f-score">Punkte / Highscore</label>
                <input id="f-score" name="score" inputmode="numeric" value="<?= e(isset($play['score']) && $play['score'] !== null ? (string)$play['score'] : '') ?>" maxlength="25" placeholder="z. B. 999990">
            </div>
        </div>

        <fieldset class="field">
            <legend class="label">Deine Wertung</legend>
            <div class="rating-input">
                <?php for ($s = 5; $s >= 1; $s--): ?>
                <input type="radio" id="star<?= $s ?>" name="rating" value="<?= $s ?>"<?= checked($rating === $s) ?>>
                <label for="star<?= $s ?>" title="<?= $s ?> Sterne"><span class="sr"><?= $s ?> Sterne</span>★</label>
                <?php endfor; ?>
            </div>
            <label class="check"><input type="radio" name="rating" value=""<?= checked($rating === 0) ?>> keine Wertung</label>
        </fieldset>

        <div class="field">
            <label for="f-review">Bewertung</label>
            <textarea id="f-review" name="review" rows="5" maxlength="10000" placeholder="Wie war's? Bester Moment, nervigster Boss, …"><?= e($play['review'] ?? '') ?></textarea>
        </div>
    </section>

    <div class="formbar">
        <button class="btn btn-primary" type="submit">💾 Speichern</button>
    </div>
</form>

<?php if (!empty($play['id'])): ?>
<form method="post" action="<?= e(url('play/delete')) ?>" class="danger-zone" data-confirm="Diesen Eintrag wirklich löschen?">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int)$play['id'] ?>">
    <button class="btn btn-danger btn-sm" type="submit">🗑 Eintrag löschen</button>
</form>
<?php endif; ?>
