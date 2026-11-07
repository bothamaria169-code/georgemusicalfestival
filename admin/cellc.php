<?php
/** WIN A SAMSUNG (Cell C) in the GMF admin: totals only. Entrant records stay in the Cell C admin. */
require __DIR__ . '/_layout.php';
require dirname(__DIR__) . '/includes/campaign-overview.php';

require_admin('admin');

if (($_GET['export'] ?? '') === 'entrants' && ($ePdo = cellc_pdo())) {
    $status = (string) ($_GET['status'] ?? 'all');
    $rows = cellc_entrants($ePdo, $status);
    audit('cellc.entrants_export', ['status' => $status, 'rows' => count($rows)]);
    $out = [];
    foreach ($rows as $p) {
        $out[] = [$p['id'], $p['full_name'], $p['email'], $p['phone'], $p['town'], $p['age_group'], $p['cellc_products'],
            $p['consent_terms'] ? 'yes' : 'no', $p['consent_popia'] ? 'yes' : 'no', $p['consent_marketing'] ? 'yes' : 'no', $p['email_status'] ? $p['email_issue'] : '',
            $p['status'], $p['verified_at'], $p['channel'], $p['activation_point'], $p['source_town'], $p['ref_code'], $p['referrer_name'], (int) $p['opens'], (int) $p['friends'], $p['created_at']];
    }
    csv_download('win-a-samsung-entrants-' . ($status === 'verified' ? 'verified-' : '') . date('Ymd-His') . '.csv',
        ['ID', 'Full name', 'Email', 'Phone', 'Town', 'Age group', 'Cell C products', 'Terms consent', 'POPIA consent', 'Marketing consent', 'Email problem',
         'Status', 'Verified at', 'Channel', 'Activation point', 'Source town', 'Share code', 'Referred by', 'Link opens', 'Friends entered', 'Entered at'], $out);
}

if (($_GET['export'] ?? '') === 'leaderboard' && ($lbPdo = cellc_pdo())) {
    $rows = cellc_leaderboard($lbPdo);
    audit('cellc.leaderboard_export', ['rows' => count($rows)]);
    $out = [];
    foreach ($rows as $i => $r) {
        $out[] = [$i + 1, $r['full_name'], $r['email'], $r['phone'], $r['town'], $r['ref_code'], (int) $r['opens'], (int) $r['networks'], (int) $r['friends'], $r['last_open'], $r['verified_at']];
    }
    csv_download('win-a-samsung-leaderboard-' . date('Ymd-His') . '.csv',
        ['Rank', 'Full name', 'Email', 'Phone', 'Town', 'Share code', 'Link opens to date', 'Different networks', 'Friends entered', 'Last open', 'Verified at'], $out);
}
$s = campaign_snapshot_safe(cellc_pdo());
$mail = campaign_email_stats('cellc');

admin_head('Cell C · WIN A SAMSUNG', 'cellc');
campaign_card('WIN A SAMSUNG', 'Cell C campaign', '#e8590c', $s, $mail, [
    ['Competition page', url('cellc/win/'), true],
    ['Open Cell C admin', url('cellc/admin/'), true],
    ['Write an email', url('admin/compose.php?brand=cellc'), false],
    ['Export verified entrants (CSV)', url('admin/cellc.php?export=entrants&status=verified'), false],
    ['Export all entrants (CSV)', url('admin/cellc.php?export=entrants&status=all'), false],
    ['Export leaderboard (CSV)', url('admin/cellc.php?export=leaderboard'), false],
]);
?>

<?php if ($s): ?>
<div class="grid g-2" style="margin-top:16px;">
  <div class="card">
    <h2>By channel</h2>
    <table class="t">
      <thead><tr><th>Channel</th><th class="num">Verified</th><th class="num">Pending</th></tr></thead>
      <tbody>
      <?php foreach ($s['by_channel'] as $c): ?>
        <tr><td><?= e(channel_label((string) $c['channel'])) ?></td><td class="num"><strong><?= (int) $c['verified'] ?></strong></td><td class="num muted"><?= (int) $c['pending'] ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$s['by_channel']): ?><tr><td colspan="3" class="muted">No entries yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="card">
    <h2>By town</h2>
    <table class="t">
      <thead><tr><th>Town</th><th class="num">Verified</th></tr></thead>
      <tbody>
      <?php foreach ($s['by_town'] as $t): ?>
        <tr><td><?= e($t['town']) ?></td><td class="num"><strong><?= (int) $t['verified'] ?></strong></td></tr>
      <?php endforeach; ?>
      <?php if (!$s['by_town']): ?><tr><td colspan="2" class="muted">No entries yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<div class="card" style="margin-top:16px;">
  <h2>Cell C email campaigns</h2>
  <table class="t">
    <thead><tr><th>Campaign</th><th>Status</th><th class="num">Sent</th><th class="num">Not sent</th><th class="num">Failed</th></tr></thead>
    <tbody>
    <?php foreach ($mail['campaigns'] as $c): ?>
      <tr><td><a href="<?= e(url('admin/campaigns.php?id=' . (int) $c['id'])) ?>"><?= e($c['name']) ?></a></td><td><?= e($c['status']) ?></td><td class="num"><?= (int) $c['sent'] ?></td><td class="num"><?= (int) $c['queued'] ?></td><td class="num"><?= (int) $c['failed'] ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$mail['campaigns']): ?><tr><td colspan="5" class="muted">No Cell C campaigns yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php admin_foot();
