<?php
/** POST: the "Chat with us" form. Same-site only (CSRF token from the page), with a honeypot and rate limits. */
declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/support.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'error' => 'Method not allowed'], 405);
}
$in = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($in)) {
    $in = $_POST;
}
if (!csrf_ok((string) ($in['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')))) {
    json_out(['ok' => false, 'error' => 'Your session expired. Please refresh the page and try again.'], 400);
}
if (!empty($in['website'])) {
    json_out(['ok' => true, 'id' => 0]); // honeypot: pretend it worked
}
try {
    $res = support_submit($in);
} catch (Throwable $e) {
    error_log('support: ' . $e->getMessage());
    $res = ['ok' => false, 'error' => 'Something went wrong. Please try again in a minute.'];
}
json_out($res, $res['ok'] ? 200 : 422);
