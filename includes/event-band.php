<?php
/** The December event: poster, line-up, ticket prices and where to buy. Ticket link: Settings → ticket_url. */
$bandTicketUrl = (string) setting('ticket_url');
$bandArtists   = lines((string) setting('event_lineup'));
$phaseOneOpen  = date('Y-m-d') <= '2026-11-30';
?>
<section class="event-band"<?= !empty($bandAnchors) ? ' id="event"' : '' ?>>
  <div class="wrap event-band-grid">
    <a class="event-band-poster reveal" href="<?= e(url('assets/img/event/fill-up-thembalethu-poster.jpg')) ?>" target="_blank" rel="noopener">
      <img src="<?= e(url('assets/img/event/fill-up-thembalethu-poster.jpg')) ?>" width="1121" height="1401" alt="Poster for the 2nd annual Fill Up Thembalethu Stadium, 12 December 2026, with ticket prices" loading="lazy">
    </a>
    <div class="event-band-copy">
      <span class="kicker reveal">George Musical Festival presents · 2nd annual</span>
      <h2 class="display big reveal" style="margin-top:16px;"><?= e(setting('event_name')) ?></h2>
      <p class="event-band-when reveal"><?= e(fmt_date((string) setting('event_date'), 'j F Y')) ?> · <?= e(setting('event_venue')) ?></p>
      <?php if ($bandArtists): ?><p class="event-band-artists reveal"<?= !empty($bandAnchors) ? ' id="lineup"' : '' ?>><?= e(implode(' · ', $bandArtists)) ?> <span>+ more</span></p><?php endif; ?>
      <ul class="event-band-prices reveal">
        <?php if ($phaseOneOpen): ?>
          <li><small>General · Phase 1</small><strong>R200</strong><span>Until 30 November</span></li>
          <li><small>VIP · Phase 1</small><strong>R450</strong><span>Until 30 November</span></li>
        <?php endif; ?>
        <li><small>General · At the gate</small><strong>R300</strong></li>
        <li><small>VIP · At the gate</small><strong>R500</strong></li>
      </ul>
      <?php if ($bandTicketUrl !== ''): ?>
        <a class="btn btn-orange btn-lg reveal" href="<?= e($bandTicketUrl) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-ticket"></i> Buy tickets</a>
      <?php endif; ?>
      <p class="event-band-note reveal"><?= e(setting('ticket_note')) ?></p>
    </div>
  </div>
</section>
