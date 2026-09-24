<?php
require __DIR__ . '/includes/bootstrap.php';

$images = media_items('image', 0, false);
$videos = media_items('video', 0);
$albums = array_values(array_unique(array_column($images, 'album')));
$hero   = $images[0]['src'] ?? 'assets/img/gallery/crowd-night.jpg';

$page_title = 'Gallery & Videos — George Music Festival';
$nav_active = 'gallery';
require __DIR__ . '/includes/header.php';
?>

<section class="page-hero grain" style="padding-bottom:40px;">
  <div class="wrap">
    <span class="kicker reveal">The archive · <?= count($images) ?> photos<?= $videos ? ' · ' . count($videos) . ' films' : '' ?></span>
    <h1 class="display mega reveal">The <span class="serif-i text-orange">gallery</span></h1>
    <p class="lede muted reveal">Street activations across the Garden Route, nights under the lights and the people who make George Music Festival what it is.</p>
  </div>
</section>

<?php if (count($images) >= 3): ?>
<section class="wrap" style="margin-bottom:clamp(40px,6vw,80px);">
  <div class="mosaic">
    <?php foreach (array_slice($images, 0, 3) as $i => $img): ?>
      <a class="tile <?= ['m-a', 'm-f', 'm-g'][$i] ?> reveal" style="<?= $i === 0 ? 'grid-column:span 7;grid-row:span 5' : 'grid-column:span 5;grid-row:span ' . ($i === 1 ? 3 : 2) ?>" href="<?= e(media_url($img['src'])) ?>" data-lightbox="lead" data-caption="<?= e($img['caption'] ?: $img['title']) ?>">
        <img src="<?= e(media_url($img['src'])) ?>" alt="<?= e($img['title'] ?: 'George Music Festival') ?>" <?= $i ? 'loading="lazy"' : '' ?>>
        <span class="tile-cap"><?= e($img['title'] ?: $img['album']) ?></span>
      </a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<section class="section" style="padding-top:0;">
  <div class="wrap">
    <?php if (count($albums) > 1): ?>
      <div class="chips reveal" role="toolbar" aria-label="Filter by album">
        <button class="chip is-active" data-filter="*">All</button>
        <?php foreach ($albums as $a): ?><button class="chip" data-filter="<?= e($a) ?>"><?= e($a) ?></button><?php endforeach; ?>
      </div>
    <?php endif; ?>
    <div class="masonry">
      <?php foreach ($images as $img): ?>
        <a class="tile reveal" data-album="<?= e($img['album']) ?>" href="<?= e(media_url($img['src'])) ?>" data-lightbox="all" data-caption="<?= e($img['caption'] ?: $img['title']) ?>">
          <img src="<?= e(media_url($img['src'])) ?>" alt="<?= e($img['title'] ?: 'George Music Festival') ?>" loading="lazy">
          <span class="tile-cap"><?= e($img['album']) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<div class="marquee is-dark" aria-hidden="true">
  <div class="marquee-track">
    <?php for ($i = 0; $i < 2; $i++): ?><span>Watch</span><span>Festival films</span><span>Artist sessions</span><span>Behind the scenes</span><span>On the road</span><?php endfor; ?>
  </div>
</div>

<section class="section" id="watch">
  <div class="wrap">
    <div class="section-head">
      <div>
        <span class="kicker reveal">Watch</span>
        <h2 class="display big reveal">Festival <span class="serif-i text-orange">films</span></h2>
      </div>
    </div>
    <?php if ($videos): $lead = array_shift($videos); ?>
      <button class="video-card reveal" style="aspect-ratio:21/9" data-video="<?= e(media_url($lead['src'])) ?>" aria-label="Play <?= e($lead['title']) ?>">
        <img src="<?= e(video_poster($lead)) ?>" alt="" loading="lazy">
        <span class="play"><i class="fa-solid fa-play"></i></span>
        <span class="v-cap"><span><?= e($lead['album']) ?></span><strong style="font-size:clamp(1.6rem,4vw,3.4rem)"><?= e($lead['title']) ?></strong></span>
      </button>
      <?php if ($videos): ?>
      <div class="video-grid">
        <?php foreach ($videos as $v): ?>
          <button class="video-card is-small reveal" data-video="<?= e(media_url($v['src'])) ?>" aria-label="Play <?= e($v['title']) ?>">
            <img src="<?= e(video_poster($v)) ?>" alt="" loading="lazy">
            <span class="play"><i class="fa-solid fa-play"></i></span>
            <span class="v-cap"><span><?= e($v['album']) ?></span><strong><?= e($v['title']) ?></strong></span>
          </button>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    <?php else: ?>
      <div class="video-soon reveal">
        <div>
          <p class="display big" style="margin:0">Premieres<br><span class="serif-i text-orange">coming soon.</span></p>
          <p class="muted" style="margin:18px 0 0;max-width:520px;">Artist sessions and the story of the street campaign are in the edit. Join the GMF list to catch the premiere.</p>
          <a class="btn btn-orange" style="margin-top:22px" href="<?= e(url('index.php#join')) ?>">Join the list</a>
        </div>
        <div class="video-card" style="width:min(420px,100%);cursor:default;"><img src="<?= e(media_url($hero)) ?>" alt="" loading="lazy"><span class="play" style="animation:none;opacity:.85"><i class="fa-solid fa-clock"></i></span></div>
      </div>
    <?php endif; ?>
  </div>
</section>

<style>@media (max-width:860px){.mosaic .tile[style]{grid-column:span 6 !important;grid-row:span 3 !important}}</style>
<?php require __DIR__ . '/includes/footer.php'; ?>
