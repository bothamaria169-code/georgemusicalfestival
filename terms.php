<?php
require __DIR__ . '/includes/bootstrap.php';

$isPrivacy  = basename($_SERVER['SCRIPT_FILENAME']) === 'privacy.php' || basename($_SERVER['SCRIPT_NAME']) === 'privacy.php';
$page_title = ($isPrivacy ? 'Privacy notice' : 'Competition terms') . ' — ' . setting('comp_name');
$nav_active = 'fly';
$body_class = 'page-light';
require __DIR__ . '/includes/header.php';
?>
<section class="page-hero on-paper">
  <div class="wrap">
    <span class="kicker" style="color:var(--orange-deep)"><?= e(setting('comp_name')) ?> · GMF × ACSA</span>
    <h1 class="display huge" style="color:var(--text-dark)"><?= $isPrivacy ? 'Privacy <span class="serif-i" style="color:var(--orange-deep)">notice</span>' : 'Terms &amp; <span class="serif-i" style="color:var(--orange-deep)">conditions</span>' ?></h1>
  </div>
</section>
<section class="section-sm on-paper" style="padding-top:0">
  <div class="wrap">
    <article class="prose">
      <?php // Rich text edited by GMF administrators in Settings. ?>
      <?= $isPrivacy ? setting('privacy_html') : setting('terms_html') ?>
      <hr class="rule" style="margin:40px 0 20px">
      <p class="muted">Competition period: <?= e(fmt_date(setting('comp_open_at'), 'j F Y')) ?> – <?= e(fmt_date(setting('comp_close_at'), 'j F Y')) ?>. Questions: <a href="mailto:<?= e(setting('contact_email')) ?>" style="text-decoration:underline"><?= e(setting('contact_email')) ?></a></p>
      <p><a class="btn btn-dark" href="<?= e(url($isPrivacy ? 'terms.php' : 'privacy.php')) ?>"><?= $isPrivacy ? 'Competition terms' : 'Privacy notice' ?></a> <a class="btn btn-outline-dark" href="<?= e(url('flyfortwo/')) ?>">Back to the competition</a></p>
    </article>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
