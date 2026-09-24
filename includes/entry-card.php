<?php
/**
 * The competition entry card in every state: upcoming (countdown + notify), closed (winners),
 * signed-in (boarding pass) or open (the 4-step entry flow). Used on the homepage and /flyfortwo/.
 * Uses the page's variables when set, otherwise works them out.
 */
$me       = $me ?? current_entrant();
$state    = $state ?? comp_state();
$src      = $src ?? source_data();
$refCode  = $refCode ?? ($_SESSION['ref_by'] ?? '');
$towns    = $towns ?? lines((string) setting('towns'));
$flyOpts  = $flyOpts ?? lines((string) setting('travel_options'));
$shareCap = $shareCap ?? (int) setting('share_bonus_cap');
$winners  = $winners ?? ($state === 'closed' ? q("SELECT d.title, p.full_name, p.town FROM draws d
               JOIN draw_runs r ON r.draw_id = d.id AND r.outcome = 'confirmed'
               JOIN entrants p ON p.id = r.selected_entrant_id
               WHERE d.status = 'published' ORDER BY d.scheduled_for")->fetchAll() : []);
?>
<div class="ff-card" id="entryCard">
<?php if ($state === 'upcoming'): ?>
  <div class="ff-body ff-center">
    <div class="ff-icon"><i class="fa-solid fa-plane-departure"></i></div>
    <h2 class="ff-h">Boarding soon</h2>
    <p class="ff-sub">Entries open <?= e(fmt_date(setting('comp_open_at'), 'l j F, H:i')) ?>.</p>
    <div class="countdown ff-countdown" data-countdown="<?= e(date('c', strtotime((string) setting('comp_open_at')))) ?>">
      <div><strong data-d>0</strong><span>Days</span></div><div><strong data-h>00</strong><span>Hrs</span></div><div><strong data-m>00</strong><span>Min</span></div><div><strong data-s>00</strong><span>Sec</span></div>
    </div>
    <form action="<?= e(url('api/subscribe.php')) ?>" method="post" data-ajax style="margin-top:22px;text-align:left">
      <input type="hidden" name="timer" value="<?= e(form_timer()) ?>"><input type="hidden" name="source" value="fly-notify">
      <div class="hp" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
      <div class="field"><label for="n-email">Email me when it opens</label><input id="n-email" type="email" name="email" required placeholder="you@example.com"></div>
      <label class="check"><input type="checkbox" name="consent" value="1" required> Send me GMF news and the competition launch alert.</label>
      <button class="ff-btn ff-btn-primary" type="submit">Notify me</button>
    </form>
  </div>

<?php elseif ($state === 'closed'): ?>
  <div class="ff-body ff-center">
    <div class="ff-icon"><i class="fa-solid fa-plane-arrival"></i></div>
    <h2 class="ff-h">This flight has landed</h2>
    <p class="ff-sub">Entries closed on <?= e(fmt_date(setting('comp_close_at'), 'j F Y')) ?>. Thank you to everyone who entered.</p>
    <?php foreach ($winners as $w): ?>
      <div class="ff-winner"><small><?= e($w['title']) ?> winner</small><strong><?= e(short_name($w['full_name'])) ?></strong><?php if ($w['town']): ?><span><?= e($w['town']) ?></span><?php endif; ?></div>
    <?php endforeach; ?>
    <?php if ($me): ?><a class="ff-btn ff-btn-outline" href="<?= e(url('flyfortwo/dashboard')) ?>">My dashboard</a><?php endif; ?>
  </div>

<?php elseif ($me): ?>
  <?php $count = entry_count((int) $me['id']); ?>
  <div class="ff-body">
    <div class="ff-center"><div class="ff-check"><i class="fa-solid fa-check"></i></div><h2 class="ff-h">You're in the draw, <?= e(explode(' ', $me['full_name'])[0]) ?>.</h2></div>
    <?php include __DIR__ . '/boarding-pass.php'; ?>
    <a class="ff-btn ff-btn-primary" href="<?= e(url('flyfortwo/dashboard')) ?>">Open my dashboard</a>
    <a class="ff-btn ff-btn-ghost" href="<?= e(url('flyfortwo/logout')) ?>">Not you? Sign out</a>
  </div>

<?php else: ?>
  <?php include __DIR__ . '/entry-flow.php'; ?>
<?php endif; ?>
</div>
<script>window.FF = { api: <?= json_encode(url('api/entry.php')) ?>, cooldown: <?= (int) setting('otp_cooldown') ?>, login: <?= json_encode(url('flyfortwo/login')) ?> };</script>
