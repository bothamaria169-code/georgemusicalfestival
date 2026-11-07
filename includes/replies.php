<?php
/**
 * Replies to GMF emails: read new messages from the sending mailbox over IMAP (read-only, nothing
 * is marked or deleted), keep them in email_replies for the admin Replies page, link them to the
 * campaign they answer, and stop emailing anyone who asks to be removed.
 */
declare(strict_types=1);

const REPLY_STOP_WORDS = '/\b(unsubscribe|remove me|take me off|stop (emailing|sending)|opt[ -]?out|do not (email|contact)|don\'t (email|contact))\b/i';

function replies_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    q("CREATE TABLE IF NOT EXISTS email_replies (
        id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        uid          INT UNSIGNED NOT NULL,
        from_email   VARCHAR(190) NOT NULL,
        from_name    VARCHAR(190) NULL,
        subject      VARCHAR(255) NULL,
        body         MEDIUMTEXT   NULL,
        brand        VARCHAR(10)  NULL,
        campaign_id  INT UNSIGNED NULL,
        unsubscribe  TINYINT(1)   NOT NULL DEFAULT 0,
        status       ENUM('new','done') NOT NULL DEFAULT 'new',
        received_at  DATETIME     NULL,
        created_at   DATETIME     NOT NULL,
        UNIQUE KEY uq_uid (uid),
        KEY ix_status (status, received_at)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

/** Decodes a MIME body part by its Content-Transfer-Encoding and charset into UTF-8 text. */
function reply_decode(string $text, string $encoding, string $charset): string
{
    $encoding = strtolower(trim($encoding));
    if ($encoding === 'base64') {
        $text = (string) base64_decode(preg_replace('/\s+/', '', $text) ?? '', false);
    } elseif ($encoding === 'quoted-printable') {
        $text = quoted_printable_decode($text);
    }
    $charset = strtolower(trim($charset, " \"'")) ?: 'utf-8';
    if ($charset !== 'utf-8' && $charset !== 'us-ascii') {
        $converted = @mb_convert_encoding($text, 'UTF-8', $charset);
        if (is_string($converted)) {
            $text = $converted;
        }
    }
    return $text;
}

/** Header lines (unfolded) of a MIME block, keyed lower-case. */
function reply_headers(string $block): array
{
    $out = [];
    foreach (preg_split("/\r?\n(?![ \t])/", trim($block)) ?: [] as $line) {
        if (str_contains($line, ':')) {
            [$k, $v] = explode(':', $line, 2);
            $out[strtolower(trim($k))] = trim(preg_replace('/\r?\n[ \t]+/', ' ', $v) ?? '');
        }
    }
    return $out;
}

/** The readable text of a message body: plain text if there is one, otherwise HTML stripped to text. */
function reply_text(string $contentType, string $encoding, string $body): string
{
    $charset = preg_match('/charset="?([^";\s]+)/i', $contentType, $m) ? $m[1] : 'utf-8';
    if (preg_match('/boundary="?([^";]+)"?/i', $contentType, $m)) {
        $parts = explode('--' . $m[1], $body);
        $plain = $html = '';
        foreach ($parts as $part) {
            [$head, $content] = array_pad(preg_split("/\r?\n\r?\n/", $part, 2) ?: [], 2, '');
            $h = reply_headers($head);
            $type = strtolower($h['content-type'] ?? '');
            if (str_starts_with($type, 'multipart/')) {
                $nested = reply_text($h['content-type'], $h['content-transfer-encoding'] ?? '', $content);
                $plain = $plain ?: $nested;
            } elseif (str_starts_with($type, 'text/plain') && $plain === '') {
                $plain = reply_text($h['content-type'], $h['content-transfer-encoding'] ?? '', $content);
            } elseif (str_starts_with($type, 'text/html') && $html === '') {
                $html = reply_text($h['content-type'], $h['content-transfer-encoding'] ?? '', $content);
            }
        }
        return $plain !== '' ? $plain : $html;
    }
    $text = reply_decode($body, $encoding, $charset);
    if (stripos($contentType, 'text/html') !== false) {
        $text = preg_replace('#<(br|/p|/div|/tr|/h\d)[^>]*>#i', "\n", $text) ?? $text;
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    return $text;
}

/** Drops the quoted earlier message ("On … wrote:" and > lines) so only the new reply remains. */
function reply_strip_quote(string $text): string
{
    $text = str_replace("\r", '', $text);
    $cut = preg_split('/^(On .{5,200}wrote:|-{2,} ?Original Message ?-{2,}|From: .*georgemusicalfestival\.co\.za.*|Sent from my .*)$/mi', $text, 2);
    $lines = array_filter(explode("\n", $cut[0] ?? $text), fn ($l) => !str_starts_with(ltrim($l), '>'));
    return trim(preg_replace("/\n{3,}/", "\n\n", implode("\n", $lines)) ?? '');
}

/** One stored reply from the raw header and body of a message. */
function reply_parse(string $rawHeader, string $rawBody): array
{
    $h = reply_headers($rawHeader);
    $from = (string) ($h['from'] ?? '');
    $email = preg_match('/[A-Za-z0-9._%+\'-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/', $from, $m) ? mb_strtolower($m[0]) : '';
    $name = trim(preg_replace('/<[^>]*>/', '', mb_decode_mimeheader($from)) ?? '', " \"'");
    $subject = trim(mb_decode_mimeheader((string) ($h['subject'] ?? '')));
    $body = reply_strip_quote(reply_text((string) ($h['content-type'] ?? 'text/plain'), (string) ($h['content-transfer-encoding'] ?? ''), $rawBody));
    $date = strtotime((string) ($h['date'] ?? ''));
    return [
        'from_email'  => $email,
        'from_name'   => $name === $email ? '' : mb_substr($name, 0, 190),
        'subject'     => mb_substr($subject, 0, 255),
        'body'        => mb_substr($body, 0, 20000),
        'received_at' => $date ? date('Y-m-d H:i:s', $date) : null,
        'unsubscribe' => (int) (preg_match(REPLY_STOP_WORDS, $subject . "\n" . $body) === 1),
        'is_reply'    => isset($h['in-reply-to']) || preg_match('/^(re|aw)\s*:/i', $subject) === 1,
        'auto'        => preg_match('/^(auto-submitted|x-autoreply|x-autorespond)$/', implode("\n", array_keys($h))) === 1
                         || preg_match('/^(auto|out of office|automatic reply|undeliver|delivery status)/i', $subject) === 1,
    ];
}

/** True when GMF has emailed this address, or it belongs to an entrant, a lead or the mailing list. */
function reply_known_sender(string $email): bool
{
    foreach (['email_queue', 'entrants', 'subscribers', 'leads'] as $table) {
        try {
            if (q("SELECT 1 FROM {$table} WHERE email = ? LIMIT 1", [$email])->fetchColumn()) {
                return true;
            }
        } catch (Throwable $e) {
            // a table this database does not have
        }
    }
    return false;
}

/** The campaign a reply answers, matched on its subject ("Re: <campaign subject>"). */
function reply_campaign(string $subject): ?array
{
    $clean = trim(preg_replace('/^((re|fw|fwd|aw)\s*:\s*)+/i', '', $subject) ?? '');
    $clean = trim(preg_replace('/^\[test\]\s*/i', '', $clean) ?? '');
    if ($clean === '') {
        return null;
    }
    $row = q('SELECT id, brand FROM email_campaigns WHERE subject = ? ORDER BY id DESC LIMIT 1', [$clean])->fetch();
    return $row ?: null;
}

/**
 * Reads new messages from the mailbox and stores the replies. Returns [new replies, unsubscribes].
 * Mailer-daemon reports are left to check-bounces.php; automatic replies are skipped.
 */
function replies_fetch(int $max = 100): array
{
    campaign_schema();
    replies_schema();
    $mail = (array) config('mail');
    if ((string) ($mail['password'] ?? '') === '') {
        throw new RuntimeException('The mailbox password is not set in config/config.php.');
    }
    $host = (string) ($mail['imap_host'] ?? 'imap.secureserver.net');
    $port = (int) ($mail['imap_port'] ?? 993);
    $sock = @stream_socket_client("ssl://{$host}:{$port}", $errno, $errstr, 20);
    if (!$sock) {
        throw new RuntimeException("Could not reach the mailbox ({$errstr}).");
    }
    stream_set_timeout($sock, 40);
    fgets($sock);
    $tag = 0;
    $cmd = function (string $command) use ($sock, &$tag): string {
        $t = 'r' . (++$tag);
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
    if (!preg_match('/^r1 OK/m', $cmd('LOGIN ' . $quote((string) $mail['username']) . ' ' . $quote((string) $mail['password'])))) {
        throw new RuntimeException('The mailbox refused the sign-in.');
    }
    $cmd('EXAMINE INBOX');   // read-only: nothing is marked as read
    $last = (int) setting('replies_last_uid', 0);
    if ($last === 0) {
        // First run: start from the last 7 days instead of the whole mailbox history.
        $since = $cmd('UID SEARCH SINCE ' . date('j-M-Y', strtotime('-7 days')));
        preg_match('/^\* SEARCH([\d ]*)/m', $since, $m);
        $first = array_filter(array_map('intval', preg_split('/\s+/', trim($m[1] ?? '')) ?: []));
        $last = $first ? min($first) - 1 : 0;
    }
    $search = $cmd('UID SEARCH UID ' . ($last + 1) . ':*');
    preg_match('/^\* SEARCH([\d ]*)/m', $search, $m);
    $uids = array_slice(array_values(array_filter(array_map('intval', preg_split('/\s+/', trim($m[1] ?? '')) ?: []), fn ($u) => $u > $last)), 0, $max);

    $own = mb_strtolower((string) ($mail['from_email'] ?? $mail['username'] ?? ''));
    $added = $stopped = 0;
    foreach ($uids as $uid) {
        $raw = $cmd("UID FETCH {$uid} (BODY.PEEK[HEADER] BODY.PEEK[TEXT]<0.60000>)");
        $literals = [];
        $offset = 0;
        while (preg_match('/\{(\d+)\}\r\n/', $raw, $lm, PREG_OFFSET_CAPTURE, $offset)) {
            $start = $lm[0][1] + strlen($lm[0][0]);
            $literals[] = substr($raw, $start, (int) $lm[1][0]);
            $offset = $start + (int) $lm[1][0];
        }
        $last = max($last, $uid);
        if (count($literals) < 2) {
            continue;
        }
        $r = reply_parse($literals[0], $literals[1]);
        if ($r['from_email'] === '' || $r['from_email'] === $own || $r['auto'] || preg_match('/mailer-daemon|postmaster|no-?reply/i', $r['from_email'])) {
            continue;
        }
        $campaign = reply_campaign($r['subject']);
        if (!$campaign && !$r['is_reply'] && !reply_known_sender($r['from_email'])) {
            continue;   // ordinary mail to info@ (suppliers, invoices) stays out of the Replies page
        }
        $st = q('INSERT IGNORE INTO email_replies (uid, from_email, from_name, subject, body, brand, campaign_id, unsubscribe, status, received_at, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
            $uid, $r['from_email'], $r['from_name'] ?: null, $r['subject'], $r['body'], $campaign['brand'] ?? null, $campaign['id'] ?? null,
            $r['unsubscribe'], $r['unsubscribe'] ? 'done' : 'new', $r['received_at'], now(),
        ]);
        if ($st->rowCount()) {
            $added++;
            if ($r['unsubscribe']) {
                q('INSERT IGNORE INTO email_suppressions (email, reason, created_at) VALUES (?, ?, ?)', [$r['from_email'], 'replied stop', now()]);
                q("UPDATE email_queue SET status = 'skipped', error = 'asked to be removed' WHERE email = ? AND status = 'queued'", [$r['from_email']]);
                $stopped++;
            }
        }
    }
    $cmd('LOGOUT');
    save_setting('replies_last_uid', (string) $last);
    save_setting('replies_checked_at', now());
    return [$added, $stopped];
}
