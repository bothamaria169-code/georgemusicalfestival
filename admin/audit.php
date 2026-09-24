<?php
require __DIR__ . '/_layout.php';

require_admin('admin');
$rows = q('SELECT l.*, a.name FROM audit_log l LEFT JOIN admins a ON a.id = l.admin_id ORDER BY l.id DESC LIMIT 300')->fetchAll();

admin_head('Audit log', 'audit');
?>
<div class="card">
  <h2>Last 300 administrative actions</h2>
  <div class="t-wrap"><table class="t">
    <thead><tr><th>When</th><th>Who</th><th>Action</th><th>Detail</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr><td class="muted" style="white-space:nowrap"><?= e(fmt_date($r['created_at'], 'j M Y H:i:s')) ?></td><td><?= e($r['name'] ?: 'system') ?></td><td><span class="pill pill-muted"><?= e($r['action']) ?></span></td><td><small class="muted"><?= e(mb_strimwidth((string) $r['detail'], 0, 180, '…')) ?></small></td></tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="4" class="muted">Nothing logged yet.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>
<?php admin_foot(); ?>
