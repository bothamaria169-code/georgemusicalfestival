<?php
/** Bulk email: create a campaign, add recipients (CSV or entrants), preview, test, then send in batches. */
require __DIR__ . '/_layout.php';

$admin = require_admin('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    if ($action === 'create') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $subject = trim((string) ($_POST['subject'] ?? ''));
        $heading = trim((string) ($_POST['heading'] ?? ''));
        $body = trim((string) ($_POST['body_html'] ?? ''));
        if ($name === '' || $subject === '' || $heading === '' || $body === '') {
            flash('error', 'Fill in the name, subject, heading and message.');
            redirect('admin/campaigns.php?new=1');
        }
        q('INSERT INTO email_campaigns (name, subject, heading, body_html, status, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [mb_substr($name, 0, 120), mb_substr($subject, 0, 190), mb_substr($heading, 0, 190), $body, 'draft', $admin['id'], now()]);
        $id = (int) db()->lastInsertId();
        audit('campaign.create', ['id' => $id, 'name' => $name]);
        flash('success', 'Campaign saved as a draft. Add recipients, preview it and send yourself a test before sending.');
        redirect('admin/campaigns.php?id=' . $id);
    }
    $c = q('SELECT * FROM email_campaigns WHERE id = ?', [$id])->fetch();
    if (!$c) {
        redirect('admin/campaigns.php');
    }
    if ($action === 'recipients') {
        $rows = [];
        if (!empty($_FILES['csv']['tmp_name']) && is_uploaded_file($_FILES['csv']['tmp_name'])) {
            $rows = array_merge($rows, campaign_csv_rows($_FILES['csv']['tmp_name']));
        }
        $aud = $_POST['audience'] ?? '';
        if ($aud === 'verified' || $aud === 'marketing') {
            $rows = array_merge($rows, q("SELECT email, full_name, ref_code FROM entrants WHERE status = 'verified' AND email IS NOT NULL"
                . ($aud === 'marketing' ? ' AND consent_marketing = 1' : '') . " AND (email_status IS NULL OR email_status <> 'bounced')")->fetchAll());
        }
        foreach (lines((string) ($_POST['emails'] ?? '')) as $line) {
            $rows[] = ['email' => trim(explode(',', $line)[0]), 'full_name' => trim(explode(',', $line)[1] ?? ''), 'ref_code' => trim(explode(',', $line)[2] ?? '')];
        }
        [$added, $skipped] = campaign_add_recipients($id, $rows);
        audit('campaign.recipients', ['id' => $id, 'added' => $added, 'skipped' => $skipped]);
        flash('success', "{$added} recipients added" . ($skipped ? ", {$skipped} skipped (invalid, duplicate or unsubscribed)" : '') . '.');
    } elseif ($action === 'test') {
        [$subject, $html] = campaign_render($c, ['email' => $admin['email'], 'full_name' => $admin['name'], 'ref_code' => 'ABC2345']);
        $ok = send_campaign_mail($admin['email'], '[TEST] ' . $subject, $html);
        flash($ok ? 'success' : 'error', $ok ? 'Test sent to ' . $admin['email'] . '.' : 'The test could not be sent — check the mail settings.');
    } elseif ($action === 'start' && in_array($c['status'], ['draft', 'paused'], true)) {
        q("UPDATE email_campaigns SET status = 'sending', started_at = COALESCE(started_at, ?) WHERE id = ?", [now(), $id]);
        audit('campaign.start', ['id' => $id]);
        flash('success', 'Sending started. Emails go out in batches every few minutes (cron), or use "Send next batch now".');
    } elseif ($action === 'pause' && $c['status'] === 'sending') {
        q("UPDATE email_campaigns SET status = 'paused' WHERE id = ?", [$id]);
        audit('campaign.pause', ['id' => $id]);
        flash('success', 'Campaign paused.');
    } elseif ($action === 'batch') {
        [$sent, $failed] = campaign_send_batch(25);
        flash($failed ? 'error' : 'success', "Batch done: {$sent} sent" . ($failed ? ", {$failed} failed" : '') . '.');
    } elseif ($action === 'retry') {
        $n = q("UPDATE email_queue SET status = 'queued', error = NULL WHERE campaign_id = ? AND status = 'failed'", [$id])->rowCount();
        flash('success', "{$n} failed emails queued again.");
    } elseif ($action === 'delete' && $c['status'] === 'draft') {
        q('DELETE FROM email_campaigns WHERE id = ?', [$id]);
        audit('campaign.delete', ['id' => $id]);
        flash('success', 'Draft deleted.');
        redirect('admin/campaigns.php');
    }
    redirect('admin/campaigns.php?id=' . $id);
}

$id = (int) ($_GET['id'] ?? 0);
$campaign = $id ? q('SELECT * FROM email_campaigns WHERE id = ?', [$id])->fetch() : null;
$counts = fn (int $cid) => array_merge(['queued' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0],
    array_column(q('SELECT status, COUNT(*) n FROM email_queue WHERE campaign_id = ? GROUP BY status', [$cid])->fetchAll(), 'n', 'status'));
$bulk = is_array(config('bulk_mail')) && !empty(config('bulk_mail')['host']);
$pill = ['draft' => 'pill-muted', 'sending' => 'pill-warn', 'paused' => 'pill-info', 'done' => 'pill-ok'];

admin_head('Email campaigns', 'campaigns');
?>
<div class="card" style="margin-bottom:16px">
  <p class="muted" style="margin:0">Sending through <strong><?= $bulk ? e(config('bulk_mail')['host']) . ' (bulk service)' : e((string) config('mail.host')) . ' (' . e((string) config('mail.username')) . ')' ?></strong>.
  <?php if (!$bulk): ?>Ordinary mailboxes allow only a few hundred emails a day — for more, add a <code>bulk_mail</code> service (SMTP2GO or Brevo) to the server config.<?php endif; ?>
  Every email includes an unsubscribe link; unsubscribed addresses are skipped automatically.</p>
</div>

<?php if (isset($_GET['new'])): $t = campaign_restore_template(); ?>
<div class="card">
  <h2>New campaign</h2>
  <form method="post" class="grid" style="gap:12px">
    <?= csrf_field() ?><input type="hidden" name="action" value="create">
    <div><label class="f">Internal name</label><input class="in" name="name" value="<?= e($t['name']) ?>" required></div>
    <div><label class="f">Subject line</label><input class="in" name="subject" value="<?= e($t['subject']) ?>" required></div>
    <div><label class="f">Heading (top of the email)</label><input class="in" name="heading" value="<?= e($t['heading']) ?>" required></div>
    <div><label class="f">Message (HTML)</label><textarea class="in" name="body_html" rows="14" style="font-family:ui-monospace,monospace;font-size:.82rem" required><?= e($t['body']) ?></textarea>
      <small class="muted">Merge fields: <?= e(implode(' ', CAMPAIGN_FIELDS)) ?>. The branded header, footer and unsubscribe link are added automatically.</small></div>
    <div><button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Save draft</button> <a class="btn" href="<?= e(url('admin/campaigns.php')) ?>">Cancel</a></div>
  </form>
</div>

<?php elseif ($campaign): $n = $counts((int) $campaign['id']); [$ps, $pv] = campaign_render($campaign, ['email' => 'preview@example.com', 'full_name' => 'Thandiwe Nkosi', 'ref_code' => 'ABC2345']); ?>
<div class="grid g-21" style="margin-bottom:16px">
  <div class="card">
    <h2><?= e($campaign['name']) ?> <span class="pill <?= $pill[$campaign['status']] ?>"><?= e($campaign['status']) ?></span></h2>
    <p style="margin-top:0"><strong>Subject:</strong> <?= e($ps) ?></p>
    <iframe title="Preview" style="width:100%;height:560px;border:1px solid var(--line);border-radius:10px;background:#fff" srcdoc="<?= e($pv) ?>"></iframe>
  </div>
  <div class="grid" style="align-content:start;gap:16px">
    <div class="card">
      <h2>Progress</h2>
      <p style="margin:0">Queued <strong><?= (int) $n['queued'] ?></strong> · Sent <strong><?= (int) $n['sent'] ?></strong> · Failed <strong><?= (int) $n['failed'] ?></strong> · Skipped <strong><?= (int) $n['skipped'] ?></strong></p>
      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:14px">
        <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $campaign['id'] ?>"><input type="hidden" name="action" value="test"><button class="btn btn-sm" type="submit"><i class="fa-solid fa-flask"></i> Send me a test</button></form>
        <?php if (in_array($campaign['status'], ['draft', 'paused'], true) && $n['queued']): ?>
          <form method="post" onsubmit="return confirm('Start sending to <?= (int) $n['queued'] ?> recipients?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $campaign['id'] ?>"><input type="hidden" name="action" value="start"><button class="btn btn-sm btn-ok" type="submit"><i class="fa-solid fa-paper-plane"></i> Start sending</button></form>
        <?php elseif ($campaign['status'] === 'sending'): ?>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $campaign['id'] ?>"><input type="hidden" name="action" value="batch"><button class="btn btn-sm btn-primary" type="submit"><i class="fa-solid fa-forward"></i> Send next batch now</button></form>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $campaign['id'] ?>"><input type="hidden" name="action" value="pause"><button class="btn btn-sm" type="submit"><i class="fa-solid fa-pause"></i> Pause</button></form>
        <?php endif; ?>
        <?php if ($n['failed']): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $campaign['id'] ?>"><input type="hidden" name="action" value="retry"><button class="btn btn-sm" type="submit"><i class="fa-solid fa-rotate"></i> Retry failed</button></form><?php endif; ?>
        <?php if ($campaign['status'] === 'draft'): ?><form method="post" onsubmit="return confirm('Delete this draft?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $campaign['id'] ?>"><input type="hidden" name="action" value="delete"><button class="btn btn-sm btn-danger" type="submit"><i class="fa-solid fa-trash"></i></button></form><?php endif; ?>
      </div>
    </div>
    <?php if ($campaign['status'] !== 'done'): ?>
    <div class="card">
      <h2>Add recipients</h2>
      <form method="post" enctype="multipart/form-data" class="grid" style="gap:10px">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $campaign['id'] ?>"><input type="hidden" name="action" value="recipients">
        <div><label class="f">CSV file (needs an "Email" column; "Full name" and "Share code" are used if present)</label><input class="in" type="file" name="csv" accept=".csv,text/csv"></div>
        <div><label class="f">Or paste — one per line: email, full name, share code</label><textarea class="in" name="emails" rows="4" placeholder="thandi@example.com, Thandiwe Nkosi, QEP6GP9"></textarea></div>
        <div><label class="f">And/or entrants in this system</label><select class="in" name="audience"><option value="">— none —</option><option value="verified">All verified entrants</option><option value="marketing">Verified entrants who opted in to marketing</option></select></div>
        <div><button class="btn btn-primary" type="submit"><i class="fa-solid fa-user-plus"></i> Add</button></div>
      </form>
    </div>
    <?php endif; ?>
    <?php $fails = q("SELECT email, error FROM email_queue WHERE campaign_id = ? AND status = 'failed' LIMIT 20", [$campaign['id']])->fetchAll(); if ($fails): ?>
    <div class="card"><h2>Failed</h2><?php foreach ($fails as $f): ?><small><?= e($f['email']) ?> — <span class="muted"><?= e($f['error']) ?></span></small><br><?php endforeach; ?></div>
    <?php endif; ?>
  </div>
</div>

<?php else: $all = q('SELECT * FROM email_campaigns ORDER BY id DESC')->fetchAll(); ?>
<div class="toolbar"><a class="btn btn-primary" href="?new=1"><i class="fa-solid fa-plus"></i> New campaign</a></div>
<div class="card">
  <div class="t-wrap"><table class="t">
    <thead><tr><th>Campaign</th><th>Status</th><th class="num">Queued</th><th class="num">Sent</th><th class="num">Failed</th><th class="num">Skipped</th><th>Created</th></tr></thead>
    <tbody>
    <?php foreach ($all as $c): $n = $counts((int) $c['id']); ?>
      <tr><td><a href="?id=<?= (int) $c['id'] ?>"><strong><?= e($c['name']) ?></strong></a><br><small class="muted"><?= e($c['subject']) ?></small></td>
        <td><span class="pill <?= $pill[$c['status']] ?>"><?= e($c['status']) ?></span></td>
        <td class="num"><?= (int) $n['queued'] ?></td><td class="num"><?= (int) $n['sent'] ?></td><td class="num"><?= (int) $n['failed'] ?></td><td class="num"><?= (int) $n['skipped'] ?></td>
        <td><small class="muted"><?= e(fmt_date($c['created_at'], 'j M H:i')) ?></small></td></tr>
    <?php endforeach; ?>
    <?php if (!$all): ?><tr><td colspan="7" class="muted">No campaigns yet. Start with "New campaign" — it opens with the "Restore your entry" email ready to edit.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>
<?php endif; ?>
<?php admin_foot(); ?>
