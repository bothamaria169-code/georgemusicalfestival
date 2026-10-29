<?php
/**
 * Sends the next batch of campaign emails. Run from cron every 5 minutes:
 *   php /home/georgemu/public_html/cron/send-campaigns.php
 * Optional argument: batch size (default 40). Keep batches small to stay inside the mailbox's hourly limit.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../includes/bootstrap.php';

[$sent, $failed] = campaign_send_batch(max(1, (int) ($argv[1] ?? 40)));
if ($sent || $failed) {
    echo date('Y-m-d H:i:s') . " campaigns: {$sent} sent, {$failed} failed\n";
}
