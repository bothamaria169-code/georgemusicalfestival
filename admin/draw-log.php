<?php
/** Certification log for the independent attorney: printable page, CSV, and pool snapshots. */
require __DIR__ . '/_layout.php';
require dirname(__DIR__) . '/includes/draw.php';

require_admin();
$draw = q('SELECT * FROM draws WHERE id = ?', [(int) ($_GET['draw'] ?? 0)])->fetch();
if (!$draw) {
    exit('Draw not found.');
}
$runs = draw_runs((int) $draw['id']);

// Pool snapshot for a run: index,entry_id,entrant_id (hash of entry_id list = pool_hash)
if (isset($_GET['pool'])) {
    $runId = (int) $_GET['pool'];
    $file = draw_pool_dir() . "/run_{$runId}_pool.csv";
    $owns = in_array($runId, array_map('intval', array_column($runs, 'id')), true);
    if (!$owns || !is_file($file)) {
        exit('Pool snapshot not found.');
    }
    audit('draw.pool_download', ['run_id' => $runId]);
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="draw-' . $draw['draw_key'] . '-run-' . $runId . '-pool.csv"');
    readfile($file);
    exit;
}

if (($_GET['format'] ?? '') === 'csv') {
    audit('draw.log_export', ['draw_id' => $draw['id']]);
    csv_download('draw-log-' . $draw['draw_key'] . '-' . date('Ymd-His') . '.csv',
        ['Prize', 'Run', 'Next-in-line', 'Reason', 'Revealed at', 'Cut-off', 'Entrants on leaderboard', 'Total link opens', 'Snapshot SHA-256', 'Snapshot SHA-256 (copy)', 'Place (0 = first)', 'Winner link opens', 'Entrant ID', 'Winner name', 'Winner phone', 'Winner email', 'Outcome', 'Outcome note', 'Witness', 'Triggered by'],
        array_map(fn ($r) => [$draw['title'], $r['run_no'], $r['is_redraw'] ? 'yes' : 'no', $r['redraw_reason'], $r['created_at'], $r['cutoff_at'], $r['pool_entrants'], $r['pool_entries'], $r['pool_hash'], $r['random_proof'],
            $r['selected_index'], $r['selected_entry_id'], $r['selected_entrant_id'], $r['full_name'], display_phone($r['phone']), $r['email'], $r['outcome'], $r['outcome_note'], $r['witness'], $r['admin_name']], $runs));
}
?><!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="robots" content="noindex"><title>Draw certification log — <?= e($draw['title']) ?></title>
<style>
  body { font-family: Arial, Helvetica, sans-serif; color: #111; max-width: 980px; margin: 30px auto; padding: 0 20px; font-size: 13px; line-height: 1.5; }
  h1 { font-size: 22px; margin: 0 0 4px; } h2 { font-size: 15px; margin: 28px 0 8px; border-bottom: 2px solid #111; padding-bottom: 4px; }
  table { width: 100%; border-collapse: collapse; margin: 8px 0; } td, th { border: 1px solid #bbb; padding: 6px 8px; text-align: left; vertical-align: top; }
  th { background: #f1f1f1; width: 200px; } code { font-size: 11px; word-break: break-all; }
  .head { display: flex; justify-content: space-between; align-items: center; border-bottom: 3px solid #ff9a00; padding-bottom: 14px; margin-bottom: 18px; }
  .head img { height: 50px; } .sign { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-top: 50px; } .sign div { border-top: 1px solid #111; padding-top: 6px; }
  .no-print { margin-bottom: 16px; } @media print { .no-print { display: none; } }
</style></head>
<body>
<div class="no-print"><button onclick="print()">Print / save as PDF</button> <a href="?draw=<?= (int) $draw['id'] ?>&format=csv">Download CSV</a></div>
<div class="head">
  <div><h1><?= e(setting('comp_name')) ?> — <?= e($draw['title']) ?></h1>Draw certification log · generated <?= e(date('j F Y H:i:s T')) ?></div>
  <img src="<?= e(url('assets/img/brand/acsa-teal.png')) ?>" alt="ACSA">
</div>
<table>
  <tr><th>Competition</th><td><?= e(setting('comp_name')) ?> · George Music Festival × Airports Company South Africa</td></tr>
  <tr><th>Draw</th><td><?= e($draw['title']) ?> (<?= e($draw['draw_key']) ?>) — scheduled <?= e(fmt_date($draw['scheduled_for'], 'j F Y')) ?></td></tr>
  <tr><th>Prize</th><td><?= nl2br(e($draw['prize_description'] ?: 'TBC')) ?></td></tr>
  <tr><th>Competition period</th><td><?= e(setting('comp_open_at')) ?> to <?= e(setting('comp_close_at')) ?></td></tr>
  <tr><th>Selection method</th><td>Leaderboard (rules from 24 September 2026). Each verified entrant's score is the number of unique visitors who opened their personal link while entries were open. The entrant's own devices never count, and one network adds at most 3 opens per entrant per day. Ties go to more friends who entered through the link, then to whoever reached the score first. The Grand Prize goes to 1st place and the Second Prize to the next eligible entrant. At each reveal the standings are frozen at the cut-off, saved as a snapshot (download below) and fingerprinted with SHA-256.</td></tr>
</table>

<?php foreach ($runs as $r): ?>
<h2>Run <?= (int) $r['run_no'] ?><?= $r['is_redraw'] ? ' — REDRAW' : '' ?></h2>
<table>
  <tr><th>Triggered at</th><td><?= e($r['created_at']) ?> by <?= e($r['admin_name'] ?: '—') ?></td></tr>
  <tr><th>Witness</th><td><?= e($r['witness'] ?: '—') ?></td></tr>
  <?php if ($r['is_redraw']): ?><tr><th>Redraw reason</th><td><?= e($r['redraw_reason']) ?></td></tr><?php endif; ?>
  <tr><th>Pool cut-off</th><td><?= e($r['cutoff_at']) ?></td></tr>
  <tr><th>Leaderboard</th><td><?= number_format((int) $r['pool_entrants']) ?> entrants with link opens · <?= number_format((int) $r['pool_entries']) ?> link opens in total</td></tr>
  <tr><th>Snapshot SHA-256</th><td><code><?= e($r['pool_hash']) ?></code> · <a href="?draw=<?= (int) $draw['id'] ?>&pool=<?= (int) $r['id'] ?>">download leaderboard snapshot</a></td></tr>
    <tr><th>Selected</th><td>Place #<?= (int) $r['selected_index'] + 1 ?> with <?= (int) $r['selected_entry_id'] ?> link opens (entrant #<?= (int) $r['selected_entrant_id'] ?>)</td></tr>
  <tr><th>Selected entrant</th><td><?= e($r['full_name']) ?> · <?= e(display_phone($r['phone'])) ?> · <?= e($r['email'] ?: '—') ?> · <?= e($r['town'] ?: '—') ?> · channel <?= e($r['channel']) ?></td></tr>
  <tr><th>Outcome</th><td><strong><?= e(strtoupper($r['outcome'])) ?></strong><?= $r['outcome_note'] ? ' — ' . e($r['outcome_note']) : '' ?></td></tr>
</table>
<?php endforeach; ?>
<?php if (!$runs): ?><p>No runs yet.</p><?php endif; ?>

<div class="sign">
  <div>ACSA representative — name, signature, date</div>
  <div>Independent attorney — name, signature, date</div>
</div>
</body></html>
