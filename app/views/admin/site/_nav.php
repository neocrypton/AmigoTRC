<?php /** @var string $siteTab */ ?>
<nav class="btnrow site-adminnav" aria-label="Website-Verwaltung">
    <a class="btn btn-sm<?= $siteTab === 'index' ? ' btn-primary' : '' ?>" href="<?= e(url('admin/site')) ?>">🌐 Übersicht</a>
    <a class="btn btn-sm<?= $siteTab === 'faq' ? ' btn-primary' : '' ?>" href="<?= e(url('admin/site/faq')) ?>">❓ FAQ</a>
    <a class="btn btn-sm<?= $siteTab === 'news' ? ' btn-primary' : '' ?>" href="<?= e(url('admin/site/news')) ?>">📰 News</a>
    <a class="btn btn-sm<?= $siteTab === 'links' ? ' btn-primary' : '' ?>" href="<?= e(url('admin/site/links')) ?>">🔗 Links &amp; Partner</a>
    <a class="btn btn-sm btn-ghost" href="<?= e(url('site')) ?>" target="_blank">👁 Website ansehen</a>
</nav>
