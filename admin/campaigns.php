<?php
/**
 * Bulk email: create a campaign from a template, add recipients (Excel/CSV, pasted or entrants), preview,
 * test, then send in batches. FLY FOR TWO (ACSA) and WIN A SAMSUNG (Cell C) campaigns are kept apart.
 */
require __DIR__ . '/_layout.php';

$admin = require_admin('admin');
campaign_schema();

$brands = campaign_brands();
$statusLabel = ['queued' => 'Not sent yet', 'sent' => 'Sent', 'failed' => 'Failed', 'skipped' => 'Skipped'];
$statusPill = ['queued' => 'pill-muted', 'sent' => 'pill-ok', 'failed' => 'pill-bad', 'skipped' => 'pill-warn'];

/** Recipient filter shared by the table and its CSV export. */
$recipientWhere = function (int $cid): array {
    $where = 'campaign_id = ?';
    $params = [$cid];
    $st = (string) ($_GET['st'] ?? '');
    if (in_array($st, ['queued', 'sent', 'failed', 'skipped'], true)) {
        $where .= ' AND status = ?';
        $params[] = $st;
    }
    $s = trim((string) ($_GET['s'] ?? ''));
    if ($s !== '') {
        $where .= ' AND (email LIKE ? OR full_name LIKE ?)';
        $params[] = '%' . $s . '%';
        $params[] = '%' . $s . '%';
    }
    return [$where, $params];
};

if (isset($_GET['export']) && ($c = q('SELECT * FROM email_campaigns WHERE id = ?', [(int) ($_GET['id'] ?? 0)])->fetch())) {
    audit('campaign.export', ['id' => (int) $c['id']]);
    [$where, $params] = $recipientWhere((int) $c['id']);
    $rows = q("SELECT email, full_name, ref_code, source, status, error, sent_at FROM email_queue WHERE {$where} ORDER BY id", $params)->fetchAll();
    csv_download('campaign-' . (int) $c['id'] . '-recipients-' . date('Ymd-His') . '.csv', ['Email', 'Full name', 'Share code', 'Added from', 'Status', 'Error', 'Sent at'],
        array_map(fn ($r) => array_merge($r, ['status' => $statusLabel[$r['status']] ?? $r['status']]), $rows));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    $fields = fn () => [
        mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 120),
        mb_substr(trim((string) ($_POST['subject'] ?? '')), 0, 190),
        mb_substr(trim((string) ($_POST['heading'] ?? '')), 0, 190),
        trim((string) ($_POST['body_html'] ?? '')),
    ];

    if ($action === 'create' || $action === 'save_template_new') {
        $brand = isset($brands[$_POST['brand'] ?? '']) ? $_POST['brand'] : 'acsa';
        [$name, $subject, $heading, $body] = $fields();
        if ($name === '' || $subject === '' || $heading === '' || $body === '') {
            flash('error', 'Fill in the name, subject, heading and message.');
            redirect('admin/campaigns.php?new=1&brand=' . $brand);
        }
        if ($action === 'save_template_new' || !empty($_POST['also_template'])) {
            q('INSERT INTO email_templates (brand, name, subject, heading, body_html, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$brand, $name, $subject, $heading, $body, $admin['id'], now()]);
            audit('template.create', ['brand' => $brand, 'name' => $name]);
        }
        if ($action === 'save_template_new') {
            flash('success', 'Template saved. It now appears in the template list for ' . $brands[$brand]['label'] . '.');
            redirect('admin/campaigns.php?templates=1&brand=' . $brand);
        }
        q('INSERT INTO email_campaigns (brand, name, subject, heading, body_html, status, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$brand, $name, $subject, $heading, $body, 'draft', $admin['id'], now()]);
        $id = (int) db()->lastInsertId();
        audit('campaign.create', ['id' => $id, 'brand' => $brand, 'name' => $name]);
        flash('success', 'Campaign saved as a draft. Next: add recipients, then send yourself a test.');
        redirect('admin/campaigns.php?id=' . $id);
    }
    if ($action === 'delete_template') {
        $t = q('SELECT brand FROM email_templates WHERE id = ?', [(int) ($_POST['template_id'] ?? 0)])->fetch();
        q('DELETE FROM email_templates WHERE id = ?', [(int) ($_POST['template_id'] ?? 0)]);
        audit('template.delete', ['id' => (int) ($_POST['template_id'] ?? 0)]);
        flash('success', 'Template deleted.');
        redirect('admin/campaigns.php?templates=1&brand=' . ($t['brand'] ?? 'acsa'));
    }

    $c = q('SELECT * FROM email_campaigns WHERE id = ?', [$id])->fetch();
    if (!$c) {
        redirect('admin/campaigns.php');
    }
    $editable = in_array($c['status'], ['draft', 'paused'], true);
    if ($action === 'update' && $editable) {
        [$name, $subject, $heading, $body] = $fields();
        if ($name !== '' && $subject !== '' && $heading !== '' && $body !== '') {
            q('UPDATE email_campaigns SET name = ?, subject = ?, heading = ?, body_html = ? WHERE id = ?', [$name, $subject, $heading, $body, $id]);
            audit('campaign.update', ['id' => $id]);
            flash('success', 'Changes saved. Send yourself a test to check them.');
        } else {
            flash('error', 'Fill in the name, subject, heading and message.');
        }
    } elseif ($action === 'recipients' && $c['status'] !== 'done') {
        $total = ['added' => 0, 'invalid' => 0, 'duplicate' => 0, 'unsubscribed' => 0];
        $add = function (array $n) use (&$total) {
            foreach ($n as $k => $v) {
                $total[$k] += $v;
            }
        };
        if (!empty($_FILES['sheet']['tmp_name']) && is_uploaded_file($_FILES['sheet']['tmp_name'])) {
            $fileName = (string) $_FILES['sheet']['name'];
            try {
                $rows = campaign_sheet_recipients(spreadsheet_rows($_FILES['sheet']['tmp_name'], $fileName));
                if (!$rows) {
                    flash('error', 'No email addresses found in ' . $fileName . '. The sheet needs a column headed "Email".');
                }
                $add(campaign_add_recipients($id, $rows, $fileName));
            } catch (Throwable $e) {
                flash('error', $e->getMessage());
            }
        }
        $pasted = [];
        foreach (lines((string) ($_POST['emails'] ?? '')) as $line) {
            $parts = array_map('trim', preg_split('/[,;\t]/', $line) ?: []);
            $pasted[] = ['email' => $parts[0] ?? '', 'full_name' => $parts[1] ?? '', 'ref_code' => $parts[2] ?? ''];
        }
        if ($pasted) {
            $add(campaign_add_recipients($id, $pasted, 'Pasted'));
        }
        $aud = (string) ($_POST['audience'] ?? '');
        if ($aud !== '') {
            if (!in_array($aud, campaign_brand($c['brand'])['audiences'], true)) {
                flash('error', 'That list belongs to the other competition and cannot be added to this campaign.');
            } else {
                $add(campaign_add_recipients($id, campaign_audience_rows($aud, $c['brand']), CAMPAIGN_AUDIENCES[$aud]));
            }
        }
        audit('campaign.recipients', ['id' => $id] + $total);
        if (!array_sum($total)) {
            redirect('admin/campaigns.php?id=' . $id);   // nothing read: any error message is already set
        }
        $skip = array_filter(['already on this campaign' => $total['duplicate'], 'not a valid email' => $total['invalid'], 'unsubscribed' => $total['unsubscribed']]);
        flash('success', number_format($total['added']) . ' recipients added'
            . ($skip ? ' · skipped: ' . implode(', ', array_map(fn ($k, $v) => number_format($v) . ' ' . $k, array_keys($skip), $skip)) : '') . '.');
    } elseif ($action === 'test') {
        $to = mb_strtolower(trim((string) ($_POST['test_to'] ?? '')));
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Type a valid email address for the test.');
        } else {
            [$subject, $html] = campaign_render($c, ['email' => $to, 'full_name' => $admin['name'], 'ref_code' => 'ABC2345']);
            $ok = send_campaign_mail($to, '[TEST] ' . $subject, $html);
            audit('campaign.test', ['id' => $id, 'to' => $to, 'ok' => $ok]);
            flash($ok ? 'success' : 'error', $ok ? 'Test sent to ' . $to . '. Check the inbox (and spam folder).' : 'The test could not be sent — check the mail settings.');
        }
    } elseif ($action === 'save_template') {
        q('INSERT INTO email_templates (brand, name, subject, heading, body_html, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$c['brand'], $c['name'], $c['subject'], $c['heading'], $c['body_html'], $admin['id'], now()]);
        audit('template.create', ['from_campaign' => $id]);
        flash('success', 'Saved as a template. Use it next time under "New campaign".');
    } elseif ($action === 'start' && $editable) {
        q("UPDATE email_campaigns SET status = 'sending', started_at = COALESCE(started_at, ?) WHERE id = ?", [now(), $id]);
        audit('campaign.start', ['id' => $id]);
        flash('success', 'Sending started. About 40 emails go out every 5 minutes (cron). "Send next batch now" sends 25 straight away.');
    } elseif ($action === 'pause' && $c['status'] === 'sending') {
        q("UPDATE email_campaigns SET status = 'paused' WHERE id = ?", [$id]);
        audit('campaign.pause', ['id' => $id]);
        flash('success', 'Campaign paused.');
    } elseif ($action === 'batch') {
        [$sent, $failed] = campaign_send_batch(25);
        flash($failed ? 'error' : 'success', "Batch done: {$sent} sent" . ($failed ? ", {$failed} failed" : '') . '.');
    } elseif ($action === 'retry') {
        $n = q("UPDATE email_queue SET status = 'queued', error = NULL WHERE campaign_id = ? AND status = 'failed'", [$id])->rowCount();
        if ($n && $c['status'] === 'done') {
            q("UPDATE email_campaigns SET status = 'paused', finished_at = NULL WHERE id = ?", [$id]);
        }
        flash('success', "{$n} failed emails queued again" . ($n && $c['status'] === 'done' ? ' — press "Start sending" to send them.' : '.'));
    } elseif ($action === 'remove') {
        q("DELETE FROM email_queue WHERE id = ? AND campaign_id = ? AND status = 'queued'", [(int) ($_POST['queue_id'] ?? 0), $id]);
        flash('success', 'Recipient removed.');
    } elseif ($action === 'clear' && $editable) {
        $n = q("DELETE FROM email_queue WHERE campaign_id = ? AND status = 'queued'", [$id])->rowCount();
        audit('campaign.clear', ['id' => $id, 'removed' => $n]);
        flash('success', "{$n} unsent recipients removed.");
    } elseif ($action === 'delete' && $c['status'] === 'draft') {
        q('DELETE FROM email_campaigns WHERE id = ?', [$id]);
        audit('campaign.delete', ['id' => $id]);
        flash('success', 'Draft deleted.');
        redirect('admin/campaigns.php?brand=' . $c['brand']);
    }
    redirect('admin/campaigns.php?id=' . $id . (isset($_POST['back']) ? '&' . (string) $_POST['back'] : ''));
}

$id = (int) ($_GET['id'] ?? 0);
$campaign = $id ? q('SELECT * FROM email_campaigns WHERE id = ?', [$id])->fetch() : null;
$brand = $campaign['brand'] ?? (isset($brands[$_GET['brand'] ?? '']) ? $_GET['brand'] : 'acsa');
$counts = fn (int $cid) => array_merge(['queued' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0],
    array_column(q('SELECT status, COUNT(*) n FROM email_queue WHERE campaign_id = ? GROUP BY status', [$cid])->fetchAll(), 'n', 'status'));
$bulk = is_array(config('bulk_mail')) && !empty(config('bulk_mail')['host']);
$pill = ['draft' => 'pill-muted', 'sending' => 'pill-warn', 'paused' => 'pill-info', 'done' => 'pill-ok'];
$hidden = fn () => csrf_field() . '<input type="hidden" name="id" value="' . (int) $campaign['id'] . '">';

/** The campaign / template editing fields. */
$editor = function (array $t) {
    ?>
    <div><label class="f">Internal name (only admins see this)</label><input class="in" name="name" value="<?= e($t['name']) ?>" required></div>
    <div><label class="f">Subject line</label><input class="in" name="subject" value="<?= e($t['subject']) ?>" required></div>
    <div><label class="f">Heading (top of the email)</label><input class="in" name="heading" value="<?= e($t['heading']) ?>" required></div>
    <div><label class="f">Message (HTML)</label><textarea class="in" name="body_html" rows="14" style="font-family:ui-monospace,monospace;font-size:.82rem" required><?= e($t['body']) ?></textarea>
      <small class="muted">Merge fields: <?= e(implode(' ', CAMPAIGN_FIELDS)) ?> ({close_date} is FLY FOR TWO only). The logos, footer and unsubscribe link are added automatically.</small></div>
    <?php
};

admin_head('Email campaigns', 'campaigns');
?>
<div class="toolbar" style="align-items:center">
  <?php foreach ($brands as $k => $b): ?>
    <a class="btn <?= $k === $brand ? 'btn-primary' : '' ?>" href="?brand=<?= e($k) ?>"><i class="fa-solid <?= $k === 'acsa' ? 'fa-plane' : 'fa-mobile-screen' ?>"></i> <?= e($b['label']) ?></a>
  <?php endforeach; ?>
  <span class="grow"></span>
  <a class="btn" href="?templates=1&amp;brand=<?= e($brand) ?>"><i class="fa-regular fa-file-lines"></i> Templates</a>
  <a class="btn btn-primary" href="?new=1&amp;brand=<?= e($brand) ?>"><i class="fa-solid fa-plus"></i> New campaign</a>
</div>

<?php if (isset($_GET['new'])):
    $templates = campaign_templates($brand);
    $tplKey = isset($templates[$_GET['tpl'] ?? '']) ? $_GET['tpl'] : array_key_first($templates);
    $t = $templates[$tplKey]; ?>
<div class="card">
  <h2>New <?= e($brands[$brand]['label']) ?> campaign</h2>
  <form method="get" class="toolbar" style="margin-bottom:12px">
    <input type="hidden" name="new" value="1"><input type="hidden" name="brand" value="<?= e($brand) ?>">
    <div class="grow"><label class="f">Start from a template</label>
      <select class="in" name="tpl" onchange="this.form.submit()">
        <?php foreach ($templates as $k => $tt): ?><option value="<?= e($k) ?>" <?= $k === $tplKey ? 'selected' : '' ?>><?= !empty($tt['saved']) ? 'Saved: ' : '' ?><?= e($tt['name']) ?></option><?php endforeach; ?>
      </select></div>
  </form>
  <form method="post" class="grid" style="gap:12px">
    <?= csrf_field() ?><input type="hidden" name="action" value="create"><input type="hidden" name="brand" value="<?= e($brand) ?>">
    <?php $editor($t); ?>
    <label class="chk"><input type="checkbox" name="also_template" value="1"> Also save this as a template for next time</label>
    <div><button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Save draft</button>
      <button class="btn" type="submit" name="action" value="save_template_new"><i class="fa-regular fa-file-lines"></i> Save as template only</button>
      <a class="btn" href="<?= e(url('admin/campaigns.php?brand=' . $brand)) ?>">Cancel</a></div>
  </form>
</div>

<?php elseif (isset($_GET['templates'])): ?>
<div class="card">
  <h2><?= e($brands[$brand]['label']) ?> templates</h2>
  <p class="muted" style="margin-top:0">Ready-made emails for this competition. Pick one to start a campaign, then edit it before saving. Save your own from any campaign with "Save as template".</p>
  <div class="t-wrap"><table class="t">
    <thead><tr><th>Template</th><th>Subject</th><th></th></tr></thead>
    <tbody>
    <?php foreach (campaign_templates($brand) as $k => $tt): ?>
      <tr><td><strong><?= e($tt['name']) ?></strong><br><small class="muted"><?= !empty($tt['saved']) ? 'Saved by GMF' : 'Built in' ?></small></td>
        <td><small><?= e($tt['subject']) ?></small></td>
        <td style="white-space:nowrap;text-align:right"><a class="btn btn-sm btn-primary" href="?new=1&amp;brand=<?= e($brand) ?>&amp;tpl=<?= e(rawurlencode($k)) ?>">Use</a>
          <?php if (!empty($tt['saved'])): ?><form method="post" style="display:inline" onsubmit="return confirm('Delete this template?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete_template"><input type="hidden" name="template_id" value="<?= (int) $tt['saved'] ?>"><button class="btn btn-sm btn-danger" type="submit"><i class="fa-solid fa-trash"></i></button></form><?php endif; ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>

<?php elseif ($campaign):
    $n = $counts((int) $campaign['id']);
    $editable = in_array($campaign['status'], ['draft', 'paused'], true);
    [$ps, $pv] = campaign_render($campaign, ['email' => 'preview@example.com', 'full_name' => 'Thandiwe Nkosi', 'ref_code' => 'ABC2345']); ?>
<div class="grid g-21" style="margin-bottom:16px">
  <div class="card">
    <h2><?= e($campaign['name']) ?> <span class="pill <?= $pill[$campaign['status']] ?>"><?= e($campaign['status']) ?></span> <span class="pill pill-info"><?= e($brands[$campaign['brand']]['label'] ?? $campaign['brand']) ?></span></h2>
    <?php if (isset($_GET['edit']) && $editable): ?>
      <form method="post" class="grid" style="gap:12px">
        <?= $hidden() ?><input type="hidden" name="action" value="update">
        <?php $editor(['name' => $campaign['name'], 'subject' => $campaign['subject'], 'heading' => $campaign['heading'], 'body' => $campaign['body_html']]); ?>
        <div><button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Save changes</button> <a class="btn" href="?id=<?= (int) $campaign['id'] ?>">Cancel</a></div>
      </form>
    <?php else: ?>
      <p style="margin-top:0"><strong>Subject:</strong> <?= e($ps) ?>
        <?php if ($editable): ?> · <a href="?id=<?= (int) $campaign['id'] ?>&amp;edit=1"><i class="fa-solid fa-pen"></i> Edit</a><?php endif; ?></p>
      <iframe title="Preview" style="width:100%;height:560px;border:1px solid var(--line);border-radius:10px;background:#fff" srcdoc="<?= e($pv) ?>"></iframe>
    <?php endif; ?>
  </div>
  <div class="grid" style="align-content:start;gap:16px">
    <div class="card">
      <h2>Progress</h2>
      <p style="margin:0">Sent <strong><?= number_format((int) $n['sent']) ?></strong> · Not sent yet <strong><?= number_format((int) $n['queued']) ?></strong> · Failed <strong><?= number_format((int) $n['failed']) ?></strong> · Skipped <strong><?= number_format((int) $n['skipped']) ?></strong></p>
      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:14px">
        <?php if ($editable && $n['queued']): ?>
          <form method="post" onsubmit="return confirm('Start sending to <?= (int) $n['queued'] ?> recipients?')"><?= $hidden() ?><input type="hidden" name="action" value="start"><button class="btn btn-sm btn-ok" type="submit"><i class="fa-solid fa-paper-plane"></i> Start sending</button></form>
        <?php elseif ($campaign['status'] === 'sending'): ?>
          <form method="post"><?= $hidden() ?><input type="hidden" name="action" value="batch"><button class="btn btn-sm btn-primary" type="submit"><i class="fa-solid fa-forward"></i> Send next batch now</button></form>
          <form method="post"><?= $hidden() ?><input type="hidden" name="action" value="pause"><button class="btn btn-sm" type="submit"><i class="fa-solid fa-pause"></i> Pause</button></form>
        <?php endif; ?>
        <?php if ($n['failed']): ?><form method="post"><?= $hidden() ?><input type="hidden" name="action" value="retry"><button class="btn btn-sm" type="submit"><i class="fa-solid fa-rotate"></i> Retry failed</button></form><?php endif; ?>
        <form method="post"><?= $hidden() ?><input type="hidden" name="action" value="save_template"><button class="btn btn-sm" type="submit"><i class="fa-regular fa-file-lines"></i> Save as template</button></form>
        <?php if ($campaign['status'] === 'draft'): ?><form method="post" onsubmit="return confirm('Delete this draft?')"><?= $hidden() ?><input type="hidden" name="action" value="delete"><button class="btn btn-sm btn-danger" type="submit" title="Delete draft"><i class="fa-solid fa-trash"></i></button></form><?php endif; ?>
      </div>
    </div>
    <div class="card">
      <h2>Send a test</h2>
      <form method="post" class="toolbar" style="margin:0">
        <?= $hidden() ?><input type="hidden" name="action" value="test">
        <div class="grow"><label class="f">Send the test to</label><input class="in" type="email" name="test_to" value="<?= e($admin['email']) ?>" required></div>
        <button class="btn btn-primary" type="submit"><i class="fa-solid fa-flask"></i> Send test</button>
      </form>
      <small class="muted">Tests are marked [TEST] and never count as sent.</small>
    </div>
    <?php if ($campaign['status'] !== 'done'): ?>
    <div class="card">
      <h2>Add recipients</h2>
      <form method="post" enctype="multipart/form-data" class="grid" style="gap:10px">
        <?= $hidden() ?><input type="hidden" name="action" value="recipients">
        <div><label class="f">Excel (.xlsx) or CSV file</label><input class="in" type="file" name="sheet" accept=".xlsx,.csv,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet">
          <small class="muted">Needs a column headed "Email". "Full name" (or "First name" + "Surname") and "Share code" / "Referral code" are used if present. First sheet only.</small></div>
        <div><label class="f">Or paste — one per line: email, full name, share code</label><textarea class="in" name="emails" rows="3" placeholder="thandi@example.com, Thandiwe Nkosi"></textarea></div>
        <div><label class="f">And/or a list in this system</label><select class="in" name="audience"><option value="">— none —</option>
          <?php foreach (campaign_brand($campaign['brand'])['audiences'] as $a): ?><option value="<?= e($a) ?>"><?= e(CAMPAIGN_AUDIENCES[$a]) ?></option><?php endforeach; ?></select>
          <?php if ($campaign['brand'] === 'cellc'): ?><small class="muted">Cell C entrants are kept in the Cell C system: export them there (Entrants → Export) and upload the file above. FLY FOR TWO entrants can't be added to Cell C emails.</small><?php endif; ?></div>
        <div><button class="btn btn-primary" type="submit"><i class="fa-solid fa-user-plus"></i> Add</button></div>
      </form>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php
    [$where, $params] = $recipientWhere((int) $campaign['id']);
    $per = 50;
    $total = (int) q("SELECT COUNT(*) FROM email_queue WHERE {$where}", $params)->fetchColumn();
    $pages = max(1, (int) ceil($total / $per));
    $page = min($pages, max(1, (int) ($_GET['page'] ?? 1)));
    $list = q("SELECT * FROM email_queue WHERE {$where} ORDER BY (status = 'queued') DESC, sent_at DESC, id LIMIT {$per} OFFSET " . (($page - 1) * $per), $params)->fetchAll();
    $st = (string) ($_GET['st'] ?? '');
    $back = http_build_query(array_intersect_key($_GET, ['st' => 1, 's' => 1, 'page' => 1]));
?>
<div class="card" id="recipients">
  <h2>Recipients <a class="btn btn-sm" href="?<?= e(http_build_query(array_merge($_GET, ['export' => 1]))) ?>"><i class="fa-solid fa-file-csv"></i> Export <?= number_format($total) ?></a></h2>
  <div class="toolbar">
    <?php foreach (['' => 'All', 'sent' => 'Sent', 'queued' => 'Not sent yet', 'failed' => 'Failed', 'skipped' => 'Skipped'] as $k => $label):
        $cnt = $k === '' ? array_sum($n) : (int) $n[$k]; ?>
      <a class="btn btn-sm <?= $st === $k ? 'btn-primary' : '' ?>" href="?<?= e(http_build_query(['id' => $campaign['id'], 'st' => $k, 's' => $_GET['s'] ?? ''])) ?>#recipients"><?= e($label) ?> (<?= number_format($cnt) ?>)</a>
    <?php endforeach; ?>
    <form method="get" style="display:flex;gap:6px;margin-left:auto"><input type="hidden" name="id" value="<?= (int) $campaign['id'] ?>"><input type="hidden" name="st" value="<?= e($st) ?>">
      <input class="in" name="s" value="<?= e($_GET['s'] ?? '') ?>" placeholder="Search email or name"><button class="btn btn-sm" type="submit"><i class="fa-solid fa-magnifying-glass"></i></button></form>
    <?php if ($editable && $n['queued']): ?>
      <form method="post" onsubmit="return confirm('Remove all <?= (int) $n['queued'] ?> recipients who have not been sent the email yet?')"><?= $hidden() ?><input type="hidden" name="action" value="clear"><button class="btn btn-sm btn-danger" type="submit">Remove unsent</button></form>
    <?php endif; ?>
  </div>
  <div class="t-wrap"><table class="t">
    <thead><tr><th>Email</th><th>Name</th><th>Added from</th><th>Status</th><th>Sent / note</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($list as $r): ?>
      <tr><td><?= e($r['email']) ?></td><td><?= e((string) $r['full_name']) ?></td><td><small class="muted"><?= e((string) $r['source']) ?></small></td>
        <td><span class="pill <?= $statusPill[$r['status']] ?>"><?= e($statusLabel[$r['status']]) ?></span></td>
        <td><small class="muted"><?= $r['sent_at'] && $r['status'] === 'sent' ? e(fmt_date($r['sent_at'], 'j M Y H:i')) : e((string) $r['error']) ?></small></td>
        <td style="text-align:right"><?php if ($r['status'] === 'queued'): ?><form method="post"><?= $hidden() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="queue_id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="back" value="<?= e($back) ?>"><button class="btn btn-sm" type="submit" title="Remove"><i class="fa-solid fa-xmark"></i></button></form><?php endif; ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$list): ?><tr><td colspan="6" class="muted">No recipients here yet. Add them with an Excel or CSV file, by pasting, or from a list in this system.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
  <?= admin_pager($page, $pages) ?>
</div>

<?php else: $all = q('SELECT * FROM email_campaigns WHERE brand = ? ORDER BY id DESC', [$brand])->fetchAll(); ?>
<div class="card" style="margin-bottom:16px">
  <h2>How to send an email</h2>
  <ol style="margin:0;padding-left:20px;line-height:1.8">
    <li>Choose the competition above, then <strong>New campaign</strong> and pick a template. Edit the wording and <strong>Save draft</strong>.</li>
    <li><strong>Add recipients</strong>: upload an Excel (.xlsx) or CSV file, paste addresses, or pick a list in this system.</li>
    <li><strong>Send a test</strong> to yourself and check it on your phone.</li>
    <li>Press <strong>Start sending</strong>. About 40 emails go out every 5 minutes; the Recipients list shows who has been sent it and who hasn't.</li>
  </ol>
  <p class="muted" style="margin:12px 0 0">Sending through <strong><?= $bulk ? e(config('bulk_mail')['host']) . ' (bulk service)' : e((string) config('mail.host')) . ' (' . e((string) config('mail.username')) . ')' ?></strong>.
  <?php if (!$bulk): ?>Ordinary mailboxes allow only a few hundred emails a day — for more, add a <code>bulk_mail</code> service (SMTP2GO or Brevo) to the server config.<?php endif; ?>
  Every email has an unsubscribe link; unsubscribed addresses are skipped automatically.</p>
</div>
<div class="card">
  <h2><?= e($brands[$brand]['label']) ?> campaigns</h2>
  <div class="t-wrap"><table class="t">
    <thead><tr><th>Campaign</th><th>Status</th><th class="num">Sent</th><th class="num">Not sent yet</th><th class="num">Failed</th><th class="num">Skipped</th><th>Created</th></tr></thead>
    <tbody>
    <?php foreach ($all as $c): $n = $counts((int) $c['id']); ?>
      <tr><td><a href="?id=<?= (int) $c['id'] ?>"><strong><?= e($c['name']) ?></strong></a><br><small class="muted"><?= e($c['subject']) ?></small></td>
        <td><span class="pill <?= $pill[$c['status']] ?>"><?= e($c['status']) ?></span></td>
        <td class="num"><?= number_format((int) $n['sent']) ?></td><td class="num"><?= number_format((int) $n['queued']) ?></td><td class="num"><?= number_format((int) $n['failed']) ?></td><td class="num"><?= number_format((int) $n['skipped']) ?></td>
        <td><small class="muted"><?= e(fmt_date($c['created_at'], 'j M H:i')) ?></small></td></tr>
    <?php endforeach; ?>
    <?php if (!$all): ?><tr><td colspan="7" class="muted">No <?= e($brands[$brand]['label']) ?> campaigns yet. Press "New campaign" to start from a template.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>
<?php endif; ?>
<?php admin_foot(); ?>
