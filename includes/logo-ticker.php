<?php
/** Big scrolling Cell C × George Musical Festival logo strip, shown directly under the header on the competition pages. */
$acsaLogo = url('assets/img/brand/cellc-orange.png');
$gmfLogo  = url('assets/img/brand/gmf-logo-orange.png');
?>
<div class="logo-ticker" role="img" aria-label="In partnership with Cell C and George Musical Festival">
  <div class="logo-ticker-track" aria-hidden="true">
    <?php for ($i = 0; $i < 4; $i++): ?>
      <img class="lt-acsa" src="<?= e($acsaLogo) ?>" alt="" width="684" height="265">
      <span class="lt-text">Change your world</span>
      <img class="lt-gmf" src="<?= e($gmfLogo) ?>" alt="" width="620" height="424">
      <span class="lt-text">Win a Huawei Pura 80 Pro</span>
    <?php endfor; ?>
  </div>
</div>
