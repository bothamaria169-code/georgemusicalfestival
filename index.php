<?php
require __DIR__ . '/includes/bootstrap.php';

$eventName  = (string) setting('event_name');
$eventDate  = (string) setting('event_date');
$venue      = (string) setting('event_venue');
$ticketUrl  = (string) setting('ticket_url');
$heroVideo  = (string) setting('hero_video_url');
$finalDraw  = draw_by_key('final');
$eventDraw  = draw_by_key('event_day');
$images     = media_items('image', 7);
$videos     = media_items('video', 5);
$artists    = media_items('artist');
$state      = comp_state();

// "Eden Soul & Jazz Reimagined" → cover lines ["Eden", "Soul & Jazz"] + italic "Reimagined"
$words = preg_split('/\s+/', $eventName) ?: [];
$accent = count($words) > 2 ? array_pop($words) : '';
$coverLines = [implode(' ', array_slice($words, 0, 1)), implode(' ', array_slice($words, 1))];

$page_title = 'George Music Festival — ' . $eventName . ' · ' . fmt_date($eventDate, 'j M Y');
$nav_active = 'home';
require __DIR__ . '/includes/header.php';

$ytId = preg_match('#(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([\w-]{11})#', $heroVideo, $m) ? $m[1] : null;
?>

<!-- ═══════════════ COVER ═══════════════ -->
<section class="cover grain">
  <div class="cover-media">
    <?php if ($ytId): ?>
      <iframe src="https://www.youtube-nocookie.com/embed/<?= e($ytId) ?>?autoplay=1&mute=1&loop=1&playlist=<?= e($ytId) ?>&controls=0&showinfo=0&modestbranding=1&playsinline=1" allow="autoplay; encrypted-media" title="Festival film" tabindex="-1"></iframe>
    <?php elseif ($heroVideo !== ''): ?>
      <video src="<?= e(media_url($heroVideo)) ?>" autoplay muted loop playsinline poster="<?= e(url('assets/img/gallery/crowd-night.jpg')) ?>"></video>
    <?php else: ?>
      <img src="<?= e(url('assets/img/gallery/crowd-night.jpg')) ?>" alt="The crowd at a George Music Festival night show" fetchpriority="high">
    <?php endif; ?>
  </div>

  <div class="wrap">
    <div class="cover-meta">
      <span>George Music Festival · <b>Issue 2026</b></span>
      <span>Garden Route · South Africa</span>
    </div>

    <h1 class="display mega cover-title">
      <span class="line"><span><?= e($coverLines[0]) ?></span></span>
      <span class="line"><span><?= e($coverLines[1]) ?></span></span>
      <?php if ($accent): ?><span class="line"><span class="serif-i"><?= e($accent) ?></span></span><?php endif; ?>
    </h1>

    <div class="cover-foot">
      <div>
        <p class="lede"><?= e(setting('event_tagline')) ?></p>
        <div class="cover-actions">
          <a class="btn btn-orange btn-lg" href="<?= e($ticketUrl !== '' ? $ticketUrl : '#tickets') ?>" <?= $ticketUrl !== '' ? 'target="_blank" rel="noopener"' : '' ?>><i class="fa-solid fa-ticket"></i> Get tickets</a>
          <a class="btn btn-ghost btn-lg" href="<?= e(url('flyfortwo/')) ?>"><i class="fa-solid fa-plane-departure"></i> Win a trip for two</a>
        </div>
      </div>
      <div class="cover-facts">
        <div>Date<strong><?= e(fmt_date($eventDate, 'j M')) ?></strong></div>
        <div>Venue<strong>Thembalethu</strong></div>
        <div>City<strong>George</strong></div>
      </div>
    </div>
  </div>
</section>

<!-- ═══════════════ TICKER ═══════════════ -->
<div class="marquee" aria-hidden="true">
  <div class="marquee-track">
    <?php for ($i = 0; $i < 2; $i++): ?>
      <span><?= e(fmt_date($eventDate, 'j F Y')) ?></span><span><?= e($venue) ?></span><span>Soul</span><span>Jazz</span><span>Culture</span><span>Fly For Two</span><span>Garden Route</span>
    <?php endfor; ?>
  </div>
</div>

<!-- ═══════════════ 01 THE EVENT ═══════════════ -->
<section class="section on-paper" id="event">
  <div class="wrap">
    <div class="spread">
      <div class="spread-text">
        <span class="kicker reveal"><span class="num">01</span> The Event</span>
        <h2 class="display huge reveal" style="margin:22px 0 34px;">Music is the <span class="serif-i text-orange">reason</span> to travel.</h2>
        <p class="lede dropcap reveal"><?= e($eventName) ?> brings soul, jazz and the sound of the Garden Route to <?= e($venue) ?> — a full day of live music, food, culture and community under the George sky.</p>
        <p class="reveal muted">George Music Festival is a regional platform connecting music, culture, community, lifestyle and tourism. Local talent gives it authenticity; the Garden Route gives it a destination worth flying to. This year, with Airports Company South Africa, we're turning the festival into a journey — from the first flyer on the street to the final note on stage.</p>

        <div class="fact-grid reveal">
          <div><small>Date</small><strong><?= e(fmt_date($eventDate, 'l, j F Y')) ?></strong></div>
          <div><small>Venue</small><strong><?= e($venue) ?></strong></div>
          <div><small>Time</small><strong><?= e(setting('event_time')) ?></strong></div>
          <div><small>Tickets</small><strong><?= e($ticketUrl !== '' ? 'On sale now' : setting('ticket_note')) ?></strong></div>
        </div>

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
        <div class="img-a"><img src="<?= e(url('assets/img/gallery/performer-stage.jpg')) ?>" alt="A performer on stage at George Music Festival" loading="lazy"></div>
        <div class="img-b"><img src="<?= e(url('assets/img/gallery/activation-22.jpg')) ?>" alt="The GMF street team in George" loading="lazy"></div>
      </div>
    </div>
  </div>
</section>

<!-- ═══════════════ PULL QUOTE ═══════════════ -->
<section class="bleed">
  <img src="<?= e(url('assets/img/gallery/airport-terminal.jpg')) ?>" alt="" data-parallax loading="lazy">
  <div class="wrap">
    <blockquote class="pullquote reveal">
      <p>GMF creates the <em>reason</em> to travel. ACSA helps define the <em>journey</em>.</p>
      <cite>George Music Festival × Airports Company South Africa</cite>
    </blockquote>
  </div>
</section>

<!-- ═══════════════ 02 FLY FOR TWO ═══════════════ -->
<section class="section fly-feature" id="fly">
  <div class="wrap fly-grid">
    <div>
      <span class="kicker reveal"><span class="num">02</span> The Competition</span>
      <h2 class="display huge reveal" style="margin-top:22px;">Fly <span class="serif-i">for</span> two.</h2>
      <p class="lede reveal" style="margin-top:22px;max-width:560px;">Enter free and win <strong><?= e(setting('prize_headline')) ?></strong>. It takes less than a minute — and sharing your link earns you a bonus entry.</p>
      <ul class="prize-list reveal">
        <?php $icons = ['fa-plane', 'fa-hotel', 'fa-van-shuttle', 'fa-star'];
        foreach (prize_lines($finalDraw) as $i => $line): ?>
          <li><i class="fa-solid <?= $icons[$i] ?? 'fa-star' ?>"></i><?= e($line) ?></li>
        <?php endforeach; ?>
      </ul>
      <div class="reveal" style="display:flex;gap:12px;flex-wrap:wrap;">
        <?php if ($state === 'open'): ?>
          <a class="btn btn-orange btn-lg" href="<?= e(url('flyfortwo/')) ?>">Enter now — it's free</a>
        <?php elseif ($state === 'upcoming'): ?>
          <a class="btn btn-orange btn-lg" href="<?= e(url('flyfortwo/')) ?>">Opens <?= e(fmt_date(setting('comp_open_at'), 'j M')) ?></a>
        <?php else: ?>
          <a class="btn btn-ghost btn-lg" href="<?= e(url('flyfortwo/')) ?>">Competition closed — see results</a>
        <?php endif; ?>
        <a class="btn btn-ghost btn-lg" href="<?= e(url('flyfortwo/login')) ?>">My dashboard</a>
      </div>
      <div class="draw-dates reveal">
        <div class="draw-date"><small>Event-Day Draw</small><strong><?= e(fmt_date($eventDraw['scheduled_for'] ?? '2026-10-03', 'j M')) ?></strong><span><?= e(prize_lines($eventDraw)[0] ?? 'Prize to be announced') ?></span></div>
        <div class="draw-date"><small>Final Draw</small><strong><?= e(fmt_date($finalDraw['scheduled_for'] ?? '2026-10-31', 'j M')) ?></strong><span><?= e(setting('prize_headline')) ?></span></div>
      </div>
    </div>
    <div class="fly-mascot reveal reveal-d2">
      <div class="fly-ring"></div>
      <img src="<?= e(url('assets/img/brand/mascot-moyeni.png')) ?>" alt="Moyeni, the ACSA mascot, flying" loading="lazy">
    </div>
  </div>
</section>

<!-- ═══════════════ 03 LINE-UP ═══════════════ -->
<section class="section" id="lineup">
  <div class="wrap">
    <div class="section-head">
      <div>
        <span class="kicker reveal"><span class="num">03</span> Line-up</span>
        <h2 class="display big reveal">On the <span class="serif-i text-orange">stage</span></h2>
      </div>
    </div>
    <?php if ($artists): ?>
      <div class="lineup-grid">
        <?php foreach ($artists as $i => $a): ?>
          <figure class="artist-card reveal <?= 'reveal-d' . ($i % 3 + 1) ?>" style="margin:0">
            <img src="<?= e(media_url($a['src'])) ?>" alt="<?= e($a['title']) ?>" loading="lazy">
            <figcaption class="cap"><span><?= e($a['caption'] ?: 'Live') ?></span><strong><?= e($a['title']) ?></strong></figcaption>
          </figure>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="lineup-soon reveal">
        <p class="display mega"><span class="outline">Line-up</span><span>reveal</span><span class="serif-i text-orange">soon.</span></p>
        <p class="muted" style="max-width:520px;margin:28px auto 0;">The artists are locked in and the announcement is close. Join the list below and you'll hear first.</p>
      </div>
    <?php endif; ?>
  </div>
</section>

<!-- ═══════════════ 04 GALLERY ═══════════════ -->
<section class="section on-ink-2" id="gallery">
  <div class="wrap">
    <div class="section-head">
      <div>
        <span class="kicker reveal"><span class="num">04</span> On the ground</span>
        <h2 class="display big reveal">The <span class="serif-i text-orange">street</span> is our stage</h2>
      </div>
      <a class="btn btn-ghost reveal" href="<?= e(url('gallery.php')) ?>">Full gallery <i class="fa-solid fa-arrow-right"></i></a>
    </div>
    <div class="mosaic">
      <?php $spans = ['m-a', 'm-b', 'm-c', 'm-d', 'm-e', 'm-f', 'm-g'];
      foreach (array_slice($images, 0, 7) as $i => $img): ?>
        <a class="tile <?= $spans[$i] ?> reveal" href="<?= e(media_url($img['src'])) ?>" data-lightbox="home" data-caption="<?= e($img['caption'] ?: $img['title']) ?>">
          <img src="<?= e(media_url($img['src'])) ?>" alt="<?= e($img['title'] ?: 'George Music Festival') ?>" loading="lazy">
          <span class="tile-cap"><?= e($img['album']) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ═══════════════ 05 WATCH ═══════════════ -->
<section class="section" id="watch">
  <div class="wrap">
    <div class="section-head">
      <div>
        <span class="kicker reveal"><span class="num">05</span> Watch</span>
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
          <p class="muted" style="margin:18px 0 0;max-width:520px;">Artist sessions, behind-the-scenes and the story of the street campaign — follow GMF so you don't miss the premiere.</p>
        </div>
        <div class="video-card" style="width:min(420px,100%);cursor:default;">
          <img src="<?= e(url('assets/img/gallery/crowd-night.jpg')) ?>" alt="" loading="lazy">
          <span class="play" style="animation:none;opacity:.85"><i class="fa-solid fa-clock"></i></span>
        </div>
      </div>
    <?php endif; ?>
  </div>
</section>

<!-- ═══════════════ 06 CALENDAR ═══════════════ -->
<section class="section on-paper" id="calendar">
  <div class="wrap">
    <div class="section-head">
      <div>
        <span class="kicker reveal" style="color:var(--orange-deep)"><span class="num">06</span> The season</span>
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
      <a class="btn btn-outline-dark btn-lg" href="<?= e(url('flyfortwo/')) ?>">Enter Fly For Two</a>
    </div>
  </div>
</section>

<section class="section-sm on-paper">
  <div class="wrap">
    <p class="kicker reveal" style="color:var(--text-muted);justify-content:center;display:flex;">In partnership</p>
    <div class="partners reveal" style="margin-top:26px;">
      <img src="<?= e(url('assets/img/brand/gmf-logo-orange.png')) ?>" alt="George Music Festival" loading="lazy">
      <span class="x">×</span>
      <img src="<?= e(url('assets/img/brand/acsa-teal.png')) ?>" alt="Airports Company South Africa" loading="lazy">
    </div>
  </div>
</section>

<!-- ═══════════════ JOIN THE LIST ═══════════════ -->
<section class="section-sm on-paper" id="join" style="padding-top:0;">
  <div class="wrap">
    <div class="join-box reveal">
      <div style="position:relative">
        <span class="kicker">The GMF list</span>
        <h2 class="display big" style="margin-top:16px;">Hear it <span class="serif-i text-orange">first.</span></h2>
        <p class="muted" style="margin-top:14px;">Line-up drops, ticket releases, travel deals and every GMF event across the Garden Route — straight to your inbox and phone.</p>
      </div>
      <form action="<?= e(url('api/subscribe.php')) ?>" method="post" data-ajax style="position:relative">
        <input type="hidden" name="timer" value="<?= e(form_timer()) ?>">
        <input type="hidden" name="source" value="homepage">
        <div class="hp" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
        <div class="field"><label for="sub-email">Email address</label><input id="sub-email" type="email" name="email" required autocomplete="email" placeholder="you@example.com"></div>
        <div class="field"><label for="sub-phone">Cellphone <span class="muted">(optional)</span></label><input id="sub-phone" type="tel" name="phone" autocomplete="tel" placeholder="082 123 4567"></div>
        <label class="check"><input type="checkbox" name="consent" value="1" required> I'd like to receive news and offers from George Music Festival. I can unsubscribe at any time.</label>
        <button class="btn btn-orange btn-block" type="submit" style="margin-top:8px;">Join the list</button>
      </form>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
