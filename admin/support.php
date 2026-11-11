<?php
/**
 * Support: competition help requests from the "Chat with us" button on both websites
 * (FLY FOR TWO in this database, WIN A SAMSUNG in the Cell C database). Answer here; the reply is
 * emailed to the person in that competition's branding.
 */
require __DIR__ . '/_layout.php';
require dirname(__DIR__) . '/includes/campaign-overview.php';
require_once dirname(__DIR__) . '/includes/support.php';

$admin = require_admin('admin');
support_schema();
$cPdo = cellc_pdo();
if ($cPdo) {
    try {
        support_schema($cPdo);
    } catch (Throwable $e) {
        $cPdo = null;
    }
}
$sources = ['acsa' => ['label' => 'FLY FOR TWO', 'pdo' => db()], 'cellc' => ['label' => 'WIN A SAMSUNG', 'pdo' => $cPdo]];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $brand = (string) ($_POST['brand'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    $pdo = $sources[$brand]['pdo'] ?? null;
    $back = 'admin/support.php' . (isset($_GET['show']) ? '?show=' . rawurlencode((string) $_GET['show']) : '');
    if (!$pdo || !$id) {
        flash('error', 'That message could not be found.');
        redirect($back);
    }
    $st = $pdo->prepare('SELECT * FROM support_messages WHERE id = ?');
    $st->execute([$id]);
    $msg = $st->fetch();
    if (!$msg) {
        flash('error', 'That message could not be found.');
        redirect($back);
    }
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'reply') {
        $text = trim((string) ($_POST['reply'] ?? ''));
        if (mb_strlen($text) < 2) {
            flash('error', 'Write a reply first.');
            redirect($back);
        }
        $comp = $sources[$brand]['label'];
        $first = e(explode(' ', (string) $msg['name'])[0]);
        $paras = '';
        foreach (preg_split('/\R{2,}/', $text) ?: [] as $p) {
            $paras .= '<p style="margin:0 0 14px;font-size:15px;line-height:1.6;">' . nl2br(e(trim($p))) . '</p>';
        }
        $quote = '<p style="margin:18px 0 0;padding:12px 14px;background:#f6f3f0;border-radius:10px;font-size:13px;color:#6b645e;line-height:1.5;"><strong>You wrote:</strong><br>'
            . nl2br(e(mb_strimwidth((string) $msg['message'], 0, 600, '…'))) . '</p>';
        $inner = "<p style=\"margin:0 0 14px;font-size:15px;line-height:1.6;\">Hi {$first},</p>{$paras}{$quote}";
        $heading = 'About your ' . $comp . ' question';
        $html = $brand === 'cellc' ? campaign_cellc_template($heading, $inner) : mail_template($heading, $inner);
        if (send_mail((string) $msg['email'], "Re: your {$comp} question (ref #{$id})", $html)) {
            $pdo->prepare("UPDATE support_messages SET status = 'answered', reply = ?, replied_at = ?, replied_by = ? WHERE id = ?")
                ->execute([$text, now(), (string) ($admin['name'] ?? $admin['email'] ?? 'admin'), $id]);
            audit('support.reply', ['brand' => $brand, 'id' => $id]);
            flash('success', 'Reply sent to ' . $msg['email'] . '.');
        } else {
            flash('error', 'The reply could not be sent. Check Settings → email, then try again.');
        }
    } elseif ($action === 'close' || $action === 'reopen') {
        $pdo->prepare('UPDATE support_messages SET status = ? WHERE id = ?')->execute([$action === 'close' ? 'closed' : 'open', $id]);
        audit('support.' . $action, ['brand' => $brand, 'id' => $id]);
    }
    redirect($back);
}

$show = ($_GET['show'] ?? 'open') === 'all' ? 'all' : 'open';
$rows = [];
$open = 0;
foreach ($sources as $key => $src) {
    if (!$src['pdo']) {
        continue;
    }
    $open += (int) $src['pdo']->query("SELECT COUNT(*) FROM support_messages WHERE status = 'open'")->fetchColumn();
    $sql = 'SELECT * FROM support_messages' . ($show === 'open' ? " WHERE status = 'open'" : '') . ' ORDER BY created_at DESC LIMIT 200';
    foreach ($src['pdo']->query($sql)->fetchAll() as $r) {
        $rows[] = $r + ['brand' => $key];
    }
}
usort($rows, fn ($a, $b) => strcmp((string) $b['created_at'], (string) $a['created_at']));

admin_head('Support', 'support');
?>
<div class="toolbar" style="align-items:center">
  <a class="btn <?= $show === 'open' ? 'btn-primary' : '' ?>" href="?show=open">To answer (<?= number_format($open) ?>)</a>
  <a class="btn <?= $show === 'all' ? 'btn-primary' : '' ?>" href="?show=all">All messages</a>
  <span class="grow muted" style="font-size:.84rem">Competition questions sent with the <strong>Chat with us</strong> button on both websites.<?= $cPdo ? '' : ' Cell C database not reachable: only FLY FOR TWO messages are shown.' ?></span>
</div>

<?php if (!$rows): ?>
  <div class="card"><p class="muted" style="margin:0"><?= $show === 'open' ? 'Nothing waiting for an answer.' : 'No messages yet.' ?></p></div>
<?php endif; ?>

<?php foreach ($rows as $r): $done = $r['status'] !== 'open'; ?>
  <article class="card reply <?= $done ? 'is-done' : '' ?>" id="m-<?= e($r['brand'] . '-' . $r['id']) ?>">
    <header class="reply-head">
      <div>
        <strong><?= e($r['name']) ?></strong><span class="muted"> · <?= e($r['email']) ?></span>
        <div class="reply-subject"><?= e(SUPPORT_TOPICS[$r['topic']] ?? $r['topic']) ?></div>
      </div>
      <div class="reply-meta">
        <span class="pill pill-info"><?= e($sources[$r['brand']]['label']) ?></span>
        <?php if ($r['status'] === 'answered'): ?><span class="pill pill-ok">Answered</span><?php elseif ($r['status'] === 'closed'): ?><span class="pill">Closed</span><?php endif; ?>
        <span class="muted">#<?= (int) $r['id'] ?> · <?= e(fmt_date($r['created_at'], 'j M H:i')) ?></span>
      </div>
    </header>
    <div class="reply-body"><?= nl2br(e((string) $r['message'])) ?></div>
    <?php if ($r['reply']): ?>
      <div class="reply-body" style="border-left:3px solid var(--orange)"><strong>Our reply</strong> <span class="muted">· <?= e((string) $r['replied_by']) ?>, <?= e(fmt_date($r['replied_at'], 'j M H:i')) ?></span><br><?= nl2br(e((string) $r['reply'])) ?></div>
    <?php endif; ?>
    <?php if (!$done): ?>
      <form method="post" class="support-reply">
        <?= csrf_field() ?><input type="hidden" name="action" value="reply"><input type="hidden" name="brand" value="<?= e($r['brand']) ?>"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
        <textarea class="mail-input" name="reply" rows="4" required placeholder="Write your reply to <?= e(explode(' ', (string) $r['name'])[0]) ?>…"></textarea>
        <div class="reply-actions">
          <button class="btn btn-primary btn-sm" type="submit"><i class="fa-solid fa-paper-plane"></i> Send reply</button>
        </div>
      </form>
    <?php endif; ?>
    <div class="reply-actions">
      <form method="post"><?= csrf_field() ?><input type="hidden" name="brand" value="<?= e($r['brand']) ?>"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
        <?php if ($done): ?><input type="hidden" name="action" value="reopen"><button class="btn btn-sm" type="submit">Reopen</button>
        <?php else: ?><input type="hidden" name="action" value="close"><button class="btn btn-sm" type="submit"><i class="fa-solid fa-check"></i> Close without replying</button><?php endif; ?>
      </form>
    </div>
  </article>
<?php endforeach; ?>
<?php admin_foot();
