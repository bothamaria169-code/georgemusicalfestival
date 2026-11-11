<?php
/** Public live feed for the ticker: new entrants and friends joining through a link (masked names only). */
require dirname(__DIR__) . '/includes/bootstrap.php';

header('Cache-Control: public, max-age=20');
$cache = APP_ROOT . '/storage/logs/activity-cache.json';
if (is_file($cache) && filemtime($cache) > time() - 20) {
    header('Content-Type: application/json; charset=utf-8');
    readfile($cache);
    exit;
}
$payload = [
    'ok'       => true,
    'entrants' => (int) q("SELECT COUNT(*) FROM entrants WHERE status = 'verified'")->fetchColumn(),
    'items'    => recent_activity(15),
];
@file_put_contents($cache, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
json_out($payload);
