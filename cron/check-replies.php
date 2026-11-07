<?php
/**
 * Saves replies to GMF emails into the admin Replies page. Read-only on the mailbox. Run from cron
 * every 10 minutes:
 *   /usr/local/bin/php /home/georgemu/public_html/cron/check-replies.php
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/replies.php';

try {
    [$added, $stopped] = replies_fetch(200);
    if ($added) {
        echo date('Y-m-d H:i:s') . " replies: {$added} new" . ($stopped ? ", {$stopped} asked to be removed" : '') . "\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, date('Y-m-d H:i:s') . ' replies: ' . $e->getMessage() . "\n");
    exit(1);
}
