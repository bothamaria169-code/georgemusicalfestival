<?php
/** /unsubscribe?e=EMAIL&t=TOKEN — stops campaign emails to that address (signed link from each email). */
require __DIR__ . '/includes/bootstrap.php';

$email = mb_strtolower(trim((string) ($_GET['e'] ?? $_POST['e'] ?? '')));
$token = (string) ($_GET['t'] ?? $_POST['t'] ?? '');
$valid = filter_var($email, FILTER_VALIDATE_EMAIL) && hash_equals(campaign_unsubscribe_token($email), $token);
$done = false;
if ($valid && $_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    q('INSERT IGNORE INTO email_suppressions (email, reason, created_at) VALUES (?, ?, ?)', [$email, 'unsubscribed', now()]);
    q("UPDATE email_queue SET status = 'skipped', error = 'unsubscribed' WHERE email = ? AND status = 'queued'", [$email]);
    $done = true;
}

$page_title = 'Unsubscribe — George Musical Festival';
$extra_css  = ['assets/css/comp.css'];
require __DIR__ . '/includes/header.php';
?>
<section class="auth-wrap" style="grid-template-columns:1fr">
  <div class="auth-form">
    <div class="ff-card"><div class="ff-body ff-center">
      <div class="ff-icon"><i class="fa-regular fa-envelope"></i></div>
      <?php if (!$valid): ?>
        <h1 class="ff-h">Link not valid</h1>
        <p class="ff-sub">This unsubscribe link is incomplete. Email <?= e(setting('contact_email')) ?> and we'll remove you.</p>
      <?php elseif ($done || campaign_suppressed($email)): ?>
        <h1 class="ff-h">You're unsubscribed</h1>
        <p class="ff-sub"><strong><?= e(mask_email($email)) ?></strong> won't receive these emails again. Codes you request yourself (to enter or sign in) still arrive.</p>
      <?php else: ?>
        <h1 class="ff-h">Unsubscribe?</h1>
        <p class="ff-sub">Stop emails like this to <strong><?= e(mask_email($email)) ?></strong>.</p>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="e" value="<?= e($email) ?>"><input type="hidden" name="t" value="<?= e($token) ?>">
          <button class="ff-btn ff-btn-primary" type="submit">Unsubscribe me</button>
        </form>
      <?php endif; ?>
    </div></div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
