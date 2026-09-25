<?php
/**
 * Read-only reporting API for ACSA (standing access to the database, not a one-off dump).
 * Auth: X-Api-Key header or ?key=  (config: partner_api_key)
 *
 *   GET api/report.php                     → summary counts (by channel, day, town, activation point, hour)
 *   GET api/report.php?view=entrants&page=1&per_page=500&since=2026-10-01
 *                                          → paginated verified entrant records with consent & attribution
 */
require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/reports.php';

$key = (string) ($_SERVER['HTTP_X_API_KEY'] ?? $_GET['key'] ?? '');
if (!config('partner_api_key') || !hash_equals((string) config('partner_api_key'), $key)) {
    json_out(['ok' => false, 'error' => 'Invalid API key'], 401);
}
if (!rate_limit('report_api', client_ip(), 120, 3600)) {
    json_out(['ok' => false, 'error' => 'Rate limit exceeded'], 429);
}

if (($_GET['view'] ?? '') === 'entrants') {
    $per   = max(1, min(1000, (int) ($_GET['per_page'] ?? 500)));
    $page  = max(1, (int) ($_GET['page'] ?? 1));
    $since = !empty($_GET['since']) ? date('Y-m-d H:i:s', strtotime((string) $_GET['since'])) : '1970-01-01 00:00:00';
    $total = (int) q("SELECT COUNT(*) FROM entrants WHERE status = 'verified' AND verified_at >= ?", [$since])->fetchColumn();
    $rows  = q("SELECT id, full_name, email, phone, town, age_group, fly_frequency, consent_terms, consent_popia, consent_marketing, consent_at,
                       verified_at, verified_via, channel, activation_point, source_town, time_slot, utm_source, utm_medium, utm_campaign,
                       referred_by, created_at,
                       (SELECT COUNT(*) FROM entries e WHERE e.entrant_id = entrants.id) AS entries
                FROM entrants WHERE status = 'verified' AND verified_at >= ?
                ORDER BY id LIMIT {$per} OFFSET " . (($page - 1) * $per), [$since])->fetchAll();
    json_out(['ok' => true, 'page' => $page, 'per_page' => $per, 'total' => $total, 'data' => $rows]);
}

json_out(['ok' => true, 'generated_at' => date('c'), 'summary' => report_summary()]);
