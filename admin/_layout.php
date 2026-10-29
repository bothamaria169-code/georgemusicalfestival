<?php
/** Admin chrome. Call admin_head($title, $activeKey) and admin_foot(). */
require_once dirname(__DIR__) . '/includes/bootstrap.php';

function admin_head(string $title, string $active = ''): void
{
    $admin = current_admin();
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Robots-Tag: noindex');
    $items = [
        'overview'    => ['index.php', 'fa-chart-line', 'Overview', 'viewer'],
        'entrants'    => ['entrants.php', 'fa-users', 'Entrants', 'viewer'],
        'restores'    => ['restores.php', 'fa-rotate-left', 'Restoration', 'viewer'],
        'draws'       => ['draws.php', 'fa-trophy', 'Draws', 'viewer'],
        'subscribers' => ['subscribers.php', 'fa-envelope-open-text', 'Mailing list', 'admin'],
        'campaigns'   => ['campaigns.php', 'fa-paper-plane', 'Email campaigns', 'admin'],
        'media'       => ['media.php', 'fa-photo-film', 'Website media', 'admin'],
        'settings'    => ['settings.php', 'fa-sliders', 'Settings', 'admin'],
        'users'       => ['users.php', 'fa-user-shield', 'Team & access', 'admin'],
        'audit'       => ['audit.php', 'fa-list-check', 'Audit log', 'admin'],
    ];
    ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e($title) ?> — FLY FOR TWO admin</title>
<link rel="icon" type="image/png" href="<?= e(url('assets/img/brand/gmf-logo-orange.png')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Oswald:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="<?= e(asset('assets/css/admin.css')) ?>">
</head>
<body>
<div class="a-wrap">
  <aside class="a-side" id="aSide">
    <a class="a-brand" href="<?= e(url('admin/index.php')) ?>">
      <img src="<?= e(url('assets/img/brand/gmf-logo-orange.png')) ?>" alt="GMF">
      <span>Fly For Two<small>Control room</small></span>
    </a>
    <nav>
      <?php foreach ($items as $key => [$href, $icon, $label, $role]):
          if ($role === 'admin' && ($admin['role'] ?? '') !== 'admin') continue; ?>
        <a href="<?= e(url('admin/' . $href)) ?>" class="<?= $active === $key ? 'is-active' : '' ?>"><i class="fa-solid <?= $icon ?>"></i><?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="a-side-foot">
      <a href="<?= e(url('flyfortwo/')) ?>" target="_blank"><i class="fa-solid fa-arrow-up-right-from-square"></i>Competition page</a>
      <a href="<?= e(url('index.php')) ?>" target="_blank"><i class="fa-solid fa-globe"></i>Website</a>
    </div>
  </aside>
  <div class="a-main">
    <header class="a-top">
      <button class="a-burger" onclick="document.body.classList.toggle('side-open')" aria-label="Menu"><i class="fa-solid fa-bars"></i></button>
      <h1><?= e($title) ?></h1>
      <div class="a-user">
        <span class="a-role a-role-<?= e($admin['role'] ?? '') ?>"><?= ($admin['role'] ?? '') === 'admin' ? 'GMF admin' : 'Read-only' ?></span>
        <span class="a-name"><?= e($admin['name'] ?? '') ?></span>
        <a href="<?= e(url('admin/logout.php')) ?>" title="Sign out"><i class="fa-solid fa-right-from-bracket"></i></a>
      </div>
    </header>
    <?php foreach (flashes() as $f): ?>
      <div class="a-flash a-flash-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
    <?php endforeach; ?>
    <div class="a-content">
<?php
}

function admin_foot(): void
{
    ?>
    </div>
  </div>
</div>
</body>
</html>
<?php
}

/** Simple pager links preserving the current query string. */
function admin_pager(int $page, int $pages): string
{
    if ($pages <= 1) {
        return '';
    }
    $out = '<div class="a-pager">';
    for ($i = max(1, $page - 3); $i <= min($pages, $page + 3); $i++) {
        $qs = http_build_query(array_merge($_GET, ['page' => $i]));
        $out .= '<a class="' . ($i === $page ? 'is-active' : '') . '" href="?' . e($qs) . '">' . $i . '</a>';
    }
    return $out . '<span>of ' . $pages . '</span></div>';
}

function csv_download(string $filename, array $header, iterable $rows): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // Excel-friendly UTF-8
    fputcsv($out, $header);
    foreach ($rows as $r) {
        // Neutralise spreadsheet formula injection in user-supplied text.
        fputcsv($out, array_map(fn ($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v, array_values($r)));
    }
    fclose($out);
    exit;
}
