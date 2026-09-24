<?php
/** Step-by-step guide to entering FLY FOR TWO: /flyfortwo/how-to-enter */
require __DIR__ . '/includes/bootstrap.php';

$final     = draw_by_key('final');
$eventDay  = draw_by_key('event_day');
$shareCap  = (int) setting('share_bonus_cap');
$ttl       = (int) setting('otp_ttl_minutes');
$cooldown  = (int) setting('otp_cooldown');
$state     = comp_state();
$enterUrl  = url('flyfortwo/');

$page_title       = 'How to enter FLY FOR TWO — win a weekend getaway for two';
$meta_description = 'Four steps and about a minute: how to enter FLY FOR TWO, confirm your entry by email, earn a bonus entry by sharing, and how the winners are drawn.';
$og_image         = url('assets/img/competition/hero-win-a-trip-for-two.jpg');
$nav_active       = 'howto';
$extra_css        = ['assets/css/comp.css'];
$show_logo_ticker = true;
require __DIR__ . '/includes/header.php';
?>

<section class="win-hero grain">
  <div class="wrap win-hero-grid">
    <div class="win-hero-text">
      <span class="win-kicker"><i class="fa-solid fa-circle-info"></i> FLY FOR TWO · How to enter</span>
      <h1 class="display huge">How to <span class="serif-i">enter.</span></h1>
      <p class="lede">Four steps, about a minute, and it's free. Here's exactly what happens — from the form to the draw.</p>
      <div class="cover-actions">
        <a class="btn btn-orange btn-lg" href="<?= e($enterUrl) ?>"><i class="fa-solid fa-plane-departure"></i> <?= $state === 'open' ? 'Enter now' : 'Go to the competition' ?></a>
        <a class="btn btn-ghost btn-lg" href="<?= e(url('flyfortwo/login')) ?>">Already entered? Sign in</a>
      </div>
    </div>
    <figure class="win-hero-photo">
      <img src="<?= e(url('assets/img/competition/hero-win-a-trip-for-two.jpg')) ?>" width="1536" height="1024" alt="A couple on a plane holding their boarding passes" fetchpriority="high">
      <figcaption><span class="win-stamp">Free<br>to enter</span></figcaption>
    </figure>
  </div>
</section>

<!-- ═══════════════ THE FOUR STEPS ═══════════════ -->
<section class="section on-paper">
  <div class="wrap">
    <span class="kicker reveal" style="color:var(--orange-deep)">Step by step</span>
    <h2 class="display big reveal" style="margin:16px 0 44px;color:var(--text-dark)">Four steps to <span class="serif-i" style="color:var(--orange-deep)">take-off</span></h2>

    <div class="howto-steps">
      <article class="howto-step reveal">
        <span class="howto-n">1</span>
        <div>
          <h3>Open the entry form</h3>
          <p>Use whichever is easiest:</p>
          <ul>
            <li>The form at the top of <a href="<?= e(url('index.php#enter')) ?>">georgemusicalfestival.co.za</a> or on the <a href="<?= e($enterUrl) ?>">competition page</a></li>
            <li>Scan a FLY FOR TWO QR code on a GMF flyer, poster or event screen</li>
            <li>A friend's personal share link</li>
          </ul>
        </div>
      </article>
      <article class="howto-step reveal">
        <span class="howto-n">2</span>
        <div>
          <h3>Fill in your details</h3>
          <p>Your full name, cellphone number, email address, town and one quick question about flying. Tick the box to accept the <a href="<?= e(url('flyfortwo/terms')) ?>">competition terms</a> — it's required. The box for news and travel offers is optional and separate.</p>
          <p class="howto-note"><i class="fa-solid fa-circle-exclamation"></i> Use an email address you can open right now — that's where your confirmation goes.</p>
        </div>
      </article>
      <article class="howto-step reveal">
        <span class="howto-n">3</span>
        <div>
          <h3>Confirm your entry by email</h3>
          <p>We email you straight away. Either tap <strong>Confirm my entry</strong> in the email, or type the 6-digit code into the form. <strong>Your entry only counts once it's confirmed.</strong></p>
          <ul>
            <li>It's fine to leave the page to check your email — when you come back, the form opens on the code step.</li>
            <li>The code and button work for <?= $ttl ?> minutes. No email after a minute? Check spam, junk or promotions, then tap <strong>Resend code</strong> (available after <?= $cooldown ?> seconds).</li>
          </ul>
        </div>
      </article>
      <article class="howto-step reveal">
        <span class="howto-n">4</span>
        <div>
          <h3>Share for a bonus entry</h3>
          <p>You get your own personal link. Send it on WhatsApp, Facebook, SMS or anywhere else.<?php if ($shareCap > 0): ?> <strong>The first friend who opens it earns you <?= $shareCap === 1 ? 'a bonus entry' : $shareCap . ' bonus entries' ?></strong> — no extra steps. Opening your own link doesn't count.<?php endif; ?> Prefer not to share? Tap "Skip" — your entry still counts.</p>
        </div>
      </article>
    </div>
  </div>
</section>

<!-- ═══════════════ DASHBOARD ═══════════════ -->
<section class="section acsa-feature">
  <div class="wrap acsa-grid">
    <div>
      <span class="kicker reveal">After you enter</span>
      <h2 class="display big reveal" style="margin-top:16px;">Your own <span class="serif-i text-orange">dashboard.</span></h2>
      <p class="lede reveal" style="margin-top:18px;">Every entrant gets a dashboard to spread the word and track how it's going.</p>
      <ul class="prize-list reveal">
        <li><i class="fa-solid fa-ticket"></i>Your entries in the draw</li>
        <li><i class="fa-solid fa-link"></i>Your personal link, share buttons and a QR code to show friends</li>
        <li><i class="fa-regular fa-comment"></i>Ready-made messages to copy and paste</li>
        <li><i class="fa-solid fa-trophy"></i>Friends you brought in and the leaderboard</li>
      </ul>
      <a class="btn btn-ghost reveal" href="<?= e(url('flyfortwo/login')) ?>">Sign in to my dashboard</a>
      <p class="muted reveal" style="font-size:.88rem;margin-top:14px;">No password needed — sign in with your email and a one-time code.</p>
    </div>
    <div class="acsa-collage reveal reveal-d2">
      <img class="c-a" src="<?= e(url('assets/img/acsa/plane-on-apron.jpg')) ?>" alt="A plane at the gate" loading="lazy">
      <img class="c-b" src="<?= e(url('assets/img/acsa/moyeni-with-young-fan.jpg')) ?>" alt="A young fan with Moyeni, the ACSA mascot" loading="lazy">
      <img class="c-c" src="<?= e(url('assets/img/acsa/acsa-team-terminal.jpg')) ?>" alt="The ACSA airport team" loading="lazy">
    </div>
  </div>
</section>

<!-- ═══════════════ DRAWS ═══════════════ -->
<section class="section">
  <div class="wrap">
    <span class="kicker reveal">The draws</span>
    <h2 class="display big reveal" style="margin:16px 0 30px;">Two chances to <span class="serif-i text-orange">win.</span></h2>
    <div class="draw-dates howto-draws reveal">
      <div class="draw-date"><small>Event-Day Draw · live at <?= e(setting('event_venue')) ?></small><strong><?= e(fmt_date($eventDay['scheduled_for'] ?? null, 'j F Y')) ?></strong><span><?= e(implode(' · ', prize_lines($eventDay)) ?: 'Prize to be announced') ?></span></div>
      <div class="draw-date"><small>Final Draw · entries close <?= e(fmt_date(setting('comp_close_at'), 'j F, H:i')) ?></small><strong><?= e(fmt_date($final['scheduled_for'] ?? null, 'j F Y')) ?></strong><span><?= e(setting('prize_headline')) ?>: <?= e(implode(', ', prize_lines($final))) ?></span></div>
    </div>
    <p class="muted reveal" style="max-width:760px;margin-top:22px;"><?= e(setting('draw_reassurance')) ?> Event-Day winners stay in the running for the Final Draw.</p>
  </div>
</section>

<!-- ═══════════════ RULES + FAQ ═══════════════ -->
<section class="section on-paper">
  <div class="wrap ff-faq-grid">
    <div>
      <span class="kicker reveal" style="color:var(--orange-deep)">Good to know</span>
      <h2 class="display big reveal" style="margin-top:16px;color:var(--text-dark)">The <span class="serif-i" style="color:var(--orange-deep)">rules</span> in short</h2>
      <ul class="howto-rules reveal">
        <li>Free to enter. South African residents aged 18 and over.</li>
        <li>One entry per cellphone number and email address.</li>
        <li>Only confirmed entries go into the draw.</li>
        <?php if ($shareCap > 0): ?><li>Maximum <?= $shareCap ?> share bonus entr<?= $shareCap === 1 ? 'y' : 'ies' ?> per person.</li><?php endif; ?>
        <li>Full details: <a href="<?= e(url('flyfortwo/terms')) ?>">competition terms</a> · <a href="<?= e(url('flyfortwo/privacy')) ?>">privacy notice</a></li>
      </ul>
    </div>
    <div class="ff-faq reveal">
      <details open><summary>I didn't get the email</summary><p>Wait a minute, then check your spam, junk and promotions folders. Still nothing? Go back to the form — it remembers where you were — check the email address is right ("Wrong email? Edit details"), then tap Resend code.</p></details>
      <details><summary>It says I've already entered</summary><p>That phone number or email address is already in the draw. <a href="<?= e(url('flyfortwo/login')) ?>">Sign in</a> to see your entries and your share link.</p></details>
      <details><summary>Does sharing my link cost anything?</summary><p>No. Sharing is free and optional. <?= $shareCap > 0 ? 'The first friend who opens your link earns you a bonus entry automatically.' : '' ?></p></details>
      <details><summary>How is the winner chosen?</summary><p><?= e(setting('draw_reassurance')) ?></p></details>
      <details><summary>What happens to my information?</summary><p>It's used to run the competition and is owned by Airports Company South Africa. You'll only get news and offers if you ticked the optional box, and you can switch that off on your dashboard. See the <a href="<?= e(url('flyfortwo/privacy')) ?>">privacy notice</a>.</p></details>
    </div>
  </div>
</section>

<section class="section-sm win-hero">
  <div class="wrap" style="text-align:center">
    <h2 class="display big reveal">Ready? <span class="serif-i text-orange">Let's fly.</span></h2>
    <p class="reveal" style="margin-top:22px;"><a class="btn btn-orange btn-lg" href="<?= e($enterUrl) ?>"><i class="fa-solid fa-plane-departure"></i> Enter FLY FOR TWO</a></p>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
