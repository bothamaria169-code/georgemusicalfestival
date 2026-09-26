<?php
/**
 * Reads delivery-failure reports ("Message Delivery Failure" from mailer-daemon) from the sending
 * mailbox over IMAP and flags the matching entrants in the admin. Read-only: messages are not
 * changed or deleted. Run from cron every 10 minutes:
 *   php /var/www/gmf/cron/check-bounces.php
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../includes/bootstrap.php';

$mail = config('mail');
$host = (string) ($mail['imap_host'] ?? 'imap.secureserver.net');
$port = (int) ($mail['imap_port'] ?? 993);
$stateFile = APP_ROOT . '/storage/bounce-state.json';
$state = is_file($stateFile) ? (json_decode((string) file_get_contents($stateFile), true) ?: []) : [];
$lastUid = (int) ($state['last_uid'] ?? 0);

$sock = @stream_socket_client("ssl://{$host}:{$port}", $errno, $errstr, 20);
if (!$sock) {
    fwrite(STDERR, "IMAP connect failed: {$errstr}\n");
    exit(1);
}
stream_set_timeout($sock, 30);
fgets($sock);
$tag = 0;
$cmd = function (string $command) use ($sock, &$tag): string {
    $t = 'b' . (++$tag);
    fwrite($sock, "{$t} {$command}\r\n");
    $out = '';
    while (($line = fgets($sock)) !== false) {
        $out .= $line;
        if (str_starts_with($line, "{$t} ")) {
            break;
        }
    }
    return $out;
};
$quote = fn (string $s) => '"' . addcslashes($s, "\"\\") . '"';

$login = $cmd('LOGIN ' . $quote((string) $mail['username']) . ' ' . $quote((string) $mail['password']));
if (!preg_match('/^b1 OK/m', $login)) {
    fwrite(STDERR, "IMAP login failed\n");
    exit(1);
}
$cmd('EXAMINE INBOX');   // read-only
$search = $cmd('UID SEARCH UID ' . ($lastUid + 1) . ':* FROM "mailer-daemon"');
preg_match('/^\* SEARCH([\d ]*)/m', $search, $m);
$uids = array_values(array_filter(array_map('intval', preg_split('/\s+/', trim($m[1] ?? ''))), fn ($u) => $u > $lastUid));

$flagged = 0;
foreach ($uids as $uid) {
    $raw = $cmd("UID FETCH {$uid} (BODY.PEEK[TEXT]<0.20000>)");
    foreach (parse_bounce($raw) as $b) {
        if (!$b['permanent']) {
            continue;   // temporary failures (mailbox busy, greylisting) usually deliver later
        }
        $n = mark_email_problem($b['email'], 'bounced', $b['reason']);
        if ($n) {
            $flagged += $n;
            audit('email.bounced', ['email' => mask_email($b['email']), 'reason' => $b['reason']]);
        }
    }
    $lastUid = max($lastUid, $uid);
}
$cmd('LOGOUT');

@file_put_contents($stateFile, json_encode(['last_uid' => $lastUid, 'checked_at' => date('c')]));
echo date('c') . ' checked ' . count($uids) . " bounce report(s), flagged {$flagged} entrant(s)\n";
