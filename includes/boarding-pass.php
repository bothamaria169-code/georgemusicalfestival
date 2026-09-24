<?php
/** Boarding-pass confirmation for a signed-in entrant. Expects $me. Shows their leaderboard score. */
$score = referral_stats((int) $me['id'])['clicks'];
?>
<div class="pass">
  <div class="pass-top"><span class="pass-brand"><?= e(setting('comp_name')) ?></span><span class="pass-tag">GMF × ACSA</span></div>
  <div class="pass-mid">
    <div><small>Entrant</small><strong><?= e(short_name($me['full_name'])) ?></strong></div>
    <div><small>Pass</small><strong><?= e($me['ref_code']) ?></strong></div>
  </div>
  <div class="pass-mid">
    <div><small>From</small><strong><?= e($me['town'] ?: 'Garden Route') ?></strong></div>
    <div style="text-align:right"><small>To</small><strong>A weekend away</strong></div>
  </div>
  <div class="pass-divider"></div>
  <div class="pass-bottom">
    <div class="pass-entries"><span class="num"><?= $score ?></span><span>link open<?= $score === 1 ? '' : 's' ?> · your score</span></div>
    <div class="pass-barcode" aria-hidden="true"></div>
  </div>
  <div class="pass-foot"><i class="fa-solid fa-circle-check"></i> Entry confirmed · the most link opens wins</div>
</div>
