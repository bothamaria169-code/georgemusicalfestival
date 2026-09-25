<?php
require __DIR__ . '/_layout.php';

$admin = require_admin();
$isAdmin = $admin['role'] === 'admin';

// Admin actions: disqualify / reinstate
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAdmin && csrf_ok()) {
    $id = (int) ($_POST['id'] ?? 0);
    $note = mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 255);
    if (($_POST['action'] ?? '') === 'disqualify') {
        q("UPDATE entrants SET status = 'disqualified', notes = ? WHERE id = ? AND status = 'verified'", [$note ?: 'Disqualified by admin', $id]);
        audit('entrant.disqualify', ['id' => $id, 'note' => $note]);
        flash('success', 'Entrant disqualified — their entries are excluded from future draws.');
    } elseif (($_POST['action'] ?? '') === 'reinstate') {
        q("UPDATE entrants SET status = 'verified', notes = ? WHERE id = ? AND status = 'disqualified' AND verified_at IS NOT NULL", [$note ?: null, $id]);
        audit('entrant.reinstate', ['id' => $id]);
        flash('success', 'Entrant reinstated.');
    }
    if (($_POST['action'] ?? '') === 'photo') {
        $saved = save_entrant_photo($_FILES['photo'] ?? [], $id);
        audit('entrant.photo', ['id' => $id, 'ok' => (bool) $saved]);
        flash($saved ? 'success' : 'error', $saved ? 'Photo saved — it will show on the winner reveal.' : 'That file could not be used (JPG, PNG or WebP only).');
    }
    redirect('admin/entrants.php?' . http_build_query(array_diff_key($_GET, ['page' => 1])));
}

$status  = $_GET['status'] ?? 'verified';
$channel = $_GET['channel'] ?? '';
$mkt     = $_GET['mkt'] ?? '';
$search  = trim((string) ($_GET['q'] ?? ''));

$where = [];
$params = [];
if (in_array($status, ['verified', 'pending', 'disqualified'], true)) {
    $where[] = 'p.status = ?';
    $params[] = $status;
}
if (isset(CHANNELS[$channel])) {
    $where[] = 'p.channel = ?';
    $params[] = $channel;
}
if ($mkt === '1' || $mkt === '0') {
    $where[] = 'p.consent_marketing = ?';
    $params[] = (int) $mkt;
}
if ($search !== '') {
    $digits = preg_replace('/\D/', '', $search);
    $where[] = '(p.full_name LIKE ? OR p.email LIKE ? OR p.ref_code = ?' . ($digits !== '' ? ' OR p.phone LIKE ?' : '') . ')';
    array_push($params, "%{$search}%", "%{$search}%", strtoupper($search));
    if ($digits !== '') {
        $params[] = '%' . ltrim($digits, '0') . '%';
    }
}
$w = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$select = "SELECT p.*, (SELECT COUNT(*) FROM referral_clicks c WHERE c.referrer_id = p.id) AS entries,
                  (SELECT COUNT(*) FROM entrants x WHERE x.referred_by = p.id AND x.status = 'verified') AS referrals,
                  (SELECT COUNT(*) FROM entrants d WHERE d.device_fp = p.device_fp AND d.status = 'verified') AS same_device,
                  r.full_name AS referrer_name
           FROM entrants p LEFT JOIN entrants r ON r.id = p.referred_by $w";

if (isset($_GET['export'])) {
    audit('entrants.export', ['filters' => $_GET]);
    $rows = q($select . ' ORDER BY p.id', $params);
    csv_download('flyfortwo-entrants-' . date('Ymd-His') . '.csv',
        ['ID', 'Full name', 'Email', 'Phone', 'Town', 'Age group', 'Travel profile', 'Terms consent', 'POPIA consent', 'Marketing consent', 'Consent at', 'Status', 'Verified at', 'Verified via',
         'Channel', 'Activation point', 'Source town', 'Time slot', 'UTM source', 'UTM medium', 'UTM campaign', 'Referral code', 'Referred by', 'Link opens', 'Friends referred', 'Created at'],
        (function () use ($rows) {
            foreach ($rows as $p) {
                yield [$p['id'], $p['full_name'], $p['email'], display_phone($p['phone']), $p['town'], $p['age_group'], $p['fly_frequency'], $p['consent_terms'] ? 'yes' : 'no', $p['consent_popia'] ? 'yes' : 'no',
                    $p['consent_marketing'] ? 'yes' : 'no', $p['consent_at'], $p['status'], $p['verified_at'], $p['verified_via'], $p['channel'], $p['activation_point'],
                    $p['source_town'], $p['time_slot'], $p['utm_source'], $p['utm_medium'], $p['utm_campaign'], $p['ref_code'], $p['referrer_name'], $p['entries'], $p['referrals'], $p['created_at']];
            }
        })());
}

$per = 50;
$total = (int) q("SELECT COUNT(*) FROM entrants p $w", $params)->fetchColumn();
$pages = max(1, (int) ceil($total / $per));
$page = min($pages, max(1, (int) ($_GET['page'] ?? 1)));
$rows = q($select . ' ORDER BY p.id DESC LIMIT ' . $per . ' OFFSET ' . (($page - 1) * $per), $params)->fetchAll();

admin_head('Entrants', 'entrants');
?>
<form class="toolbar" method="get">
  <div class="grow"><label class="f">Search</label><input class="in" name="q" value="<?= e($search) ?>" placeholder="Name, email, phone or referral code"></div>
  <div><label class="f">Status</label><select class="in" name="status">
    <?php foreach (['verified' => 'Verified', 'pending' => 'Unverified', 'disqualified' => 'Disqualified', 'all' => 'All'] as $k => $v): ?><option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?>
  </select></div>
  <div><label class="f">Channel</label><select class="in" name="channel"><option value="">All</option>
    <?php foreach (CHANNELS as $k => $v): ?><option value="<?= $k ?>" <?= $channel === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
  </select></div>
  <div><label class="f">Marketing</label><select class="in" name="mkt"><option value="">Any</option><option value="1" <?= $mkt === '1' ? 'selected' : '' ?>>Opted in</option><option value="0" <?= $mkt === '0' ? 'selected' : '' ?>>Not opted in</option></select></div>
  <button class="btn btn-primary" type="submit"><i class="fa-solid fa-filter"></i> Filter</button>
  <a class="btn" href="?<?= e(http_build_query(array_merge($_GET, ['export' => 1]))) ?>"><i class="fa-solid fa-file-csv"></i> Export <?= number_format($total) ?></a>
</form>

<div class="card">
  <div class="t-wrap"><table class="t">
    <thead><tr><th>#</th><th>Entrant</th><th>Contact</th><th>Town</th><th>Channel / source</th><th>Consent</th><th class="num">Link opens</th><th class="num">Friends</th><th>Status</th><?php if ($isAdmin): ?><th></th><?php endif; ?></tr></thead>
    <tbody>
    <?php foreach ($rows as $p): ?>
      <tr>
        <td class="muted"><?= (int) $p['id'] ?></td>
        <td><?php if ($p['photo']): ?><img src="<?= e(media_url($p['photo'])) ?>" alt="" style="width:30px;height:30px;border-radius:50%;object-fit:cover;float:left;margin-right:8px"><?php endif; ?><strong><?= e($p['full_name']) ?></strong><br><small class="muted"><?= e($p['ref_code']) ?><?= $p['referrer_name'] ? ' · via ' . e(short_name($p['referrer_name'])) : '' ?></small></td>
        <td><?= e($p['email'] ?: '—') ?><br><small class="muted"><?= e(display_phone($p['phone'])) ?></small></td>
        <td><?= e($p['town'] ?: '—') ?><br><small class="muted"><?= e(trim(($p['age_group'] ? $p['age_group'] . ' · ' : '') . $p['fly_frequency'])) ?></small></td>
        <td><span class="pill pill-info"><?= e($p['channel']) ?></span><br><small class="muted"><?= e(trim(($p['activation_point'] ?? '') . ' ' . ($p['time_slot'] ? '· ' . $p['time_slot'] : ''))) ?></small></td>
        <td><span class="pill pill-ok">Terms</span> <?= $p['consent_popia'] ? '<span class="pill pill-ok">POPIA</span>' : '' ?><?= $p['consent_marketing'] ? '<span class="pill pill-ok">Mkt</span>' : '<span class="pill pill-muted">No mkt</span>' ?></td>
        <td class="num"><strong><?= (int) $p['entries'] ?></strong></td>
        <td class="num"><?= (int) $p['referrals'] ?></td>
        <td>
          <?php if ($p['status'] === 'verified'): ?><span class="pill pill-ok">Verified</span><?php elseif ($p['status'] === 'pending'): ?><span class="pill pill-warn">Unverified</span><?php else: ?><span class="pill pill-bad" title="<?= e($p['notes']) ?>">Disqualified</span><?php endif; ?>
          <?php if ((int) $p['same_device'] > 2): ?><br><span class="pill pill-warn" title="Device fingerprint shared by <?= (int) $p['same_device'] ?> verified entrants — review for duplicate entries">Shared device ×<?= (int) $p['same_device'] ?></span><?php endif; ?>
          <br><small class="muted"><?= e(fmt_date($p['verified_at'] ?: $p['created_at'], 'j M H:i')) ?></small>
        </td>
        <?php if ($isAdmin): ?>
        <td>
          <?php if ($p['status'] === 'verified'): ?>
            <form method="post" onsubmit="var n=prompt('Reason for disqualification (logged):');if(!n)return false;this.note.value=n;"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="action" value="disqualify"><input type="hidden" name="note"><button class="btn btn-sm btn-danger" type="submit" title="Disqualify"><i class="fa-solid fa-ban"></i></button></form>
            <form method="post" enctype="multipart/form-data" style="margin-top:6px"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="action" value="photo"><label class="btn btn-sm" title="Upload a winner photo for this entrant" style="cursor:pointer"><i class="fa-solid fa-camera"></i><input type="file" name="photo" accept="image/jpeg,image/png,image/webp" hidden onchange="this.form.submit()"></label></form>
          <?php elseif ($p['status'] === 'disqualified'): ?>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="action" value="reinstate"><button class="btn btn-sm btn-ok" type="submit" title="Reinstate"><i class="fa-solid fa-rotate-left"></i></button></form>
          <?php endif; ?>
        </td>
        <?php endif; ?>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="10" class="muted">No entrants match these filters.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
  <?= admin_pager($page, $pages) ?>
</div>
<?php admin_foot(); ?>
