<?php
/** The two prizes under the leaderboard rules. Optional $placesClass adds classes to the wrapper. */
$gp = draw_by_key('final');
$sp = draw_by_key('event_day');
$announceAt = $gp['scheduled_for'] ?? '2026-12-12';
$revealDate = 'on ' . fmt_date($announceAt, 'j M');
?>
<div class="draw-dates <?= e($placesClass ?? '') ?>">
  <div class="draw-date"><small><i class="fa-solid fa-trophy"></i> Grand Prize · 1st place</small><strong><?= e(setting('prize_headline')) ?></strong><span>Most link opens · revealed <?= e($revealDate) ?></span></div>
  <div class="draw-date"><small><i class="fa-solid fa-medal"></i> Second Prize · runner-up</small><strong><?= e(prize_lines($sp)[0] ?? 'To be announced') ?></strong><span>2nd most link opens · revealed <?= e($revealDate) ?></span></div>
  <div class="draw-announce"><small><i class="fa-solid fa-bullhorn"></i> Winners announced</small><strong><?= e(fmt_date($announceAt, 'j F Y')) ?></strong><span>Entries close <?= e(fmt_date(setting('comp_close_at'), 'j F Y \a\t H:i')) ?></span></div>
</div>
