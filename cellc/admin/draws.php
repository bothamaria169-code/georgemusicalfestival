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

$draws = q("SELECT * FROM draws ORDER BY FIELD(draw_key, 'final', 'event_day'), id")->fetchAll();
$board = leaderboard_scores(null, 25);
$people = (int) q("SELECT COUNT(*) FROM entrants WHERE status = 'verified'")->fetchColumn();

admin_head('Draws', 'draws');
?>
<div class="card" style="margin-bottom:16px;">
  <h2>Live leaderboard — admins only <span class="muted" style="letter-spacing:0;text-transform:none;font-weight:600"><?= number_format($people) ?> verified entrants · the public sees the top 10 with #1 hidden</span></h2>
  <p class="muted" style="margin-top:0">Score = link opens: unique visitors who opened the entrant's personal link while entries were open (own devices never count; one network adds at most 3 opens per person per day). Ties go to more friends entered, then whoever got there first. 1st place wins the Grand Prize, the next eligible person the Second Prize.</p>
  <div class="t-wrap"><table class="t">
    <thead><tr><th>#</th><th>Entrant</th><th>Contact</th><th class="num">Link opens</th><th class="num">Networks</th><th class="num">Friends entered</th><th>Last open</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($board as $i => $r):
        $suspicious = (int) $r['clicks'] >= 10 && (int) $r['networks'] / max(1, (int) $r['clicks']) < 0.3; ?>
      <tr>
        <td><strong style="font-family:var(--f-d);font-size:1.2rem;<?= $i < 2 ? 'color:var(--orange)' : '' ?>"><?= $i + 1 ?></strong></td>
        <td style="display:flex;gap:10px;align-items:center"><?php if ($r['photo']): ?><img src="<?= e(media_url($r['photo'])) ?>" alt="" style="width:34px;height:34px;border-radius:50%;object-fit:cover"><?php endif; ?><span><strong><?= e($r['full_name']) ?></strong><br><small class="muted"><?= e($r['town'] ?: '—') ?> · <?= e($r['ref_code']) ?></small></span></td>
        <td><?= e($r['email'] ?: '—') ?><br><small class="muted"><?= e(display_phone($r['phone'])) ?></small></td>
        <td class="num"><strong><?= (int) $r['clicks'] ?></strong></td>
        <td class="num"><?= (int) $r['networks'] ?></td>
        <td class="num"><?= (int) $r['referrals'] ?></td>
        <td class="muted"><?= e(fmt_date($r['last_click'], 'j M H:i')) ?></td>
        <td><?php if ($suspicious): ?><span class="pill pill-warn" title="Most opens came from very few networks — check before confirming as a winner">Check</span><?php endif; ?><?php if (!$r['photo']): ?> <span class="pill pill-muted" title="No photo yet — upload one under Entrants">No photo</span><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$board): ?><tr><td colspan="8" class="muted">No link opens yet. The leaderboard fills as entrants share their links.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>

<div class="grid g-2">
<?php foreach ($draws as $d): $runs = draw_runs((int) $d['id']); $last = end($runs) ?: null; ?>
  <div class="card">
    <h2><?= e($d['title']) ?>
      <span class="pill <?= ['scheduled' => 'pill-muted', 'drawn' => 'pill-warn', 'confirmed' => 'pill-ok', 'published' => 'pill-info'][$d['status']] ?>"><?= e($d['status']) ?></span>
    </h2>
    <p style="margin:0 0 12px"><span class="pill pill-info"><?= $d['draw_key'] === 'final' ? '1st place on the leaderboard' : '2nd place (next eligible after the Grand Prize winner)' ?></span><br><strong><?= e(fmt_date($d['scheduled_for'], 'l, j F Y')) ?></strong><br><span class="muted"><?= nl2br(e($d['prize_description'] ?: 'Prize to be confirmed')) ?></span></p>

    <?php if ($last): ?>
      <div class="winner-box" style="margin-bottom:12px">
        <small class="muted">Run <?= (int) $last['run_no'] ?><?= $last['is_redraw'] ? ' (redraw)' : '' ?> · <?= e(fmt_date($last['created_at'], 'j M Y H:i:s')) ?></small>
        <strong><?= e($last['full_name']) ?></strong>
        <span><?= e(display_phone($last['phone'])) ?> · <?= e($last['email'] ?: 'no email (USSD)') ?> · <?= e($last['town'] ?: '—') ?></span><br>
        <span class="pill <?= $last['outcome'] === 'confirmed' ? 'pill-ok' : ($last['outcome'] === 'disqualified' ? 'pill-bad' : 'pill-warn') ?>"><?= $last['outcome'] === 'selected' ? 'Awaiting verification' : e($last['outcome']) ?></span>
      </div>
    <?php endif; ?>

    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px">
      <?php if ($isAdmin): ?><a class="btn btn-primary" href="draw-live.php?draw=<?= (int) $d['id'] ?>" target="_blank"><i class="fa-solid fa-display"></i> Live reveal screen</a><?php endif; ?>
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
        <thead><tr><th>Run</th><th>Time</th><th class="num">Place · opens</th><th>Selected</th><th>Outcome</th></tr></thead>
        <tbody>
        <?php foreach ($runs as $r): ?>
          <tr>
            <td><?= (int) $r['run_no'] ?><?= $r['is_redraw'] ? ' <span class="pill pill-warn">redraw</span>' : '' ?></td>
            <td class="muted"><?= e(fmt_date($r['created_at'], 'j M H:i:s')) ?></td>
            <td class="num">#<?= (int) $r['selected_index'] + 1 ?> · <?= (int) $r['selected_entry_id'] ?></td>
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
