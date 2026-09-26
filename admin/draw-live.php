<?php
/** Full-screen live draw for the event-day audience and the ACSA witness. */
require __DIR__ . '/_layout.php';
require dirname(__DIR__) . '/includes/draw.php';

$admin = require_admin('admin');
$draw = q('SELECT * FROM draws WHERE id = ?', [(int) ($_GET['draw'] ?? 0)])->fetch();
if (!$draw) {
    exit('Draw not found.');
}
$runs = draw_runs((int) $draw['id']);
$last = end($runs) ?: null;
$boardNow = leaderboard_scores();
$pool = (int) array_sum(array_column($boardNow, 'clicks'));   // total link opens
$people = count($boardNow);                                    // entrants on the leaderboard
$lastPhoto = $last && $last['outcome'] !== 'disqualified' ? q('SELECT photo FROM entrants WHERE id = ?', [$last['selected_entrant_id']])->fetchColumn() : null;
$prize = prize_lines($draw);
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title><?= e($draw['title']) ?> — live draw</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@1,9..144,400&family=Manrope:wght@500;700;800&family=Oswald:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
  :root { --o:#f7801a; --o2:#ff9d4a; --ink:#02262f; --paper:#f6f1e8; --muted:rgba(246,241,232,.6); }
  * { box-sizing: border-box; }
  html, body { margin: 0; height: 100%; }
  body { background: radial-gradient(ellipse 70% 60% at 50% 110%, rgba(247,128,26,.28), transparent 70%), radial-gradient(ellipse 60% 50% at 90% 0%, rgba(42,163,187,.3), transparent 70%), linear-gradient(160deg,#006780,#02262f); color: var(--paper); font-family: Manrope, sans-serif; display: flex; flex-direction: column; overflow: hidden; }
  header { display: flex; justify-content: space-between; align-items: center; padding: 28px 44px; }
  header img { height: 64px; width: auto; }
  header .x { font-family: Oswald; font-size: 2rem; color: var(--muted); }
  main { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 0 30px; }
  .kicker { font-weight: 800; letter-spacing: .3em; text-transform: uppercase; color: var(--o); font-size: clamp(.8rem, 1.2vw, 1.1rem); }
  h1 { font-family: Oswald; text-transform: uppercase; font-size: clamp(3rem, 8vw, 8rem); line-height: .9; margin: 14px 0 8px; font-weight: 700; }
  h1 em { font-family: Fraunces; font-weight: 400; text-transform: none; color: var(--o); }
  .prize { color: var(--muted); font-size: clamp(1rem, 1.6vw, 1.5rem); margin: 0 0 30px; }
  .slot { min-height: 1.1em; font-family: Oswald; font-weight: 700; text-transform: uppercase; font-size: clamp(3.4rem, 11vw, 11rem); line-height: 1; letter-spacing: -.01em; margin: 10px 0; }
  .slot.spin { color: rgba(246,241,232,.55); filter: blur(.5px); }
  .slot.win { color: var(--o); animation: pop .7s cubic-bezier(.2,.8,.2,1.2); text-shadow: 0 0 60px rgba(247,128,26,.45); }
  @keyframes pop { from { transform: scale(.6); opacity: 0; } }
  .meta { font-size: clamp(1rem, 1.8vw, 1.7rem); color: var(--muted); min-height: 1.6em; }
  .stats { display: flex; gap: 50px; justify-content: center; margin-top: 36px; }
  .stats div { font-size: .8rem; font-weight: 800; letter-spacing: .2em; text-transform: uppercase; color: var(--muted); }
  .stats strong { display: block; font-family: Oswald; font-size: 3rem; color: var(--paper); letter-spacing: 0; font-weight: 600; }
  .controls { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; margin-top: 30px; align-items: center; }
  .controls input { padding: 14px 16px; border-radius: 12px; border: 1px solid rgba(255,255,255,.2); background: rgba(255,255,255,.06); color: #fff; font: inherit; min-width: 280px; }
  button { font: inherit; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; padding: 16px 26px; border-radius: 999px; border: 1px solid rgba(255,255,255,.25); background: rgba(255,255,255,.06); color: #fff; cursor: pointer; }
  button.go { background: var(--o); color: #1d1204; border-color: var(--o); font-size: 1.1rem; padding: 20px 40px; }
  button[disabled] { opacity: .4; cursor: not-allowed; }
  .audit { position: fixed; left: 44px; right: 44px; bottom: 22px; display: flex; gap: 30px; justify-content: center; flex-wrap: wrap; font-family: ui-monospace, Menlo, monospace; font-size: .74rem; color: rgba(246,241,232,.45); }
  .audit b { color: rgba(246,241,232,.75); font-weight: 600; }
  .hidden { display: none !important; }
  .confetti { position: fixed; top: -20px; width: 10px; height: 16px; z-index: 5; animation: fall linear forwards; }
  @keyframes fall { to { transform: translateY(110vh) rotate(720deg); } }
  .msg { color: #ff9c8a; font-weight: 700; margin-top: 14px; min-height: 1.4em; }
  .photo { width: clamp(150px, 20vw, 260px); height: clamp(150px, 20vw, 260px); border-radius: 50%; object-fit: cover; border: 6px solid var(--o); box-shadow: 0 0 80px rgba(247,128,26,.45); margin: 6px auto 4px; display: block; animation: pop .8s cubic-bezier(.2,.8,.2,1.2); }
  .count { font-family: Oswald; font-size: clamp(6rem, 18vw, 16rem); line-height: 1; color: var(--o); }
  .fs { position: fixed; top: 110px; right: 44px; font-size: .7rem; padding: 10px 14px; }
</style>
</head>
<body>
<header>
  <img src="<?= e(url('assets/img/brand/gmf-logo-orange.png')) ?>" alt="George Musical Festival">
  <span class="x">×</span>
  <img src="<?= e(url('assets/img/brand/acsa-white.png')) ?>" alt="Airports Company South Africa">
</header>
<button class="fs" onclick="document.documentElement.requestFullscreen && document.documentElement.requestFullscreen()"><i class="fa-solid fa-expand"></i> Full screen</button>
<main>
  <div class="kicker"><?= e(setting('comp_name')) ?> · Live draw</div>
  <h1><?= e($draw['title']) ?></h1>
  <p class="prize"><?= e(implode(' · ', $prize) ?: 'Prize to be announced') ?></p>

  <img class="photo <?= $lastPhoto ? '' : 'hidden' ?>" id="photo" src="<?= $lastPhoto ? e(media_url($lastPhoto)) : '' ?>" alt="">
  <div class="slot" id="slot"><?= $last && $last['outcome'] !== 'disqualified' ? e(short_name($last['full_name'])) : '— ? —' ?></div>
  <div class="meta" id="meta"><?= $last && $last['outcome'] !== 'disqualified' ? e(($last['town'] ? $last['town'] . ' · ' : '') . (int) $last['selected_entry_id'] . ' link opens · Place #' . ((int) $last['selected_index'] + 1)) : '' ?></div>

  <div class="stats" id="stats">
    <div>Link opens<strong id="poolEntries"><?= number_format($last ? (int) $last['pool_entries'] : $pool) ?></strong></div>
    <div>On the leaderboard<strong id="poolPeople"><?= number_format($last ? (int) $last['pool_entrants'] : $people) ?></strong></div>
  </div>

  <div class="controls <?= $last && $last['outcome'] !== 'disqualified' ? 'hidden' : '' ?>" id="runControls">
    <input id="witness" placeholder="ACSA witness name (recorded in the log)">
    <button class="go" id="runBtn"><i class="fa-solid fa-trophy"></i> Reveal the winner</button>
  </div>
  <div class="controls <?= $last && $last['outcome'] === 'selected' ? '' : 'hidden' ?>" id="outcomeControls">
    <button id="confirmBtn"><i class="fa-solid fa-circle-check"></i> Winner verified</button>
    <button id="redrawBtn"><i class="fa-solid fa-rotate"></i> Winner ineligible — next on the leaderboard</button>
  </div>
  <div class="msg" id="msg"></div>
</main>
<div class="audit" id="audit">
  <?php if ($last): ?>
    <span>Run <b><?= (int) $last['run_no'] ?></b></span><span>Time <b><?= e($last['created_at']) ?></b></span><span>Leaderboard snapshot <b><?= e(substr($last['pool_hash'], 0, 16)) ?>…</b></span><span>Place <b>#<?= (int) $last['selected_index'] + 1 ?></b></span>
  <?php else: ?>
    <span>Winner = most link opens at the cut-off · every reveal is logged with a snapshot of the leaderboard</span>
  <?php endif; ?>
</div>

<script>
(function () {
  var CSRF = <?= json_encode(csrf_token()) ?>, DRAW = <?= (int) $draw['id'] ?>;
  var runId = <?= $last ? (int) $last['id'] : 'null' ?>;
  var photo = document.getElementById('photo');
  var slot = document.getElementById('slot'), meta = document.getElementById('meta'), msg = document.getElementById('msg');
  var runControls = document.getElementById('runControls'), outcomeControls = document.getElementById('outcomeControls');
  <?php if ($last && $last['outcome'] !== 'disqualified'): ?>runControls.classList.add('hidden');<?php endif; ?>

  function post(data) {
    var fd = new FormData(); fd.append('csrf', CSRF);
    Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
    return fetch('draw-action.php', { method: 'POST', body: fd }).then(function (r) { return r.json(); });
  }
  function sleep(ms) { return new Promise(function (r) { setTimeout(r, ms); }); }

  function countdown() {
    slot.className = 'slot'; meta.textContent = ''; photo.classList.add('hidden');
    return new Promise(function (resolve) {
      var n = 3;
      (function step() {
        if (n === 0) return resolve();
        slot.innerHTML = '<span class="count">' + n + '</span>'; n--;
        setTimeout(step, 900);
      })();
    });
  }

  function reveal(res) {
    runId = res.run.id;
    slot.className = 'slot win';
    slot.textContent = res.winner.name;
    if (res.winner.photo) { photo.src = res.winner.photo; photo.classList.remove('hidden'); }
    meta.textContent = [res.winner.town, res.winner.score + ' link opens', 'Place #' + res.winner.rank].filter(Boolean).join(' · ');
    document.getElementById('poolEntries').textContent = res.run.pool_entries.toLocaleString();   // total link opens
    document.getElementById('poolPeople').textContent = res.run.pool_entrants.toLocaleString();
    document.getElementById('audit').innerHTML =
      '<span>Run <b>' + res.run.run_no + (res.run.is_redraw ? ' (redraw)' : '') + '</b></span><span>Time <b>' + res.run.created_at + '</b></span>' +
      '<span>Leaderboard snapshot <b>' + res.run.pool_hash.slice(0, 16) + '…</b></span><span>Place <b>#' + (res.run.index + 1) + '</b></span>';
    confetti();
    runControls.classList.add('hidden');
    outcomeControls.classList.remove('hidden');
  }

  function draw(fn) {
    msg.textContent = '';
    // The winner comes from the frozen leaderboard on the server; the countdown is for the audience.
    return Promise.all([fn(), countdown()]).then(function (all) {
      var res = all[0];
      if (!res.ok) { slot.className = 'slot'; slot.textContent = '— ? —'; meta.textContent = ''; msg.textContent = res.error; return; }
      reveal(res);
    });
  }

  document.getElementById('runBtn').addEventListener('click', function () {
    var b = this; b.disabled = true;
    draw(function () { return post({ action: 'run', draw_id: DRAW, witness: document.getElementById('witness').value }); })
      .finally(function () { b.disabled = false; });
  });
  document.getElementById('confirmBtn').addEventListener('click', function () {
    if (!confirm('Confirm this winner as verified and eligible?')) return;
    post({ action: 'outcome', run_id: runId, outcome: 'confirmed' }).then(function (res) {
      if (!res.ok) { msg.textContent = res.error; return; }
      outcomeControls.classList.add('hidden');
      msg.style.color = '#7ee2a2'; msg.textContent = 'Winner confirmed and logged.';
      confetti();
    });
  });
  document.getElementById('redrawBtn').addEventListener('click', function () {
    var reason = prompt('Why is this winner ineligible? (recorded in the certification log)');
    if (!reason) return;
    outcomeControls.classList.add('hidden');
    draw(function () { return post({ action: 'outcome', run_id: runId, outcome: 'disqualified', note: reason }); });
  });

  function confetti() {
    var colors = ['#ff9a00', '#ffb74a', '#1a8ea6', '#f6f1e8', '#2f8a5b'];
    for (var i = 0; i < 140; i++) {
      var c = document.createElement('i'); c.className = 'confetti';
      c.style.left = Math.random() * 100 + 'vw';
      c.style.background = colors[i % colors.length];
      c.style.animationDuration = (2.5 + Math.random() * 2.5) + 's';
      c.style.animationDelay = Math.random() * .6 + 's';
      c.style.transform = 'rotate(' + Math.random() * 360 + 'deg)';
      document.body.appendChild(c);
      setTimeout(function (el) { el.remove(); }, 6000, c);
    }
  }
})();
</script>
</body>
</html>
