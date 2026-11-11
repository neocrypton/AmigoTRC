<?php
/**
 * Textfeld mit CKEditor (Website-Texte). Ohne JavaScript bleibt ein HTML-Textfeld.
 * @var string $editorName  @var string $editorLabel  @var string $editorValue
 */
?>
<div class="field editor-field">
    <label for="ed-<?= e($editorName) ?>"><?= e($editorLabel) ?></label>
    <textarea id="ed-<?= e($editorName) ?>" name="<?= e($editorName) ?>" rows="16" data-editor
              data-upload="<?= e(url('admin/site/upload')) ?>" data-csrf="<?= e(\Amigo\Core\Csrf::token()) ?>"><?= e($editorValue) ?></textarea>
    <p class="hint" data-editor-count>Editor wird geladen…</p>
</div>
