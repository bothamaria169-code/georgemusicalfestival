<?php
/** Big scrolling ACSA × GMF logo strip, shown directly under the header on the competition pages. */
$acsaLogo = url('assets/img/brand/acsa-teal-lg.png');
$gmfLogo  = url('assets/img/brand/gmf-logo-orange.png');
?>
<div class="logo-ticker" role="img" aria-label="In partnership with Airports Company South Africa and George Music Festival">
  <div class="logo-ticker-track" aria-hidden="true">
    <?php for ($i = 0; $i < 4; $i++): ?>
      <img class="lt-acsa" src="<?= e($acsaLogo) ?>" alt="" width="900" height="439">
      <span class="lt-text">Getting the unflown flying</span>
      <img class="lt-gmf" src="<?= e($gmfLogo) ?>" alt="" width="620" height="424">
      <span class="lt-text">Win a trip for two</span>
    <?php endfor; ?>
  </div>
</div>
