<?php
/** Replies people sent to GMF emails, newest first, with one-click answers from a template. */
require __DIR__ . '/_layout.php';
require dirname(__DIR__) . '/includes/replies.php';

$admin = require_admin('admin');
campaign_schema();
replies_schema();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    if ($action === 'check') {
        try {
            set_time_limit(120);
            [$added, $stopped] = replies_fetch(100);
            flash('success', $added ? number_format($added) . ' new repl' . ($added === 1 ? 'y' : 'ies') . ($stopped ? ', ' . $stopped . ' asked to be removed and will not be emailed again' : '') . '.' : 'No new replies.');
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }
    } elseif ($action === 'done' || $action === 'reopen') {
        q('UPDATE email_replies SET status = ? WHERE id = ?', [$action === 'done' ? 'done' : 'new', $id]);
    } elseif ($action === 'remove') {
        $email = (string) q('SELECT from_email FROM email_replies WHERE id = ?', [$id])->fetchColumn();
        if ($email !== '') {
            q('INSERT IGNORE INTO email_suppressions (email, reason, created_at) VALUES (?, ?, ?)', [$email, 'removed by admin', now()]);
            q("UPDATE email_queue SET status = 'skipped', error = 'asked to be removed' WHERE email = ? AND status = 'queued'", [$email]);
            q("UPDATE email_replies SET status = 'done', unsubscribe = 1 WHERE id = ?", [$id]);
            audit('reply.unsubscribe', ['email' => mask_email($email)]);
            flash('success', 'They will not be sent any more emails.');
        }
    }
    redirect('admin/replies.php' . (isset($_GET['show']) ? '?show=' . rawurlencode((string) $_GET['show']) : ''));
}

$show = ($_GET['show'] ?? 'new') === 'all' ? 'all' : 'new';
$rows = q('SELECT r.*, c.name AS campaign FROM email_replies r LEFT JOIN email_campaigns c ON c.id = r.campaign_id'
    . ($show === 'new' ? " WHERE r.status = 'new'" : '') . ' ORDER BY COALESCE(r.received_at, r.created_at) DESC LIMIT 200')->fetchAll();
$counts = q("SELECT SUM(status = 'new') n, COUNT(*) t, SUM(unsubscribe) u FROM email_replies")->fetch();
$checked = (string) setting('replies_checked_at', '');
$brands = campaign_brands();

admin_head('Replies', 'replies');
?>
<div class="toolbar" style="align-items:center">
  <a class="btn <?= $show === 'new' ? 'btn-primary' : '' ?>" href="?show=new">To answer (<?= number_format((int) $counts['n']) ?>)</a>
  <a class="btn <?= $show === 'all' ? 'btn-primary' : '' ?>" href="?show=all">All replies (<?= number_format((int) $counts['t']) ?>)</a>
  <span class="grow muted" style="font-size:.84rem"><?= $checked ? 'Last checked ' . e(fmt_date($checked, 'j M H:i')) : 'Not checked yet' ?> · <?= number_format((int) $counts['u']) ?> asked to be removed</span>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="check"><button class="btn" type="submit"><i class="fa-solid fa-rotate"></i> Check for replies now</button></form>
</div>

<?php if (!$rows): ?>
  <div class="card"><p class="muted" style="margin:0"><?= $show === 'new' ? 'Nothing waiting for an answer.' : 'No replies yet.' ?> Replies to emails from info@georgemusicalfestival.co.za appear here every 10 minutes.</p></div>
<?php endif; ?>

<?php foreach ($rows as $r):
    $brand = $r['brand'] ?: 'acsa';
    $subject = preg_match('/^re:/i', (string) $r['subject']) ? (string) $r['subject'] : 'Re: ' . $r['subject'];
    $replyUrl = url('admin/compose.php?' . http_build_query(['brand' => $brand, 'to' => $r['from_email'], 'tpl' => 'b:' . $brand . '-info', 'subject' => $subject])); ?>
  <article class="card reply <?= $r['status'] === 'done' ? 'is-done' : '' ?>">
    <header class="reply-head">
      <div>
        <strong><?= e($r['from_name'] ?: $r['from_email']) ?></strong>
        <?php if ($r['from_name']): ?><span class="muted"> · <?= e($r['from_email']) ?></span><?php endif; ?>
        <div class="reply-subject"><?= e($r['subject'] ?: '(no subject)') ?></div>
      </div>
      <div class="reply-meta">
        <?php if ($r['unsubscribe']): ?><span class="pill pill-bad">Asked to be removed</span><?php endif; ?>
        <?php if ($r['brand']): ?><span class="pill pill-info"><?= e($brands[$r['brand']]['label'] ?? $r['brand']) ?></span><?php endif; ?>
        <span class="muted"><?= e(fmt_date($r['received_at'] ?: $r['created_at'], 'j M H:i')) ?></span>
      </div>
    </header>
    <?php if ($r['campaign']): ?><p class="muted" style="margin:6px 0 0;font-size:.8rem">Reply to: <?= e($r['campaign']) ?></p><?php endif; ?>
    <div class="reply-body"><?= nl2br(e(mb_strimwidth((string) $r['body'], 0, 3000, '…'))) ?></div>
    <div class="reply-actions">
      <a class="btn btn-primary btn-sm" href="<?= e($replyUrl) ?>"><i class="fa-solid fa-reply"></i> Reply</a>
      <?php if ($r['status'] === 'new'): ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="done"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn btn-sm" type="submit"><i class="fa-solid fa-check"></i> Mark as answered</button></form>
      <?php else: ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="reopen"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn btn-sm" type="submit">Move back to answer</button></form>
      <?php endif; ?>
      <?php if (!$r['unsubscribe']): ?>
        <form method="post" onsubmit="return confirm('Stop all emails to <?= e($r['from_email']) ?>?')"><?= csrf_field() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn btn-sm" type="submit"><i class="fa-solid fa-ban"></i> Stop emailing them</button></form>
      <?php endif; ?>
    </div>
  </article>
<?php endforeach; ?>
<?php admin_foot();
