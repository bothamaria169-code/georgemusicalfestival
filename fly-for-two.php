<?php
require __DIR__ . '/includes/bootstrap.php';

// Attribution: ?src=qr_ground&pt=...&town=...&slot=...  and  ?ref=CODE
capture_source();
if (!empty($_GET['ref']) && ($r = entrant_by_ref((string) $_GET['ref']))) {
    record_referral_click($r);
    $_SESSION['ref_by'] = $r['ref_code'];
}
$src      = source_data();
$refCode  = $_SESSION['ref_by'] ?? '';
$referrer = $refCode ? entrant_by_ref($refCode) : null;
$me       = current_entrant();
$state    = comp_state();
$final    = draw_by_key('final');
$eventDay = draw_by_key('event_day');
$prizes   = prize_lines($final);
$towns    = lines((string) setting('towns'));
$flyOpts  = lines((string) setting('travel_options'));
$leaders  = referral_leaderboard(10);
$shareCap = (int) setting('share_bonus_cap');
$winners  = q("SELECT d.title, d.prize_description, p.full_name, p.town
               FROM draws d
               JOIN draw_runs r ON r.draw_id = d.id AND r.outcome = 'confirmed'
               JOIN entrants p ON p.id = r.selected_entrant_id
               WHERE d.status = 'published' ORDER BY d.scheduled_for")->fetchAll();

$page_title       = setting('comp_name') . ' — Win ' . setting('prize_headline') . ' | GMF × ACSA';
$meta_description = 'Enter FLY FOR TWO free and win ' . setting('prize_headline') . ' — return flights, accommodation and shuttle transfers. George Music Festival × Airports Company South Africa.';
$og_image         = url('assets/img/gallery/airport-terminal.jpg');
$nav_active       = 'fly';
$extra_css        = ['assets/css/comp.css'];
$extra_js         = ['assets/js/enter.js'];
$needs_turnstile  = true;
require __DIR__ . '/includes/header.php';
?>

<section class="ff-hero grain">
  <img class="ff-hero-bg" src="<?= e(url('assets/img/gallery/airport-terminal.jpg')) ?>" alt="" fetchpriority="high">
  <div class="wrap ff-layout">

    <div class="ff-intro">
      <div class="ff-badge">George Music Festival <b>× ACSA</b></div>
      <h1 class="display huge">Win a weekend <span class="serif-i">away.</span></h1>
      <p class="lede">Enter <?= e(setting('comp_name')) ?> before <?= e(setting('event_name')) ?> — it takes less than a minute, and it's free.</p>
      <?php if ($referrer && (!$me || (int) $me['id'] !== (int) $referrer['id'])): ?>
        <p class="ff-invited"><i class="fa-solid fa-user-plus"></i> <?= e(short_name($referrer['full_name'])) ?> invited you to enter.</p>
      <?php endif; ?>
    </div>

    <!-- ── Entry card ───────────────────────────── -->
    <div class="ff-card-col">
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
          <?php include __DIR__ . '/includes/boarding-pass.php'; ?>
          <a class="ff-btn ff-btn-primary" href="<?= e(url('flyfortwo/dashboard')) ?>">Open my dashboard</a>
          <a class="ff-btn ff-btn-ghost" href="<?= e(url('flyfortwo/logout')) ?>">Not you? Sign out</a>
        </div>

      <?php else: ?>
        <?php include __DIR__ . '/includes/entry-flow.php'; ?>
      <?php endif; ?>
      </div>
    </div>

    <!-- ── Prize ───────────────────────────── -->
    <div class="ff-details">
      <div class="ff-prize">
        <div class="ff-prize-label">What you win</div>
        <div class="ff-prize-title"><?= e(setting('prize_headline')) ?></div>
        <ul>
          <?php foreach ($prizes as $p): ?><li><span class="dot"></span><?= e($p) ?></li><?php endforeach; ?>
        </ul>
        <i class="fa-solid fa-plane ff-prize-plane" aria-hidden="true"></i>
      </div>
      <div class="draw-dates">
        <div class="draw-date"><small>Event-Day Draw</small><strong><?= e(fmt_date($eventDay['scheduled_for'] ?? null, 'j M')) ?></strong><span><?= e(prize_lines($eventDay)[0] ?? 'Prize to be announced') ?></span></div>
        <div class="draw-date"><small>Final Draw</small><strong><?= e(fmt_date($final['scheduled_for'] ?? null, 'j M')) ?></strong><span>Entries close <?= e(fmt_date(setting('comp_close_at'), 'j M')) ?></span></div>
      </div>
    </div>
  </div>
</section>

<!-- ═══════════════ HOW IT WORKS ═══════════════ -->
<section class="section on-paper">
  <div class="wrap">
    <span class="kicker reveal" style="color:var(--orange-deep)">How it works</span>
    <h2 class="display big reveal" style="margin:18px 0 50px;">Three steps to <span class="serif-i" style="color:var(--orange-deep)">take-off</span></h2>
    <div class="ff-steps">
      <div class="ff-step reveal"><span class="n">01</span><h3>Enter</h3><p>Your name, cellphone, email and one quick travel question. Scan a GMF QR code or enter right here.</p></div>
      <div class="ff-step reveal reveal-d1"><span class="n">02</span><h3>Verify</h3><p>We email you a 6-digit code. Your entry only counts once it's confirmed — one per phone number.</p></div>
      <div class="ff-step reveal reveal-d2"><span class="n">03</span><h3>Share</h3><p>Send your personal link on WhatsApp. <?= $shareCap > 0 ? 'The first friend who opens it earns you a bonus entry.' : 'Track every friend who joins on your dashboard.' ?> Climb the leaderboard.</p></div>
    </div>
  </div>
</section>

<!-- ═══════════════ LEADERBOARD ═══════════════ -->
<section class="section" id="leaderboard">
  <div class="wrap ff-lead-grid">
    <div>
      <span class="kicker reveal">Leaderboard</span>
      <h2 class="display big reveal" style="margin-top:18px;">Top <span class="serif-i text-orange">ambassadors</span></h2>
      <p class="muted reveal" style="margin-top:18px;max-width:440px;"><?= e(setting('referral_reward_text')) ?></p>
      <?php if ($me): ?><a class="btn btn-orange reveal" style="margin-top:22px" href="<?= e(url('flyfortwo/dashboard')) ?>">Get my link</a><?php endif; ?>
    </div>
    <div class="reveal reveal-d2">
      <?php if ($leaders): ?>
        <ol class="ff-leaders">
          <?php foreach ($leaders as $i => $l): ?>
            <li><span class="rank"><?= $i + 1 ?></span><span class="who"><?= e(short_name($l['full_name'])) ?><?php if ($l['town']): ?><small><?= e($l['town']) ?></small><?php endif; ?></span><span class="score"><?= (int) $l['referrals'] ?> <small>friend<?= (int) $l['referrals'] === 1 ? '' : 's' ?></small></span></li>
          <?php endforeach; ?>
        </ol>
      <?php else: ?>
        <div class="ff-leaders-empty"><i class="fa-solid fa-trophy"></i><p>The board is empty — be the first name on it. Enter, share your link, and watch your friends roll in.</p></div>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- ═══════════════ PHOTO STRIP ═══════════════ -->
<section class="ff-strip" aria-hidden="true">
  <?php foreach (['activation-22', 'activation-02', 'crowd-night', 'activation-24', 'activation-16', 'performer-stage'] as $img): ?>
    <img src="<?= e(url('assets/img/gallery/' . $img . '.jpg')) ?>" alt="" loading="lazy">
  <?php endforeach; ?>
</section>

<!-- ═══════════════ FAQ ═══════════════ -->
<section class="section on-paper">
  <div class="wrap ff-faq-grid">
    <div>
      <span class="kicker reveal" style="color:var(--orange-deep)">Questions</span>
      <h2 class="display big reveal" style="margin-top:18px;">Good to <span class="serif-i" style="color:var(--orange-deep)">know</span></h2>
    </div>
    <div class="ff-faq reveal">
      <details open><summary>Who can enter?</summary><p>South African residents aged 18 and over. Entry is free. Full rules are in the <a href="<?= e(url('flyfortwo/terms')) ?>">competition terms</a>.</p></details>
      <details><summary>How many times can I enter?</summary><p>Once per phone number and email address. <?php if ($shareCap > 0): ?>You can earn <?= $shareCap === 1 ? 'one bonus entry' : $shareCap . ' bonus entries' ?> when friends open your personal share link.<?php endif; ?></p></details>
      <details><summary>Why do I need a verification code?</summary><p>The code confirms that your email is real and that the entry is yours. Unverified entries are not included in the draw.</p></details>
      <details><summary>When are the draws?</summary><p>The Event-Day Draw happens live on <?= e(fmt_date($eventDay['scheduled_for'] ?? null)) ?> at <?= e(setting('event_venue')) ?>. The Final Draw for <?= e(setting('prize_headline')) ?> takes place on <?= e(fmt_date($final['scheduled_for'] ?? null)) ?>.</p></details>
      <details><summary>How is the winner chosen?</summary><p><?= e(setting('draw_reassurance')) ?></p></details>
      <details><summary>What happens to my information?</summary><p>Your details are used to run the competition and are owned by Airports Company South Africa. You'll only get marketing messages if you ticked the optional box. See the <a href="<?= e(url('flyfortwo/privacy')) ?>">privacy notice</a>.</p></details>
    </div>
  </div>
</section>

<script>window.FF = { api: <?= json_encode(url('api/entry.php')) ?>, cooldown: <?= (int) setting('otp_cooldown') ?> };</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
