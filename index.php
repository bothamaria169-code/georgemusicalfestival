<?php
require __DIR__ . '/includes/bootstrap.php';

// The homepage carries the entry form, so QR tags and share links work here too.
competition_landing_context();

$eventName  = (string) setting('event_name');
$eventDate  = (string) setting('event_date');
$venue      = (string) setting('event_venue');
$ticketUrl  = (string) setting('ticket_url');
$finalDraw  = draw_by_key('final');
$eventDraw  = draw_by_key('event_day');
$images     = media_items('image', 7);
$videos     = media_items('video', 5);
$artists    = media_items('artist');
$state      = comp_state();
$me         = current_entrant();
$shareCap   = (int) setting('share_bonus_cap');

// "Win a ticket for two" → "Win a ticket for" + italic "two"
$headWords  = preg_split('/\s+/', trim((string) setting('hero_headline'))) ?: [];
$headAccent = count($headWords) > 1 ? array_pop($headWords) : '';

$page_title       = 'Win a Huawei Pura 80 Pro — WIN A HUAWEI · George Musical Festival × Cell C';
$meta_description = (string) setting('hero_text');
$og_image         = url('assets/img/cellc/hero-win-a-huawei.jpg');
$nav_active       = 'home';
$extra_css        = ['assets/css/comp.css'];
$extra_js         = ['assets/js/enter.js'];
$needs_turnstile  = true;
$show_logo_ticker = true;
require __DIR__ . '/includes/header.php';
?>

<!-- ═══════════════ HERO: THE COMPETITION ═══════════════ -->
<section class="win-hero grain">
  <div class="wrap win-hero-grid">
    <div class="win-hero-text">
      <span class="win-kicker"><i class="fa-solid fa-mobile-screen-button"></i> <?= e(setting('hero_kicker')) ?></span>
      <h1 class="display huge"><?= e(implode(' ', $headWords)) ?><?php if ($headAccent): ?> <span class="serif-i"><?= e($headAccent) ?>.</span><?php endif; ?></h1>
      <p class="lede"><?= e(setting('hero_text')) ?></p>
      <div class="cover-actions">
        <a class="btn btn-orange btn-lg" href="#enter"><i class="fa-solid fa-ticket"></i> Enter now — it's free</a>
        <a class="btn btn-ghost btn-lg" href="<?= e(url('win/how-to-enter')) ?>">How to enter</a>
      </div>
      <ul class="win-points">
        <li><i class="fa-solid fa-mobile-screen-button"></i> Brand-new Huawei Pura 80 Pro</li>
        <li><i class="fa-solid fa-tag"></i> Worth <?= e(setting('prize_value')) ?></li>
        <li><i class="fa-solid fa-circle-check"></i> Free to enter</li>
      </ul>
    </div>
    <figure class="win-hero-photo">
      <img src="<?= e(url('assets/img/cellc/hero-win-a-huawei.jpg')) ?>" width="1800" height="1200" alt="Three smiling friends taking a selfie together outdoors" fetchpriority="high">
      <figcaption><span class="win-stamp">Win a Huawei<br>Pura 80 Pro</span></figcaption>
    </figure>
  </div>
</section>

<!-- ═══════════════ THE PRIZE (Cell C) ═══════════════ -->
<section class="moyeni-band">
  <div class="wrap moyeni-grid">
    <figure class="moyeni-render reveal">
      <img src="<?= e(url('assets/img/cellc/prize-phone-side.jpg')) ?>" width="1800" height="1200" alt="A sleek white premium smartphone with a large round camera module" loading="lazy">
    </figure>
    <div class="moyeni-copy">
      <img class="moyeni-logo reveal" src="<?= e(url('assets/img/brand/cellc-orange.png')) ?>" width="684" height="265" alt="Cell C" loading="lazy">
      <span class="kicker reveal" style="color:var(--orange-deep);margin-top:18px;">The prize</span>
      <h2 class="display big reveal" style="margin-top:12px;color:var(--navy-2);">Huawei Pura 80 Pro, <span class="serif-i" style="color:var(--orange-deep)">worth <?= e(setting('prize_value')) ?>.</span></h2>
      <p class="reveal" style="color:var(--text-muted);margin:14px 0 0;">A brand-new flagship smartphone with a pro-grade camera, sponsored by Cell C. Enter <?= e(setting('comp_name')) ?> free, share your personal link, and the entrant whose link is opened by the most people wins it.</p>
      <div class="moyeni-photos reveal">
        <img src="<?= e(url('assets/img/cellc/fans-phones.jpg')) ?>" alt="Two friends laughing at their phones" loading="lazy">
        <img src="<?= e(url('assets/img/cellc/caller-braids.jpg')) ?>" alt="A young woman smiling while talking on her phone" loading="lazy">
        <img src="<?= e(url('assets/img/cellc/man-phone-smile.jpg')) ?>" alt="A man smiling at his phone outdoors" loading="lazy">
      </div>
    </div>
  </div>
</section>

<!-- ═══════════════ TICKER ═══════════════ -->
<div class="marquee" aria-hidden="true">
  <div class="marquee-track">
    <?php for ($i = 0; $i < 2; $i++): ?>
      <span>Win a Huawei Pura 80 Pro</span><span>Worth <?= e(setting('prize_value')) ?></span><?php if (!empty($artists[0]['title'])): ?><span>Headlining: <?= e($artists[0]['title']) ?> + many more</span><?php endif; ?><span>Enter free</span><span>Most link opens wins</span><span>Winners revealed <?= e(fmt_date($finalDraw['scheduled_for'] ?? null, 'j M')) ?></span><span><?= e($eventName) ?></span>
    <?php endfor; ?>
  </div>
</div>

<!-- ═══════════════ ENTER (the form) ═══════════════ -->
<section class="section enter-section" id="enter">
  <div class="wrap enter-grid">
    <div class="enter-copy">
      <span class="kicker reveal"><?= e(setting('comp_name')) ?> · Enter here</span>
      <h2 class="display big reveal" style="margin-top:18px;">Enter in under a <span class="serif-i text-orange">minute.</span></h2>
      <p class="lede reveal" style="margin-top:18px;">Fill in your details and tap the button in the email we send you. Then share your personal link: <strong>the person whose link is opened by the most people wins</strong>, and the runner-up wins the second prize.</p>
      <div class="ff-prize reveal" style="margin-top:26px;">
        <div class="ff-prize-label">What you win</div>
        <div class="ff-prize-title"><?= e(setting('prize_headline')) ?></div>
        <ul>
          <?php foreach (prize_lines($finalDraw) as $p): ?><li><span class="dot"></span><?= e($p) ?></li><?php endforeach; ?>
        </ul>
        <i class="fa-solid fa-mobile-screen-button ff-prize-plane" aria-hidden="true"></i>
      </div>
      <?php $placesClass = 'reveal'; include __DIR__ . '/includes/prize-places.php'; ?>
      <p class="reveal" style="margin-top:22px;display:flex;gap:18px;flex-wrap:wrap;">
        <a class="text-link" href="<?= e(url('win/how-to-enter')) ?>">How to enter, step by step <i class="fa-solid fa-arrow-right"></i></a>
        <a class="text-link" href="<?= e(url('win/')) ?>">Full competition page <i class="fa-solid fa-arrow-right"></i></a>
      </p>
    </div>
    <div class="enter-card reveal">
      <?php include __DIR__ . '/includes/entry-card.php'; ?>
    </div>
  </div>
</section>

<!-- ═══════════════ HOW IT WORKS ═══════════════ -->
<section class="section-sm on-paper">
  <div class="wrap">
    <div class="ff-steps">
      <div class="ff-step reveal"><span class="n">01</span><h3>Enter</h3><p>Name, cellphone, email, age group and one quick question. Free, and one entry per person.</p></div>
      <div class="ff-step reveal reveal-d1"><span class="n">02</span><h3>Confirm</h3><p>Tap <strong>Confirm my entry</strong> in the email we send, or type the 6-digit code. Only confirmed entries go into the draw.</p></div>
      <div class="ff-step reveal reveal-d2"><span class="n">03</span><h3>Share</h3><p>Send your personal link on WhatsApp and everywhere else. Every different person who opens it adds 1 to your score — the top two scores win.</p></div>
    </div>
    <p class="reveal" style="text-align:center;margin:34px 0 0;"><a class="btn btn-outline-dark" href="<?= e(url('win/how-to-enter')) ?>">Read the full how-to-enter guide</a></p>
  </div>
</section>

<!-- ═══════════════ CELL C × George Musical Festival ═══════════════ -->
<section class="section acsa-feature">
  <div class="wrap acsa-grid">
    <div class="acsa-copy">
      <img class="acsa-logo reveal" src="<?= e(url('assets/img/brand/cellc-white.png')) ?>" alt="Cell C" loading="lazy">
      <span class="kicker reveal" style="margin-top:26px;">Cell C × George Musical Festival</span>
      <h2 class="display big reveal" style="margin-top:16px;">Change your <span class="serif-i text-orange">world.</span></h2>
      <p class="lede reveal" style="margin-top:18px;">Music brings the Garden Route together, and your phone keeps you connected to it. Cell C and George Musical Festival are celebrating both, with a brand-new Huawei Pura 80 Pro for one lucky entrant.</p>
      <p class="muted reveal">Enter online or at any George Musical Festival activation point, then share your personal link with friends and family. Every different person who opens it moves you up the <?= e(setting('comp_name')) ?> leaderboard.</p>
    </div>
    <div class="acsa-collage acsa-collage--wide reveal reveal-d2">
      <img class="c-a" src="<?= e(url('assets/img/cellc/friends-phones.jpg')) ?>" alt="Young friends sharing something on their phones" loading="lazy">
      <img class="c-b" src="<?= e(url('assets/img/cellc/selfie-night.jpg')) ?>" alt="Three friends in colourful dresses taking a selfie at night" loading="lazy">
      <img class="c-c" src="<?= e(url('assets/img/cellc/concert-phones.jpg')) ?>" alt="A concert crowd holding up their phones" loading="lazy">
    </div>
  </div>
</section>

<!-- ═══════════════ 01 THE EVENT ═══════════════ -->
<section class="section on-paper" id="event">
  <div class="wrap">
    <div class="spread">
      <div class="spread-text">
        <span class="kicker reveal"><span class="num">01</span> The Event</span>
        <h2 class="display huge reveal" style="margin:22px 0 34px;">Music is the <span class="serif-i text-orange">reason</span> to travel.</h2>
        <p class="lede dropcap reveal"><?= e($eventName) ?> brings soul, jazz and the sound of the Garden Route to <?= e($venue) ?> — a full day of live music, food, culture and community under the George sky.</p>
        <p class="reveal muted">George Musical Festival is a regional platform connecting music, culture, community, lifestyle and tourism. Local talent gives it authenticity; the Garden Route gives it a destination worth the trip. This year, with Cell C, we're keeping everyone connected — from the first flyer on the street to the final note on stage.</p>

        <div class="fact-grid reveal">
          <div><small>Date</small><strong><?= e(fmt_date($eventDate, 'l, j F Y')) ?></strong></div>
          <div><small>Venue</small><strong><?= e($venue) ?></strong></div>
          <div><small>Time</small><strong><?= e(setting('event_time')) ?></strong></div>
          <div><small>Tickets</small><strong><?= e($ticketUrl !== '' ? 'On sale now' : setting('ticket_note')) ?></strong></div>
        </div>
        <?php if ($ticketUrl !== ''): ?>
          <a class="btn btn-orange btn-lg reveal" style="margin-top:26px" href="<?= e($ticketUrl) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-ticket"></i> Buy tickets on Computicket</a>
        <?php endif; ?>

        <div class="reveal" style="margin-top:34px;" data-countdown="<?= e(date('c', strtotime($eventDate . ' 12:00'))) ?>">
          <span class="kicker" style="color:var(--orange-deep)">Countdown</span>
          <div class="countdown" style="margin-top:14px;">
            <div><strong data-d>0</strong><span>Days</span></div>
            <div><strong data-h>00</strong><span>Hours</span></div>
            <div><strong data-m>00</strong><span>Min</span></div>
            <div><strong data-s>00</strong><span>Sec</span></div>
          </div>
        </div>
      </div>
      <div class="spread-media reveal reveal-d2">
        <div class="img-a"><img src="<?= e(url('assets/img/gallery/crowd-night.jpg')) ?>" alt="The crowd at a George Musical Festival night show" loading="lazy"></div>
        <div class="img-b"><img src="<?= e(url('assets/img/gallery/activation-22.jpg')) ?>" alt="The George Musical Festival street team in George" loading="lazy"></div>
      </div>
    </div>
  </div>
</section>


<!-- ═══════════════ 02 THE EXPERIENCE ═══════════════ -->
<section class="section on-ink-2" id="experience">
  <div class="wrap">
    <div class="section-head">
      <div>
        <span class="kicker reveal"><span class="num">02</span> The Experience</span>
        <h2 class="display big reveal">One day. <span class="serif-i text-orange">Four</span> reasons to be there.</h2>
      </div>
      <a class="btn btn-ghost reveal" href="<?= e($ticketUrl !== '' ? $ticketUrl : '#tickets') ?>" <?= $ticketUrl !== '' ? 'target="_blank" rel="noopener"' : '' ?>><i class="fa-solid fa-ticket"></i> Tickets</a>
    </div>
    <div class="exp-grid">
      <article class="exp-card reveal">
        <img src="<?= e(url('assets/img/gallery/performer-stage.jpg')) ?>" alt="A performer on the George Musical Festival stage" loading="lazy">
        <div class="exp-body"><span class="exp-n">01</span><h3>Live soul &amp; jazz</h3><p>A full day of live soul and jazz, from the afternoon until the lights go down.</p></div>
      </article>
      <article class="exp-card reveal reveal-d1">
        <img src="<?= e(url('assets/img/gallery/activation-23.jpg')) ?>" alt="The George Musical Festival crew with local partners" loading="lazy">
        <div class="exp-body"><span class="exp-n">02</span><h3>Food, culture &amp; community</h3><p>Garden Route food, fashion and friends. A day out built for the whole community, not just the front row.</p></div>
      </article>
      <article class="exp-card reveal reveal-d2">
        <img src="<?= e(url('assets/img/cellc/concert-phones-blue.jpg')) ?>" alt="A festival crowd lighting up the night with their phones" loading="lazy">
        <div class="exp-body"><span class="exp-n">03</span><h3>Capture every moment</h3><p>Film the headliners and share the night, then share your <?= e(setting('comp_name')) ?> link for a place on the leaderboard.</p></div>
      </article>
      <article class="exp-card reveal reveal-d3">
        <img src="<?= e(url('assets/img/cellc/selfie-kids.jpg')) ?>" alt="A man and a group of children smiling for a selfie" loading="lazy">
        <div class="exp-body"><span class="exp-n">04</span><h3>Bring the whole crew</h3><p>A day out for the whole community. Every friend who opens your link moves you closer to the phone.</p></div>
      </article>
    </div>
  </div>
</section>

<!-- ═══════════════ 03 PLAN YOUR TRIP ═══════════════ -->
<section class="section on-paper" id="plan">
  <div class="wrap plan-grid">
    <figure class="plan-photo reveal">
      <img src="<?= e(url('assets/img/cellc/george-pass-road.jpg')) ?>" alt="A winding mountain road through green forest near George" loading="lazy">
    </figure>
    <div class="plan-copy">
      <span class="kicker reveal" style="color:var(--orange-deep)"><span class="num">03</span> Plan your trip</span>
      <h2 class="display big reveal" style="margin:18px 0 26px;color:var(--text-dark);">Make it a <span class="serif-i" style="color:var(--orange-deep)">weekend</span> away.</h2>
      <ol class="plan-steps">
        <li class="reveal"><i class="fa-solid fa-route"></i><div><strong>Getting to George</strong><span>Drive in over the Outeniqua Pass or along the N2, or fly into George Airport with daily domestic flights.</span></div></li>
        <li class="reveal"><i class="fa-solid fa-location-dot"></i><div><strong><?= e($venue) ?></strong><span><?= e(fmt_date($eventDate, 'l, j F Y')) ?> · <?= e(setting('event_time')) ?></span></div></li>
        <li class="reveal"><i class="fa-solid fa-bed"></i><div><strong>Stay on the Garden Route</strong><span>George, Mossel Bay, Knysna, Oudtshoorn and Plettenberg Bay are all within reach — turn one festival day into a full weekend.</span></div></li>
        <li class="reveal"><i class="fa-solid fa-ticket"></i><div><strong><?= $ticketUrl !== '' ? 'Tickets on sale now' : e(setting('ticket_note')) ?></strong><span><?= $ticketUrl !== '' ? '<a class="text-link" href="' . e($ticketUrl) . '" target="_blank" rel="noopener">Buy tickets <i class="fa-solid fa-arrow-right"></i></a>' : 'Join the George Musical Festival list at the bottom of this page to hear first.' ?></span></div></li>
      </ol>
      <div class="plan-cta reveal">
        <p>And capture it all on a new phone: <strong>win a brand-new Huawei Pura 80 Pro worth <?= e(setting('prize_value')) ?>.</strong></p>
        <a class="btn btn-dark" href="#enter"><i class="fa-solid fa-mobile-screen-button"></i> Enter <?= e(setting('comp_name')) ?></a>
      </div>
    </div>
  </div>
</section>

<!-- ═══════════════ PULL QUOTE ═══════════════ -->
<section class="bleed">
  <img src="<?= e(url('assets/img/cellc/concert-phones-blue.jpg')) ?>" alt="" data-parallax loading="lazy">
  <div class="wrap">
    <blockquote class="pullquote reveal">
      <p>George Musical Festival brings the <em>music</em>. Cell C keeps you <em>connected</em>.</p>
      <cite>George Musical Festival × Cell C</cite>
    </blockquote>
  </div>
</section>

<!-- ═══════════════ 04 LINE-UP ═══════════════ -->
<?php
// Headliner = the first (featured) line-up entry. Its extra photos are gallery photos whose album is the artist's name.
$headliner = $artists[0] ?? null;
$others    = array_slice($artists, 1);
$hlPhotos  = $headliner ? array_values(array_filter(media_items('image', 0, false), fn ($m) => strcasecmp($m['album'], (string) $headliner['title']) === 0 && $m['src'] !== $headliner['src'])) : [];
?>
<section class="section" id="lineup">
  <div class="wrap">
    <div class="section-head">
      <div>
        <span class="kicker reveal"><span class="num">04</span> Line-up</span>
        <h2 class="display big reveal">On the <span class="serif-i text-orange">stage</span></h2>
      </div>
    </div>
    <?php if ($headliner): ?>
      <div class="headliner reveal">
        <figure class="headliner-photo"><img src="<?= e(media_url($headliner['src'])) ?>" alt="<?= e($headliner['title']) ?> performing on stage" loading="lazy"></figure>
        <div class="headliner-copy">
          <span class="headliner-tag"><i class="fa-solid fa-star"></i> Headlining <?= e($eventName) ?></span>
          <p class="display mega headliner-name"><?= e($headliner['title']) ?></p>
          <?php if ($headliner['caption']): ?><p class="headliner-caption"><?= e($headliner['caption']) ?></p><?php endif; ?>
          <p class="muted" style="max-width:520px;margin:18px 0 0;">Live at <?= e($venue) ?> on <?= e(fmt_date($eventDate, 'l, j F Y')) ?> — plus many more artists to be announced.</p>
          <?php if ($hlPhotos): ?>
            <div class="headliner-strip">
              <?php foreach (array_slice($hlPhotos, 0, 3) as $ph): ?>
                <a href="<?= e(media_url($ph['src'])) ?>" data-lightbox="headliner" data-caption="<?= e($ph['caption'] ?: $headliner['title']) ?>"><img src="<?= e(media_url($ph['src'])) ?>" alt="<?= e($ph['title'] ?: $headliner['title']) ?>" loading="lazy"></a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
          <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:26px;">
            <a class="btn btn-orange" href="<?= e($ticketUrl !== '' ? $ticketUrl : '#tickets') ?>" <?= $ticketUrl !== '' ? 'target="_blank" rel="noopener"' : '' ?>><i class="fa-solid fa-ticket"></i> Get tickets</a>
            <a class="btn btn-ghost" href="#enter"><i class="fa-solid fa-mobile-screen-button"></i> Win a Huawei Pura 80 Pro</a>
          </div>
        </div>
      </div>
      <div class="lineup-grid" style="margin-top:18px;">
        <?php foreach ($others as $i => $a): ?>
          <figure class="artist-card reveal <?= 'reveal-d' . ($i % 3 + 1) ?>" style="margin:0">
            <img src="<?= e(media_url($a['src'])) ?>" alt="<?= e($a['title']) ?>" loading="lazy">
            <figcaption class="cap"><span><?= e($a['caption'] ?: 'Live') ?></span><strong><?= e($a['title']) ?></strong></figcaption>
          </figure>
        <?php endforeach; ?>
        <div class="artist-card artist-more reveal">
          <div><span class="kicker">And many more</span><strong>More artists<br><span class="serif-i text-orange">to be announced.</span></strong><p class="muted">Join the George Musical Festival list and hear first.</p><a class="btn btn-ghost btn-sm" href="#join">Notify me</a></div>
        </div>
      </div>
    <?php else: ?>
      <div class="lineup-soon reveal">
        <p class="display mega"><span class="outline">Line-up</span><span>reveal</span><span class="serif-i text-orange">soon.</span></p>
        <p class="muted" style="max-width:520px;margin:28px auto 0;">The artists are locked in and the announcement is close. Join the list below and you'll hear first.</p>
      </div>
    <?php endif; ?>
  </div>
</section>

<!-- ═══════════════ 05 GALLERY ═══════════════ -->
<section class="section on-ink-2" id="gallery">
  <div class="wrap">
    <div class="section-head">
      <div>
        <span class="kicker reveal"><span class="num">05</span> On the ground</span>
        <h2 class="display big reveal">The <span class="serif-i text-orange">street</span> is our stage</h2>
      </div>
      <a class="btn btn-ghost reveal" href="<?= e(url('gallery.php')) ?>">Full gallery <i class="fa-solid fa-arrow-right"></i></a>
    </div>
    <div class="mosaic">
      <?php $spans = ['m-a', 'm-b', 'm-c', 'm-d', 'm-e', 'm-f', 'm-g'];
      foreach (array_slice($images, 0, 7) as $i => $img): ?>
        <a class="tile <?= $spans[$i] ?> reveal" href="<?= e(media_url($img['src'])) ?>" data-lightbox="home" data-caption="<?= e($img['caption'] ?: $img['title']) ?>">
          <img src="<?= e(media_url($img['src'])) ?>" alt="<?= e($img['title'] ?: 'George Musical Festival') ?>" loading="lazy">
          <span class="tile-cap"><?= e($img['album']) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ═══════════════ 06 WATCH ═══════════════ -->
<section class="section" id="watch">
  <div class="wrap">
    <div class="section-head">
      <div>
        <span class="kicker reveal"><span class="num">06</span> Watch</span>
        <h2 class="display big reveal">Press <span class="serif-i text-orange">play</span></h2>
      </div>
      <?php if (count($videos) > 3): ?><a class="btn btn-ghost reveal" href="<?= e(url('gallery.php#watch')) ?>">All videos <i class="fa-solid fa-arrow-right"></i></a><?php endif; ?>
    </div>
    <?php if ($videos): $lead = array_shift($videos); ?>
      <div class="video-feature">
        <button class="video-card reveal" data-video="<?= e(media_url($lead['src'])) ?>" aria-label="Play <?= e($lead['title']) ?>">
          <img src="<?= e(video_poster($lead)) ?>" alt="" loading="lazy">
          <span class="play"><i class="fa-solid fa-play"></i></span>
          <span class="v-cap"><span><?= e($lead['album']) ?></span><strong><?= e($lead['title']) ?></strong></span>
        </button>
        <?php if ($videos): ?>
        <div class="video-side">
          <?php foreach (array_slice($videos, 0, 2) as $v): ?>
            <button class="video-card is-small reveal reveal-d2" data-video="<?= e(media_url($v['src'])) ?>" aria-label="Play <?= e($v['title']) ?>">
              <img src="<?= e(video_poster($v)) ?>" alt="" loading="lazy">
              <span class="play"><i class="fa-solid fa-play"></i></span>
              <span class="v-cap"><span><?= e($v['album']) ?></span><strong><?= e($v['title']) ?></strong></span>
            </button>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="video-soon reveal">
        <div>
          <p class="display big" style="margin:0">Festival films<br><span class="serif-i text-orange">dropping soon.</span></p>
          <p class="muted" style="margin:18px 0 0;max-width:520px;">Artist sessions, behind-the-scenes and the story of the street campaign — follow George Musical Festival so you don't miss the premiere.</p>
        </div>
        <div class="video-card" style="width:min(420px,100%);cursor:default;">
          <img src="<?= e(url('assets/img/gallery/crowd-night.jpg')) ?>" alt="" loading="lazy">
          <span class="play" style="animation:none;opacity:.85"><i class="fa-solid fa-clock"></i></span>
        </div>
      </div>
    <?php endif; ?>
  </div>
</section>

<!-- ═══════════════ 07 CALENDAR ═══════════════ -->
<section class="section on-paper" id="calendar">
  <div class="wrap">
    <div class="section-head">
      <div>
        <span class="kicker reveal" style="color:var(--orange-deep)"><span class="num">07</span> The season</span>
        <h2 class="display big reveal">Beyond <span class="serif-i" style="color:var(--orange-deep)">October</span></h2>
      </div>
    </div>
    <div>
      <div class="cal-row is-now reveal">
        <div class="cal-date"><?= e(fmt_date($eventDate, 'j M Y')) ?></div>
        <div class="cal-title"><?= e($eventName) ?></div>
        <div class="cal-where"><?= e($venue) ?></div>
      </div>
      <?php foreach (upcoming_events() as $ev): ?>
        <div class="cal-row reveal">
          <div class="cal-date"><?= e($ev['date']) ?></div>
          <div class="cal-title"><?= e($ev['title']) ?></div>
          <div class="cal-where"><?= e($ev['venue']) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ═══════════════ TICKETS + PARTNERS ═══════════════ -->
<section class="section-sm on-paper-2" id="tickets">
  <div class="wrap ticket-band">
    <div class="reveal">
      <span class="kicker" style="color:var(--orange-deep)">Tickets</span>
      <h2 class="display big" style="margin-top:16px;"><?= $ticketUrl !== '' ? 'Secure your <span class="serif-i" style="color:var(--orange-deep)">spot</span>' : e(setting('ticket_note')) ?></h2>
      <p class="muted" style="margin-top:14px;max-width:520px;"><?= e(fmt_date($eventDate, 'l, j F Y')) ?> · <?= e($venue) ?> · <?= e(setting('event_time')) ?></p>
    </div>
    <div class="reveal reveal-d2" style="display:flex;gap:12px;flex-wrap:wrap;">
      <?php if ($ticketUrl !== ''): ?>
        <a class="btn btn-dark btn-lg" href="<?= e($ticketUrl) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-ticket"></i> Buy tickets</a>
      <?php else: ?>
        <a class="btn btn-dark btn-lg" href="#join"><i class="fa-regular fa-bell"></i> Notify me</a>
      <?php endif; ?>
      <a class="btn btn-outline-dark btn-lg" href="<?= e(url('win/')) ?>">Enter Win a Huawei</a>
    </div>
  </div>
</section>

<section class="section-sm on-paper">
  <div class="wrap">
    <p class="kicker reveal" style="color:var(--text-muted);justify-content:center;display:flex;">In partnership</p>
    <div class="partners reveal" style="margin-top:26px;">
      <img src="<?= e(url('assets/img/brand/gmf-logo-orange.png')) ?>" alt="George Musical Festival" loading="lazy">
      <span class="x">×</span>
      <img src="<?= e(url('assets/img/brand/cellc-orange.png')) ?>" alt="Cell C" loading="lazy">
    </div>
  </div>
</section>

<!-- ═══════════════ JOIN THE LIST ═══════════════ -->
<section class="section-sm on-paper" id="join" style="padding-top:0;">
  <div class="wrap">
    <div class="join-box reveal">
      <div style="position:relative">
        <span class="kicker">The George Musical Festival list</span>
        <h2 class="display big" style="margin-top:16px;">Hear it <span class="serif-i text-orange">first.</span></h2>
        <p class="muted" style="margin-top:14px;">Line-up drops, ticket releases, travel deals and every George Musical Festival event across the Garden Route — straight to your inbox and phone.</p>
      </div>
      <form action="<?= e(url('api/subscribe.php')) ?>" method="post" data-ajax style="position:relative">
        <input type="hidden" name="timer" value="<?= e(form_timer()) ?>">
        <input type="hidden" name="source" value="homepage">
        <div class="hp" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
        <div class="field"><label for="sub-email">Email address</label><input id="sub-email" type="email" name="email" required autocomplete="email" placeholder="you@example.com"></div>
        <div class="field"><label for="sub-phone">Cellphone <span class="muted">(optional)</span></label><input id="sub-phone" type="tel" name="phone" autocomplete="tel" placeholder="082 123 4567"></div>
        <label class="check"><input type="checkbox" name="consent" value="1" required> I'd like to receive news and offers from George Musical Festival. I can unsubscribe at any time.</label>
        <button class="btn btn-orange btn-block" type="submit" style="margin-top:8px;">Join the list</button>
      </form>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
