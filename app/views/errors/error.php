<section class="panel gameover">
    <p class="gameover-code"><?= e($code ?? '') ?></p>
    <h1 class="logo-type"><?= e($title ?? 'GAME OVER') ?></h1>
    <p><?= e($message ?? '') ?></p>
    <p class="gameover-continue">CONTINUE? <a class="btn btn-primary" href="<?= e(url()) ?>">▶ Zur Startseite</a></p>
</section>
