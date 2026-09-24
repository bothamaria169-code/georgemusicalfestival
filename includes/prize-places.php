<?php
/** The two prizes under the leaderboard rules. Optional $placesClass adds classes to the wrapper. */
$gp = draw_by_key('final');
$sp = draw_by_key('event_day');
$revealDate = fmt_date($gp['scheduled_for'] ?? setting('comp_close_at'), 'j M');
?>
<div class="draw-dates <?= e($placesClass ?? '') ?>">
  <div class="draw-date"><small><i class="fa-solid fa-trophy"></i> Grand Prize · 1st place</small><strong><?= e(setting('prize_headline')) ?></strong><span>Most link opens · revealed <?= e($revealDate) ?></span></div>
  <div class="draw-date"><small><i class="fa-solid fa-medal"></i> Second Prize · runner-up</small><strong><?= e(prize_lines($sp)[0] ?? 'To be announced') ?></strong><span>2nd most link opens · revealed <?= e($revealDate) ?></span></div>
</div>
