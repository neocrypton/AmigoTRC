<?php use Amigo\Service\Messages; ?>
<div class="pagehead">
    <div>
        <p class="crumbs"><a href="<?= e(url('messages')) ?>">Nachrichten</a></p>
        <h1 class="h1">Neue Nachricht</h1>
    </div>
</div>

<form method="post" action="<?= e(url('message/new')) ?>" class="panel">
    <?= csrf_field() ?>
    <div class="field user-picker" data-user-picker data-api="<?= e(url('api/users')) ?>">
        <label for="pm-to">An <span class="req">*</span></label>
        <input id="pm-to" name="to" value="<?= e($data['to']) ?>" maxlength="1000" autocomplete="off" autocapitalize="none" spellcheck="false" placeholder="Benutzernamen, mit Komma getrennt" required>
        <div class="picker-list" data-user-list hidden></div>
        <p class="hint">Bis zu <?= Messages::MAX_PARTICIPANTS - 1 ?> Empfänger (<?= Messages::MAX_PARTICIPANTS ?> Teilnehmer inkl. dir).</p>
    </div>
    <div class="field">
        <label for="pm-subject">Betreff <span class="req">*</span></label>
        <input id="pm-subject" name="subject" value="<?= e($data['subject']) ?>" maxlength="<?= Messages::SUBJECT_MAX ?>" required>
    </div>
    <div class="field">
        <label for="pm-body">Nachricht <span class="req">*</span></label>
        <?php $target = 'pm-body'; require __DIR__ . '/../partials/smiley_bar.php'; ?>
        <textarea id="pm-body" name="body" rows="8" maxlength="<?= Messages::BODY_MAX ?>" required><?= e($data['body']) ?></textarea>
        <p class="hint">Reiner Text. Smilies per Klick oder mit Kürzeln wie :) :D ;) :cool: :1up:</p>
    </div>
    <div class="formbar">
        <button class="btn btn-primary" type="submit">✉ Senden</button>
        <a class="btn btn-ghost" href="<?= e(url('messages')) ?>">Abbrechen</a>
    </div>
</form>
