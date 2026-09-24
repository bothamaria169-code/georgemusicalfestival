<?php
require __DIR__ . '/_layout.php';

require_admin('admin');

if (isset($_GET['export'])) {
    audit('subscribers.export');
    $rows = q('SELECT email, phone, source, created_at FROM subscribers ORDER BY id');
    csv_download('gmf-mailing-list-' . date('Ymd') . '.csv', ['Email', 'Phone', 'Source', 'Joined'],
        (function () use ($rows) { foreach ($rows as $r) { yield [$r['email'], display_phone($r['phone']), $r['source'], $r['created_at']]; } })());
}

$per = 100;
$total = (int) q('SELECT COUNT(*) FROM subscribers')->fetchColumn();
$pages = max(1, (int) ceil($total / $per));
$page = min($pages, max(1, (int) ($_GET['page'] ?? 1)));
$rows = q('SELECT * FROM subscribers ORDER BY id DESC LIMIT ' . $per . ' OFFSET ' . (($page - 1) * $per))->fetchAll();
$optin = (int) q("SELECT COUNT(*) FROM entrants WHERE status = 'verified' AND consent_marketing = 1")->fetchColumn();

admin_head('Mailing list', 'subscribers');
?>
<div class="grid g-3" style="margin-bottom:16px">
  <div class="card kpi is-accent"><small>Website sign-ups</small><strong><?= number_format($total) ?></strong><span>newsletter & notify-me forms</span></div>
  <div class="card kpi"><small>With phone number</small><strong><?= number_format((int) q('SELECT COUNT(*) FROM subscribers WHERE phone IS NOT NULL')->fetchColumn()) ?></strong><span>ready for SMS / WhatsApp</span></div>
  <div class="card kpi"><small>Competition marketing opt-ins</small><strong><?= number_format($optin) ?></strong><span>see Entrants → Marketing: opted in</span></div>
</div>
<div class="card">
  <h2>Sign-ups <a class="btn btn-sm" href="?export=1"><i class="fa-solid fa-file-csv"></i> Export CSV</a></h2>
  <p class="muted" style="margin-top:0">Competition entries are ACSA-owned data and are kept separate. This list is GMF's own, collected with explicit consent on the website.</p>
  <div class="t-wrap"><table class="t">
    <thead><tr><th>Email</th><th>Phone</th><th>Source</th><th>Joined</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr><td><?= e($r['email']) ?></td><td><?= e(display_phone($r['phone'])) ?: '<span class="muted">—</span>' ?></td><td><span class="pill pill-muted"><?= e($r['source']) ?></span></td><td class="muted"><?= e(fmt_date($r['created_at'], 'j M Y H:i')) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="4" class="muted">No sign-ups yet.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
  <?= admin_pager($page, $pages) ?>
</div>
<?php admin_foot(); ?>
