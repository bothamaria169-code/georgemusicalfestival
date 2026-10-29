<?php
/**
 * Entry restoration after the October 2026 server loss: review "Restore my entry" claims,
 * see opens waiting on unclaimed share links, and restore scores from evidence.
 */
require __DIR__ . '/_layout.php';

$admin = require_admin();
$isAdmin = $admin['role'] === 'admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAdmin && csrf_ok()) {
    $action = $_POST['action'] ?? '';
    $note = mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 255);
    if ($action === 'approve') {
        $res = restore_approve((int) ($_POST['id'] ?? 0), (int) $admin['id'], $note);
        flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'Claim approved — the old link now counts for this entrant (' . $res['moved'] . ' saved opens added).' : $res['error']);
    } elseif ($action === 'reject') {
        restore_reject((int) ($_POST['id'] ?? 0), (int) $admin['id'], $note ?: 'Rejected by admin');
        flash('success', 'Claim rejected.');
    } elseif ($action === 'add_opens') {
        $who = trim((string) ($_POST['entrant'] ?? ''));
        $code = normalize_ref_code($who);
        $entrant = filter_var($who, FILTER_VALIDATE_EMAIL)
            ? q("SELECT * FROM entrants WHERE email = ? AND status = 'verified'", [mb_strtolower($who)])->fetch()
            : entrant_by_ref($code);
        $opens = (int) ($_POST['opens'] ?? 0);
        $asAt = date('Y-m-d H:i:s', strtotime((string) ($_POST['as_at'] ?? '')) ?: time());
        $reason = trim((string) ($_POST['reason'] ?? ''));
        $inWindow = $asAt >= (string) setting('comp_open_at') && $asAt <= (string) setting('comp_close_at');
        if (!$entrant) {
            flash('error', 'No verified entrant found for "' . $who . '". Use their email address or share code — their entry must be restored first.');
        } elseif ($opens < 1 || $opens > 100000) {
            flash('error', 'Enter the number of link opens to restore (1 or more).');
        } elseif ($reason === '') {
            flash('error', 'Give a reason — it is shown in the audit log and the certification record.');
        } elseif (!$inWindow) {
            flash('error', 'The "as at" date must fall inside the competition window, or the opens will not count.');
        } else {
            restore_add_opens((int) $entrant['id'], $opens, $asAt, $reason, trim((string) ($_POST['evidence'] ?? '')), (int) $admin['id']);
            flash('success', number_format($opens) . ' restored link opens added for ' . $entrant['full_name'] . '.');
        }
    } elseif ($action === 'remove_opens') {
        restore_remove_adjustment((int) ($_POST['id'] ?? 0));
        flash('success', 'Restored opens removed.');
    }
    redirect('admin/restores.php?' . http_build_query($_GET));
}

$status = $_GET['status'] ?? 'review';
$claims = q("SELECT r.*, e.full_name, e.email, e.phone, e.town, e.status AS entrant_status, e.ref_code AS current_code, a.name AS reviewer,
                    (SELECT COUNT(*) FROM orphan_clicks o WHERE o.ref_code = r.ref_code) AS waiting,
                    (SELECT COUNT(*) FROM restore_requests x WHERE x.ref_code = r.ref_code AND x.id <> r.id AND x.status IN ('review','approved')) AS rivals
             FROM restore_requests r JOIN entrants e ON e.id = r.entrant_id LEFT JOIN admins a ON a.id = r.reviewed_by
             " . (in_array($status, ['email', 'review', 'approved', 'rejected'], true) ? 'WHERE r.status = ?' : '') . "
             ORDER BY r.device_match DESC, r.claimed_opens DESC, r.id", in_array($status, ['email', 'review', 'approved', 'rejected'], true) ? [$status] : [])->fetchAll();
$counts = [];
foreach (q('SELECT status, COUNT(*) c FROM restore_requests GROUP BY status')->fetchAll() as $c) {
    $counts[$c['status']] = (int) $c['c'];
}
$unclaimed = q('SELECT ref_code, COUNT(*) AS opens, MIN(clicked_at) AS first_open, MAX(clicked_at) AS last_open,
                       (SELECT COUNT(*) FROM restore_requests r WHERE r.ref_code = o.ref_code AND r.status IN (\'email\',\'review\')) AS claims
                FROM orphan_clicks o GROUP BY ref_code ORDER BY opens DESC LIMIT 100')->fetchAll();
$waitingTotal = (int) q('SELECT COUNT(*) FROM orphan_clicks')->fetchColumn();
$adjustments = q('SELECT s.*, e.full_name, e.ref_code, a.name AS admin_name FROM score_adjustments s JOIN entrants e ON e.id = s.entrant_id LEFT JOIN admins a ON a.id = s.admin_id ORDER BY s.id DESC')->fetchAll();

admin_head('Entry restoration', 'restores');
?>
<div class="grid g-3" style="margin-bottom:16px">
  <div class="card kpi is-accent"><small>Claims to review</small><strong><?= number_format($counts['review'] ?? 0) ?></strong><span><?= number_format($counts['email'] ?? 0) ?> more waiting for their email code</span></div>
  <div class="card kpi"><small>Entries restored</small><strong><?= number_format($counts['approved'] ?? 0) ?></strong><span><?= number_format($counts['rejected'] ?? 0) ?> claims rejected</span></div>
  <div class="card kpi"><small>Opens saved on unclaimed links</small><strong><?= number_format($waitingTotal) ?></strong><span>across <?= number_format(count($unclaimed)) ?> old share links</span></div>
</div>

<form class="toolbar" method="get">
  <div><label class="f">Claims</label><select class="in" name="status" onchange="this.form.submit()">
    <?php foreach (['review' => 'To review', 'email' => 'Waiting for email code', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All'] as $k => $v): ?><option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?>
  </select></div>
</form>

<div class="card" style="margin-bottom:16px">
  <h2>Restore claims</h2>
  <p class="muted" style="margin-top:0">Each claimant confirmed their email with a code. <span class="pill pill-ok">Same phone</span> means the claim came from the phone that registered that link before the outage — those are approved automatically when no one else claims the code. Check the rest against their "You're entered" email, screenshots or the archive snapshots before approving.</p>
  <div class="t-wrap"><table class="t">
    <thead><tr><th>Claimant</th><th>Old link</th><th>Proof</th><th class="num">Says score was</th><th class="num">Opens saved since</th><th>Their note</th><th>Status</th><?php if ($isAdmin): ?><th></th><?php endif; ?></tr></thead>
    <tbody>
    <?php foreach ($claims as $c): ?>
      <tr>
        <td><strong><?= e($c['full_name']) ?></strong><br><small class="muted"><?= e($c['email']) ?> · <?= e(display_phone($c['phone'])) ?><?= $c['town'] ? ' · ' . e($c['town']) : '' ?></small></td>
        <td><code>/r/<?= e($c['ref_code']) ?></code><?php if ((int) $c['rivals'] > 0): ?><br><span class="pill pill-bad" title="Someone else also claims this link">Contested ×<?= (int) $c['rivals'] + 1 ?></span><?php endif; ?></td>
        <td><?= $c['device_match'] ? '<span class="pill pill-ok">Same phone</span>' : '<span class="pill pill-muted">Email only</span>' ?><?= $c['new_entrant'] ? '' : '<br><small class="muted">re-entered as ' . e($c['current_code']) . '</small>' ?></td>
        <td class="num"><?= $c['claimed_opens'] !== null ? number_format((int) $c['claimed_opens']) : '—' ?></td>
        <td class="num"><?= number_format((int) $c['waiting']) ?></td>
        <td style="max-width:260px"><small><?= nl2br(e((string) $c['evidence'])) ?: '<span class="muted">—</span>' ?></small></td>
        <td>
          <?php $pill = ['email' => 'pill-muted', 'review' => 'pill-warn', 'approved' => 'pill-ok', 'rejected' => 'pill-bad'][$c['status']]; ?>
          <span class="pill <?= $pill ?>"><?= e(['email' => 'Email code', 'review' => 'To review', 'approved' => 'Approved', 'rejected' => 'Rejected'][$c['status']]) ?></span>
          <br><small class="muted"><?= e(fmt_date($c['reviewed_at'] ?: $c['created_at'], 'j M H:i')) ?><?= $c['reviewer'] ? ' · ' . e($c['reviewer']) : ($c['status'] === 'approved' && !$c['reviewed_by'] ? ' · automatic' : '') ?></small>
          <?php if ($c['admin_note']): ?><br><small class="muted"><?= e($c['admin_note']) ?></small><?php endif; ?>
        </td>
        <?php if ($isAdmin): ?>
        <td>
          <?php if ($c['status'] === 'review'): ?>
            <form method="post" style="margin-bottom:6px" onsubmit="var n=prompt('Approve /r/<?= e($c['ref_code']) ?> for <?= e(addslashes($c['full_name'])) ?>? What did you check (logged):', 'Matched their You\'re entered email');if(n===null)return false;this.note.value=n;"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><input type="hidden" name="action" value="approve"><input type="hidden" name="note"><button class="btn btn-sm btn-ok" type="submit"><i class="fa-solid fa-check"></i> Approve</button></form>
            <form method="post" onsubmit="var n=prompt('Reason for rejecting (logged):');if(!n)return false;this.note.value=n;"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><input type="hidden" name="action" value="reject"><input type="hidden" name="note"><button class="btn btn-sm btn-danger" type="submit"><i class="fa-solid fa-xmark"></i> Reject</button></form>
          <?php endif; ?>
        </td>
        <?php endif; ?>
      </tr>
    <?php endforeach; ?>
    <?php if (!$claims): ?><tr><td colspan="8" class="muted">No claims here.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>

<div class="grid g-2" style="margin-bottom:16px">
  <div class="card">
    <h2>Unclaimed old links</h2>
    <p class="muted" style="margin-top:0">Share links from before the outage that people are still opening. These opens are added to the owner's score when their claim is approved.</p>
    <div class="t-wrap"><table class="t">
      <thead><tr><th>Link</th><th class="num">Opens saved</th><th>First / last open</th><th>Claims</th></tr></thead>
      <tbody>
      <?php foreach ($unclaimed as $u): ?>
        <tr><td><code>/r/<?= e($u['ref_code']) ?></code></td><td class="num"><strong><?= number_format((int) $u['opens']) ?></strong></td>
          <td><small class="muted"><?= e(fmt_date($u['first_open'], 'j M H:i')) ?> → <?= e(fmt_date($u['last_open'], 'j M H:i')) ?></small></td>
          <td><?= (int) $u['claims'] ? '<span class="pill pill-warn">' . (int) $u['claims'] . ' pending</span>' : '<span class="muted">none yet</span>' ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$unclaimed): ?><tr><td colspan="4" class="muted">No opens on unclaimed links yet.</td></tr><?php endif; ?>
      </tbody>
    </table></div>
  </div>

  <div class="card">
    <h2>Restore a score from evidence</h2>
    <p class="muted" style="margin-top:0">Adds link opens from before the outage — for example a leaderboard archive snapshot, the 3 October draw record or a dashboard screenshot. Only add what the evidence proves. Every addition is logged and can be removed.</p>
    <?php if ($isAdmin): ?>
    <form method="post" class="grid" style="gap:10px">
      <?= csrf_field() ?><input type="hidden" name="action" value="add_opens">
      <div><label class="f">Entrant (email or share code)</label><input class="in" name="entrant" required placeholder="name@example.com or EHK7T85"></div>
      <div class="grid g-2" style="gap:10px">
        <div><label class="f">Link opens to add</label><input class="in" name="opens" type="number" min="1" required></div>
        <div><label class="f">Score as at</label><input class="in" name="as_at" type="datetime-local" required></div>
      </div>
      <div><label class="f">Reason</label><input class="in" name="reason" required maxlength="255" placeholder="e.g. Wayback Machine leaderboard snapshot, 15 Oct 2026"></div>
      <div><label class="f">Evidence (links, file names)</label><textarea class="in" name="evidence" rows="3" placeholder="https://web.archive.org/web/20261015062926/https://georgemusicalfestival.co.za/flyfortwo/"></textarea></div>
      <div><button class="btn btn-primary" type="submit"><i class="fa-solid fa-plus"></i> Add restored opens</button></div>
    </form>
    <?php else: ?><p class="muted">Only GMF administrators can restore scores.</p><?php endif; ?>
  </div>
</div>

<div class="card">
  <h2>Restored scores</h2>
  <div class="t-wrap"><table class="t">
    <thead><tr><th>Entrant</th><th class="num">Opens</th><th>As at</th><th>Reason &amp; evidence</th><th>Added</th><?php if ($isAdmin): ?><th></th><?php endif; ?></tr></thead>
    <tbody>
    <?php foreach ($adjustments as $a): ?>
      <tr>
        <td><strong><?= e($a['full_name']) ?></strong><br><small class="muted"><?= e($a['ref_code']) ?></small></td>
        <td class="num"><strong><?= number_format((int) $a['opens']) ?></strong></td>
        <td><small><?= e(fmt_date($a['as_at'], 'j M Y H:i')) ?></small></td>
        <td style="max-width:360px"><?= e($a['reason']) ?><?php if ($a['evidence']): ?><br><small class="muted" style="word-break:break-all"><?= nl2br(e($a['evidence'])) ?></small><?php endif; ?></td>
        <td><small class="muted"><?= e(fmt_date($a['created_at'], 'j M H:i')) ?> · <?= e($a['admin_name'] ?? '') ?></small></td>
        <?php if ($isAdmin): ?><td><form method="post" onsubmit="return confirm('Remove these <?= (int) $a['opens'] ?> restored opens from <?= e(addslashes($a['full_name'])) ?>?')"><?= csrf_field() ?><input type="hidden" name="action" value="remove_opens"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><button class="btn btn-sm btn-danger" type="submit"><i class="fa-solid fa-trash"></i></button></form></td><?php endif; ?>
      </tr>
    <?php endforeach; ?>
    <?php if (!$adjustments): ?><tr><td colspan="6" class="muted">No scores restored yet.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>
<?php admin_foot(); ?>
