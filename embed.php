<?php
/**
 * FLY FOR TWO entry form for embedding on ACSA's website:
 *   <iframe src="https://georgemusicalfestival.co.za/flyfortwo/embed" style="width:100%;max-width:480px;height:980px;border:0" title="FLY FOR TWO"></iframe>
 * Same validation, consent and database as every other channel; entries are tagged channel = acsa_embed.
 */
require __DIR__ . '/includes/bootstrap.php';

$ancestors = implode(' ', array_map('trim', (array) config('cors_origins', [])));
header("Content-Security-Policy: frame-ancestors 'self' {$ancestors}");

$src = ['channel' => 'acsa_embed', 'activation_point' => mb_substr((string) ($_GET['pt'] ?? 'airports.co.za'), 0, 120), 'source_town' => null, 'time_slot' => null,
        'utm_source' => $_GET['utm_source'] ?? null, 'utm_medium' => $_GET['utm_medium'] ?? null, 'utm_campaign' => $_GET['utm_campaign'] ?? null];
$refCode  = '';
$towns    = lines((string) setting('towns'));
$flyOpts  = lines((string) setting('travel_options'));
$shareCap = (int) setting('share_bonus_cap');
$embed    = true;
$state    = comp_state();
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(setting('comp_name')) ?> — enter</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,600;1,9..144,400&family=Manrope:wght@400;600;700;800&family=Oswald:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="<?= e(asset('assets/css/site.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/css/comp.css')) ?>">
<?php if (turnstile_enabled()): ?><script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script><?php endif; ?>
</head>
<body class="embed">
<div class="ff-card" id="entryCard">
  <div style="background:var(--navy);color:var(--paper);padding:18px 24px;display:flex;justify-content:space-between;align-items:center;gap:12px">
    <div><div style="font-family:var(--f-display);font-size:1.5rem;text-transform:uppercase;line-height:1"><?= e(setting('comp_name')) ?></div><small style="color:var(--orange);font-weight:800;letter-spacing:.12em;font-size:.62rem">WIN <?= e(mb_strtoupper((string) setting('prize_headline'))) ?></small></div>
    <img src="<?= e(url('assets/img/brand/gmf-logo-orange.png')) ?>" alt="George Music Festival" style="height:40px;width:auto">
  </div>
  <?php if ($state === 'open'): ?>
    <?php include __DIR__ . '/includes/entry-flow.php'; ?>
  <?php else: ?>
    <div class="ff-body ff-center"><h2 class="ff-h"><?= $state === 'upcoming' ? 'Entries open soon' : 'The competition has closed' ?></h2>
      <p class="ff-sub"><a href="<?= e(url('flyfortwo/')) ?>" target="_blank" rel="noopener" style="text-decoration:underline">Visit the competition page</a></p></div>
  <?php endif; ?>
</div>
<script>window.FF = { api: <?= json_encode(url('api/entry.php')) ?>, cooldown: <?= (int) setting('otp_cooldown') ?> };</script>
<script src="<?= e(asset('assets/js/enter.js')) ?>"></script>
</body>
</html>
