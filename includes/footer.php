</main>
<?php
$socials = array_filter([
    'facebook'  => setting('social_facebook'),
    'instagram' => setting('social_instagram'),
    'tiktok'    => setting('social_tiktok'),
]);
?>
<footer class="site-footer">
  <div class="wrap">
    <div class="footer-mega">George<br>Music<br><em>Festival</em></div>
    <div class="footer-grid">
      <div>
        <img class="footer-logo" src="<?= e(url('assets/img/brand/gmf-logo-orange.png')) ?>" alt="George Music Festival" width="150" height="102" loading="lazy">
        <p class="footer-blurb">A regional platform connecting music, culture, community, lifestyle and tourism across the Garden Route.</p>
        <?php if ($socials): ?>
        <div class="footer-social">
          <?php foreach ($socials as $net => $link): ?>
            <a href="<?= e($link) ?>" target="_blank" rel="noopener" aria-label="<?= e(ucfirst($net)) ?>"><i class="fa-brands fa-<?= e($net) ?>"></i></a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
      <div>
        <h4>Festival</h4>
        <a href="<?= e(url('index.php#event')) ?>">The Event</a>
        <a href="<?= e(url('index.php#lineup')) ?>">Line-up</a>
        <a href="<?= e(url('gallery.php')) ?>">Gallery</a>
        <a href="<?= e(url('gallery.php#watch')) ?>">Watch</a>
        <a href="<?= e(url('index.php#calendar')) ?>">Upcoming events</a>
      </div>
      <div>
        <h4>Fly For Two</h4>
        <a href="<?= e(url('flyfortwo/')) ?>">Enter the competition</a>
        <a href="<?= e(url('flyfortwo/login')) ?>">My dashboard</a>
        <a href="<?= e(url('flyfortwo/#leaderboard')) ?>">Referral leaderboard</a>
        <a href="<?= e(url('flyfortwo/terms')) ?>">Competition terms</a>
        <a href="<?= e(url('flyfortwo/privacy')) ?>">Privacy (POPIA)</a>
      </div>
      <div>
        <h4>Contact</h4>
        <?php if (setting('contact_email')): ?><a href="mailto:<?= e(setting('contact_email')) ?>"><?= e(setting('contact_email')) ?></a><?php endif; ?>
        <?php if (setting('contact_phone')): ?><a href="tel:<?= e(preg_replace('/\s+/', '', (string) setting('contact_phone'))) ?>"><?= e(setting('contact_phone')) ?></a><?php endif; ?>
        <p class="footer-partner">In partnership with</p>
        <img class="footer-acsa" src="<?= e(url('assets/img/brand/acsa-white.png')) ?>" alt="Airports Company South Africa" width="170" height="83" loading="lazy">
      </div>
    </div>
    <div class="footer-bottom">
      <span>&copy; <?= date('Y') ?> George Music Festival. All rights reserved. Your personal information is protected under POPIA.</span>
      <span><a href="<?= e(url('flyfortwo/terms')) ?>">Terms</a> · <a href="<?= e(url('flyfortwo/privacy')) ?>">Privacy</a></span>
    </div>
  </div>
</footer>

<div class="lightbox" id="lightbox" aria-hidden="true" role="dialog" aria-label="Image viewer">
  <button class="lb-close" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
  <button class="lb-prev" aria-label="Previous"><i class="fa-solid fa-arrow-left"></i></button>
  <figure><img alt=""><figcaption></figcaption></figure>
  <button class="lb-next" aria-label="Next"><i class="fa-solid fa-arrow-right"></i></button>
</div>

<script src="<?= e(asset('assets/js/site.js')) ?>"></script>
<?php foreach ($extra_js ?? [] as $js): ?>
<script src="<?= e(asset($js)) ?>"></script>
<?php endforeach; ?>
</body>
</html>
