<?php
require __DIR__ . '/_layout.php';
require dirname(__DIR__) . '/includes/draw.php';

$admin = require_admin();
$isAdmin = $admin['role'] === 'admin';

// Edit draw details (the Event-Day prize is an editable field, never hard-coded).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAdmin && csrf_ok()) {
    $id = (int) $_POST['id'];
    q('UPDATE draws SET title = ?, prize_description = ?, scheduled_for = ? WHERE id = ?', [
        mb_substr(trim((string) $_POST['title']), 0, 120),
        trim((string) $_POST['prize_description']),
        $_POST['scheduled_for'] ?: null,
        $id,
    ]);
    audit('draw.edit', ['id' => $id]);
    flash('success', 'Draw details saved.');
    redirect('admin/draws.php');
}

$draws = q('SELECT * FROM draws ORDER BY scheduled_for, id')->fetchAll();
$pool = (int) q("SELECT COUNT(*) FROM entries e JOIN entrants p ON p.id = e.entrant_id WHERE p.status = 'verified'")->fetchColumn();
$poolPeople = (int) q("SELECT COUNT(*) FROM entrants WHERE status = 'verified'")->fetchColumn();

admin_head('Draws', 'draws');
?>
<div class="card" style="margin-bottom:16px;">
  <div class="toolbar" style="margin:0">
    <div class="grow">
      <h3 style="margin:0">Current pool: <?= number_format($pool) ?> entries · <?= number_format($poolPeople) ?> verified entrants</h3>
      <p class="muted" style="margin:4px 0 0">Every run is random, automated and logged with a seed and pool hash so it can be independently re-computed. A redraw only happens after the previous winner is marked ineligible, with a recorded reason.</p>
    </div>
  </div>
</div>

<div class="grid g-2">
<?php foreach ($draws as $d): $runs = draw_runs((int) $d['id']); $last = end($runs) ?: null; ?>
  <div class="card">
    <h2><?= e($d['title']) ?>
      <span class="pill <?= ['scheduled' => 'pill-muted', 'drawn' => 'pill-warn', 'confirmed' => 'pill-ok', 'published' => 'pill-info'][$d['status']] ?>"><?= e($d['status']) ?></span>
    </h2>
    <p style="margin:0 0 12px"><strong><?= e(fmt_date($d['scheduled_for'], 'l, j F Y')) ?></strong><br><span class="muted"><?= nl2br(e($d['prize_description'] ?: 'Prize to be confirmed')) ?></span></p>

    <?php if ($last): ?>
      <div class="winner-box" style="margin-bottom:12px">
        <small class="muted">Run <?= (int) $last['run_no'] ?><?= $last['is_redraw'] ? ' (redraw)' : '' ?> · <?= e(fmt_date($last['created_at'], 'j M Y H:i:s')) ?></small>
        <strong><?= e($last['full_name']) ?></strong>
        <span><?= e(display_phone($last['phone'])) ?> · <?= e($last['email'] ?: 'no email (USSD)') ?> · <?= e($last['town'] ?: '—') ?></span><br>
        <span class="pill <?= $last['outcome'] === 'confirmed' ? 'pill-ok' : ($last['outcome'] === 'disqualified' ? 'pill-bad' : 'pill-warn') ?>"><?= $last['outcome'] === 'selected' ? 'Awaiting verification' : e($last['outcome']) ?></span>
      </div>
    <?php endif; ?>

    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px">
      <?php if ($isAdmin): ?><a class="btn btn-primary" href="draw-live.php?draw=<?= (int) $d['id'] ?>" target="_blank"><i class="fa-solid fa-display"></i> Live draw screen</a><?php endif; ?>
      <?php if ($runs): ?>
        <a class="btn" href="draw-log.php?draw=<?= (int) $d['id'] ?>" target="_blank"><i class="fa-solid fa-file-signature"></i> Certification log</a>
        <a class="btn" href="draw-log.php?draw=<?= (int) $d['id'] ?>&format=csv"><i class="fa-solid fa-file-csv"></i> CSV</a>
      <?php endif; ?>
      <?php if ($isAdmin && in_array($d['status'], ['confirmed', 'published'], true)): ?>
        <button class="btn" data-publish="<?= (int) $d['id'] ?>" data-value="<?= $d['status'] === 'published' ? 0 : 1 ?>"><i class="fa-solid fa-bullhorn"></i> <?= $d['status'] === 'published' ? 'Unpublish winner' : 'Publish winner on site' ?></button>
      <?php endif; ?>
    </div>

    <?php if ($runs): ?>
      <div class="t-wrap"><table class="t">
        <thead><tr><th>Run</th><th>Time</th><th class="num">Pool</th><th>Selected</th><th>Outcome</th></tr></thead>
        <tbody>
        <?php foreach ($runs as $r): ?>
          <tr>
            <td><?= (int) $r['run_no'] ?><?= $r['is_redraw'] ? ' <span class="pill pill-warn">redraw</span>' : '' ?></td>
            <td class="muted"><?= e(fmt_date($r['created_at'], 'j M H:i:s')) ?></td>
            <td class="num"><?= number_format((int) $r['pool_entries']) ?></td>
            <td><?= e($r['full_name']) ?></td>
            <td><?= e($r['outcome']) ?><?= $r['outcome_note'] ? '<br><small class="muted">' . e($r['outcome_note']) . '</small>' : '' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>

    <?php if ($isAdmin): ?>
    <details style="margin-top:12px">
      <summary class="muted" style="cursor:pointer">Edit draw details</summary>
      <form method="post" style="margin-top:12px">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
        <div class="fg"><label class="f">Title</label><input class="in" name="title" value="<?= e($d['title']) ?>" required></div>
        <div class="fg"><label class="f">Draw date</label><input class="in" type="date" name="scheduled_for" value="<?= e($d['scheduled_for']) ?>"></div>
        <div class="fg"><label class="f">Prize description</label><textarea class="in" name="prize_description" style="font-family:inherit"><?= e($d['prize_description']) ?></textarea><p class="help">One item per line. Shown on the competition page, the winner announcement and the live draw screen.</p></div>
        <button class="btn btn-primary" type="submit">Save</button>
      </form>
    </details>
    <?php endif; ?>
  </div>
<?php endforeach; ?>
</div>

<script>
document.querySelectorAll('[data-publish]').forEach(function (b) {
  b.addEventListener('click', function () {
    var fd = new FormData();
    fd.append('action', 'publish'); fd.append('draw_id', b.dataset.publish); fd.append('publish', b.dataset.value);
    fd.append('csrf', <?= json_encode(csrf_token()) ?>);
    fetch('draw-action.php', { method: 'POST', body: fd }).then(function (r) { return r.json(); }).then(function (res) {
      if (res.ok) location.reload(); else alert(res.error);
    });
  });
});
</script>
<?php admin_foot(); ?>
