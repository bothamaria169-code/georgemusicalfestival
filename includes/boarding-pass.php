<?php
/** Boarding-pass confirmation for a signed-in entrant. Expects $me and $count. */
$bd = entry_breakdown((int) $me['id']);
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
    <div class="pass-entries"><span class="num"><?= $count ?></span><span>entr<?= $count === 1 ? 'y' : 'ies' ?> confirmed</span></div>
    <div class="pass-barcode" aria-hidden="true"></div>
  </div>
  <?php if ((int) setting('share_bonus_cap') > 0): ?>
  <div class="pass-foot"><?= $bd['share_bonus'] > 0 ? '<i class="fa-solid fa-circle-check"></i> Share bonus earned' : '<i class="fa-regular fa-clock"></i> Share bonus: unlocks when a friend opens your link' ?></div>
  <?php endif; ?>
</div>
