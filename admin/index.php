<?php
require __DIR__ . '/_layout.php';
require dirname(__DIR__) . '/includes/reports.php';

$admin = require_admin();
$r = report_summary();
$t = $r['totals'];
$state = comp_state();
$maxTown = max(array_column($r['by_town'], 'verified') ?: [1]);
$maxFly = max(array_column($r['travel_profile'], 'verified') ?: [1]);
$recent = q("SELECT full_name, town, channel, activation_point, verified_at FROM entrants WHERE status = 'verified' ORDER BY verified_at DESC LIMIT 12")->fetchAll();

// Fill missing days so the chart shows a continuous timeline.
$byDay = array_column($r['by_day'], 'verified', 'day');
$days = [];
$start = $r['by_day'] ? strtotime($r['by_day'][0]['day']) : strtotime('-13 days');
for ($d = $start; $d <= strtotime('today'); $d += 86400) {
    $days[date('j M', $d)] = (int) ($byDay[date('Y-m-d', $d)] ?? 0);
}
$hours = array_fill(0, 24, 0);
foreach ($r['event_day_by_hour'] as $h) {
    $hours[(int) $h['hour']] += (int) $h['verified'];
}

admin_head('Overview', 'overview');
?>
<div class="toolbar">
  <div class="grow">
    <span class="pill <?= $state === 'open' ? 'pill-ok' : ($state === 'upcoming' ? 'pill-warn' : 'pill-muted') ?>">Competition <?= e($state) ?></span>
    <span class="muted" style="margin-left:8px;"><?= e(fmt_date(setting('comp_open_at'), 'j M Y H:i')) ?> → <?= e(fmt_date(setting('comp_close_at'), 'j M Y H:i')) ?></span>
  </div>
  <a class="btn" href="entrants.php?export=1"><i class="fa-solid fa-file-csv"></i> Export verified entrants</a>
  <a class="btn" href="index.php"><i class="fa-solid fa-rotate"></i> Refresh</a>
</div>

<?php $mailProblems = (int) q('SELECT COUNT(*) FROM entrants WHERE email_status IS NOT NULL')->fetchColumn(); ?>
<?php if ($mailProblems): ?>
<div class="card" style="margin-bottom:16px;border-left:4px solid #e05a4f;display:flex;gap:14px;align-items:center;flex-wrap:wrap">
  <i class="fa-solid fa-triangle-exclamation" style="color:#e05a4f;font-size:1.3rem"></i>
  <div style="flex:1;min-width:220px"><strong><?= $mailProblems ?> entrant<?= $mailProblems === 1 ? '' : 's' ?> with an email problem</strong><br><span class="muted">Their code emails bounced or could not be sent — usually a mistyped address. They can't confirm or sign in until the email is fixed.</span></div>
  <a class="btn" href="entrants.php?status=all&amp;mail=problem"><i class="fa-solid fa-at"></i> Review &amp; fix</a>
</div>
<?php endif; ?>
<div class="grid g-4" style="margin-bottom:16px;">
  <div class="card kpi is-accent"><small>Verified entrants</small><strong><?= number_format($t['verified_entrants']) ?></strong><span>+<?= $t['verified_today'] ?> today</span></div>
  <div class="card kpi"><small>Draw entries</small><strong><?= number_format($t['draw_entries']) ?></strong><span><?= (int) ($t['entries_by_kind']['share_bonus'] ?? 0) ?> share bonus · <?= (int) ($t['entries_by_kind']['referral_bonus'] ?? 0) ?> referral</span></div>
  <div class="card kpi"><small>Marketing opt-in</small><strong><?= number_format($t['marketing_opt_in']) ?></strong><span><?= $t['verified_entrants'] ? round($t['marketing_opt_in'] / $t['verified_entrants'] * 100) : 0 ?>% of verified leads</span></div>
  <div class="card kpi"><small>Referred entrants</small><strong><?= number_format($t['referred_entrants']) ?></strong><span><?= number_format($t['link_clicks']) ?> share-link opens</span></div>
</div>

<div class="grid g-21" style="margin-bottom:16px;">
  <div class="card">
    <h2>Verified entries per day <span class="muted" style="letter-spacing:0;text-transform:none;font-weight:600"><?= number_format($t['pending_unverified']) ?> started but unverified</span></h2>
    <div class="chart-box"><canvas id="dayChart"></canvas></div>
  </div>
  <div class="card">
    <h2>By channel</h2>
    <table class="t">
      <thead><tr><th>Channel</th><th class="num">Verified</th><th class="num">Pending</th></tr></thead>
      <tbody>
      <?php foreach ($r['by_channel'] as $c): ?>
        <tr><td><?= e(channel_label($c['channel'])) ?></td><td class="num"><strong><?= (int) $c['verified'] ?></strong></td><td class="num muted"><?= (int) $c['pending'] ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$r['by_channel']): ?><tr><td colspan="3" class="muted">No entries yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="grid g-3" style="margin-bottom:16px;">
  <div class="card">
    <h2>By town</h2>
    <div class="hbar">
      <?php foreach (array_slice($r['by_town'], 0, 10) as $row): ?>
        <div><em><?= e($row['town']) ?></em><i style="width:<?= round($row['verified'] / $maxTown * 100) ?>%"></i><b><?= (int) $row['verified'] ?></b></div>
      <?php endforeach; ?>
      <?php if (!$r['by_town']): ?><p class="muted">No data yet.</p><?php endif; ?>
    </div>
  </div>
  <div class="card">
    <h2>Travel profile</h2>
    <div class="hbar">
      <?php foreach ($r['travel_profile'] as $row): ?>
        <div><em title="<?= e($row['answer']) ?>"><?= e($row['answer'] ?: '—') ?></em><i style="width:<?= round($row['verified'] / $maxFly * 100) ?>%"></i><b><?= (int) $row['verified'] ?></b></div>
      <?php endforeach; ?>
      <?php if (!$r['travel_profile']): ?><p class="muted">No data yet.</p><?php endif; ?>
    </div>
    <h2 style="margin-top:22px">Age groups</h2>
    <div class="hbar">
      <?php $maxAge = max(array_column($r['age_profile'], 'verified') ?: [1]); foreach ($r['age_profile'] as $row): ?>
        <div><em><?= e($row['answer']) ?></em><i style="width:<?= round($row['verified'] / $maxAge * 100) ?>%"></i><b><?= (int) $row['verified'] ?></b></div>
      <?php endforeach; ?>
      <?php if (!$r['age_profile']): ?><p class="muted">No data yet.</p><?php endif; ?>
    </div>
  </div>
  <div class="card">
    <h2>Event day by hour <span class="muted" style="letter-spacing:0;text-transform:none;font-weight:600"><?= e(fmt_date(setting('event_date'), 'j M')) ?></span></h2>
    <div class="chart-box" style="height:200px"><canvas id="hourChart"></canvas></div>
  </div>
</div>

<div class="grid g-2">
  <div class="card">
    <h2>Top activation points</h2>
    <div class="t-wrap"><table class="t">
      <thead><tr><th>Activation point</th><th>Channel</th><th>Town</th><th class="num">Verified</th></tr></thead>
      <tbody>
      <?php foreach (array_slice($r['by_activation_point'], 0, 12) as $p): ?>
        <tr><td><?= e($p['activation_point']) ?></td><td><?= e(channel_label($p['channel'])) ?></td><td><?= e($p['town']) ?></td><td class="num"><strong><?= (int) $p['verified'] ?></strong></td></tr>
      <?php endforeach; ?>
      <?php if (!$r['by_activation_point']): ?><tr><td colspan="4" class="muted">Tag QR codes with <code>?src=qr_ground&amp;pt=…&amp;town=…</code> to see activation points here. Build tagged links under Settings → QR link builder.</td></tr><?php endif; ?>
      </tbody>
    </table></div>
  </div>
  <div class="card">
    <h2>Latest verified entrants</h2>
    <div class="t-wrap"><table class="t">
      <thead><tr><th>Name</th><th>Town</th><th>Channel</th><th>Verified</th></tr></thead>
      <tbody>
      <?php foreach ($recent as $p): ?>
        <tr><td><?= e($p['full_name']) ?></td><td><?= e($p['town']) ?></td><td><span class="pill pill-info"><?= e($p['channel']) ?></span></td><td class="muted"><?= e(fmt_date($p['verified_at'], 'j M H:i')) ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$recent): ?><tr><td colspan="4" class="muted">No verified entrants yet.</td></tr><?php endif; ?>
      </tbody>
    </table></div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
(function () {
  if (!window.Chart) return;
  Chart.defaults.color = '#8b939c';
  Chart.defaults.font.family = 'Manrope, sans-serif';
  Chart.defaults.borderColor = '#1f242a';
  new Chart(document.getElementById('dayChart'), {
    type: 'bar',
    data: { labels: <?= json_encode(array_keys($days)) ?>, datasets: [{ label: 'Verified', data: <?= json_encode(array_values($days)) ?>, backgroundColor: '#ff9a00', borderRadius: 4, maxBarThickness: 28 }] },
    options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, x: { grid: { display: false } } } }
  });
  new Chart(document.getElementById('hourChart'), {
    type: 'line',
    data: { labels: <?= json_encode(array_map(fn ($h) => sprintf('%02d:00', $h), range(0, 23))) ?>, datasets: [{ data: <?= json_encode($hours) ?>, borderColor: '#1a8ea6', backgroundColor: 'rgba(26,142,166,.18)', fill: true, tension: .35, pointRadius: 0 }] },
    options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, x: { grid: { display: false }, ticks: { maxTicksLimit: 8 } } } }
  });
})();
</script>
<?php admin_foot(); ?>
