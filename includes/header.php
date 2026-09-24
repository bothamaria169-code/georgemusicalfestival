<?php
/**
 * Shared public header. Set before including:
 *   $page_title, $meta_description, $body_class ('' | 'page-light' ...), $nav_active, $extra_css[]
 */
$page_title       = $page_title ?? 'George Music Festival';
$meta_description = $meta_description ?? 'George Music Festival — music, culture and community on the Garden Route. ' . setting('event_name') . ', ' . fmt_date(setting('event_date')) . ', ' . setting('event_venue') . '.';
$body_class       = $body_class ?? '';
$nav_active       = $nav_active ?? '';
$extra_css        = $extra_css ?? [];
$me               = current_entrant();
$ticketUrl        = (string) setting('ticket_url');
$announcement     = (string) setting('announcement');
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
$nav = [
    'home'    => ['index.php', 'Home'],
    'event'   => ['index.php#event', 'The Event'],
    'lineup'  => ['index.php#lineup', 'Line-up'],
    'gallery' => ['gallery.php', 'Gallery'],
    'watch'   => ['gallery.php#watch', 'Watch'],
];
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($page_title) ?></title>
<meta name="description" content="<?= e($meta_description) ?>">
<meta name="theme-color" content="#0a0a0b">
<meta property="og:title" content="<?= e($page_title) ?>">
<meta property="og:description" content="<?= e($meta_description) ?>">
<meta property="og:image" content="<?= e($og_image ?? url('assets/img/gallery/crowd-night.jpg')) ?>">
<meta property="og:type" content="website">
<link rel="icon" type="image/png" href="<?= e(url('assets/img/brand/gmf-logo-orange.png')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,600;0,9..144,700;1,9..144,400;1,9..144,600&family=Manrope:wght@400;500;600;700;800&family=Oswald:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="<?= e(asset('assets/css/site.css')) ?>">
<?php foreach ($extra_css as $css): ?>
<link rel="stylesheet" href="<?= e(asset($css)) ?>">
<?php endforeach; ?>
<?php if (turnstile_enabled() && !empty($needs_turnstile)): ?>
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<?php endif; ?>
</head>
<body class="<?= e($body_class) ?>">
<div class="scroll-progress" aria-hidden="true"></div>

<?php if ($announcement !== '' && comp_state() === 'open' && $nav_active !== 'fly'): ?>
<a class="announce" href="<?= e(url('flyfortwo/')) ?>">
  <span class="announce-dot"></span><?= e($announcement) ?> <strong>Enter now <i class="fa-solid fa-arrow-right"></i></strong>
</a>
<?php endif; ?>

<header class="site-header" id="siteHeader">
  <div class="wrap header-inner">
    <a class="brand" href="<?= e(url('index.php')) ?>" aria-label="George Music Festival home">
      <img src="<?= e(url('assets/img/brand/gmf-logo-orange.png')) ?>" alt="George Music Festival" width="120" height="82">
    </a>
    <nav class="main-nav" id="mainNav" aria-label="Main">
      <?php foreach ($nav as $key => [$href, $label]): ?>
        <a href="<?= e(url($href)) ?>" class="<?= $nav_active === $key ? 'is-active' : '' ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
      <a href="<?= e(url('flyfortwo/')) ?>" class="nav-fly <?= $nav_active === 'fly' ? 'is-active' : '' ?>"><i class="fa-solid fa-plane-departure"></i> Fly For Two</a>
      <div class="nav-mobile-extra">
        <a href="<?= e(url($me ? 'flyfortwo/dashboard' : 'flyfortwo/login')) ?>"><?= $me ? 'My dashboard' : 'Sign in' ?></a>
      </div>
    </nav>
    <div class="header-actions">
      <a class="header-link" href="<?= e(url($me ? 'flyfortwo/dashboard' : 'flyfortwo/login')) ?>">
        <i class="fa-regular fa-user"></i><span><?= $me ? e(explode(' ', $me['full_name'])[0]) : 'Sign in' ?></span>
      </a>
      <a class="btn btn-orange btn-sm" href="<?= e($ticketUrl !== '' ? $ticketUrl : url('index.php#tickets')) ?>" <?= $ticketUrl !== '' ? 'target="_blank" rel="noopener"' : '' ?>>Tickets</a>
      <button class="nav-toggle" id="navToggle" aria-label="Open menu" aria-expanded="false" aria-controls="mainNav"><span></span><span></span><span></span></button>
    </div>
  </div>
</header>

<?php foreach (flashes() as $f): ?>
  <div class="flash flash-<?= e($f['type']) ?>" role="status"><div class="wrap"><?= e($f['message']) ?></div></div>
<?php endforeach; ?>
<main id="main">
