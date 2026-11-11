<?php use Amigo\Controller\SiteController; ?>
<article class="panel prose"><?= SiteController::html($page['body']) ?></article>

<?php if ($faq): ?>
<section class="faq" aria-label="Fragen und Antworten">
    <?php foreach ($faq as $f): ?>
    <details class="panel faq-item" id="faq-<?= (int)$f['id'] ?>">
        <summary><?= e($f['question']) ?></summary>
        <div class="prose"><?= SiteController::html($f['answer']) ?></div>
    </details>
    <?php endforeach; ?>
</section>
<?php endif; ?>
