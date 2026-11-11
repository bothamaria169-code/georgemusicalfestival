<?php
/**
 * Minimal mail sender: SMTP (SSL or STARTTLS with AUTH LOGIN), PHP mail(), or log-to-file.
 * Kept dependency-free so the platform uploads to any cPanel host as-is.
 */
declare(strict_types=1);

function send_mail(string $to, string $subject, string $html, string $text = ''): bool
{
    $GLOBALS['__mail_error'] = '';
    if ($text === '') {
        // Plain-text part: keep links visible ("Confirm my entry: https://…") and paragraphs on their own lines.
        $plain = preg_replace('#<a\s[^>]*href="([^"]+)"[^>]*>(.*?)</a>#is', '$2: $1', $html);
        $plain = preg_replace('#<br\s*/?>|</p>|</h1>|</tr>#i', "
", (string) $plain);
        $plain = html_entity_decode(strip_tags((string) $plain), ENT_QUOTES, 'UTF-8');
        $text = trim(preg_replace("/[ 	]+/", ' ', preg_replace("/
\s*
+/", "

", $plain)));
    }

    $candidates = mail_candidates();
    if (!$candidates) {
        mail_log("TO: {$to}
SUBJECT: {$subject}
{$text}
");
        return true;
    }
    $errors = [];
    foreach ($candidates as $label => $settings) {
        $GLOBALS['__mail_active'] = $settings;
        try {
            if (($settings['driver'] ?? '') === 'mail') {
                [$headers, $body] = mail_build_mime($to, $subject, $html, $text, false);
                if (!mail($to, mail_encode_header($subject), $body, $headers)) {
                    throw new RuntimeException('PHP mail() refused the message');
                }
            } else {
                smtp_send($to, $subject, $html, $text);
            }
            if ($errors) {
                mail_log("SENT to {$to} using the {$label} mailbox after: " . implode(' | ', $errors));
            }
            unset($GLOBALS['__mail_active']);
            return true;
        } catch (Throwable $e) {
            $errors[] = "{$label}: " . $e->getMessage();
        }
    }
    unset($GLOBALS['__mail_active']);
    $GLOBALS['__mail_error'] = implode(' | ', $errors);
    mail_log("FAILED to {$to}: " . $GLOBALS['__mail_error']);
    return false;
}

/** The last send_mail() failure reason, e.g. "SMTP error after AUTH: 535 Authentication failed". */
function mail_last_error(): string
{
    return (string) ($GLOBALS['__mail_error'] ?? '');
}

/** One mail setting from the mailbox being used right now (falls back to this app's config). */
function mcfg(string $key, $default = null)
{
    $m = $GLOBALS['__mail_active'] ?? config('mail', []);
    return is_array($m) && array_key_exists($key, $m) && $m[$key] !== '' && $m[$key] !== null ? $m[$key] : $default;
}

/**
 * Mailboxes to try, in order: this app's own settings, then the main GMF site's settings
 * (public_html/config/config.php), so a wrong or missing Cell C mail password never stops codes.
 * In production a 'log' driver is skipped, so codes are never silently written to a file.
 * Empty array = log only (local development).
 */
function mail_candidates(): array
{
    $list = [];
    $own = config('mail', []);
    $ownDriver = is_array($own) ? (string) ($own['driver'] ?? 'log') : 'log';
    if ($ownDriver === 'smtp' || $ownDriver === 'mail') {
        $list['Cell C'] = $own;
    }
    $mainFile = dirname(APP_ROOT) . '/config/config.php';
    if (config('env') === 'production' && is_file($mainFile)) {
        try {
            $main = (static fn (string $f) => require $f)($mainFile);
            $m = is_array($main) ? ($main['mail'] ?? null) : null;
            if (is_array($m) && ($m['driver'] ?? '') === 'smtp' && !empty($m['host'])
                && (!isset($list['Cell C']) || ($m['username'] ?? '') . ($m['password'] ?? '') . ($m['host'] ?? '') !== ($own['username'] ?? '') . ($own['password'] ?? '') . ($own['host'] ?? ''))) {
                $list['main site'] = $m;
            }
        } catch (Throwable $e) {
            // main site config unreadable: keep to our own settings
        }
    }
    return $list;
}

function mail_log(string $line): void
{
    $dir = APP_ROOT . '/storage/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    @file_put_contents($dir . '/mail.log', '[' . date('Y-m-d H:i:s') . "] {$line}\n", FILE_APPEND);
}

function mail_encode_header(string $value): string
{
    return preg_match('/[^\x20-\x7E]/', $value) ? '=?UTF-8?B?' . base64_encode($value) . '?=' : $value;
}

/** Returns [headers string, body string]. With $full the To/Subject/Date headers are included (SMTP DATA). */
function mail_build_mime(string $to, string $subject, string $html, string $text, bool $full): array
{
    $boundary = 'b' . bin2hex(random_bytes(12));
    $fromEmail = mcfg('from_email');
    $fromName = mail_encode_header((string) mcfg('from_name', 'George Musical Festival'));
    $domain = substr(strrchr((string) $fromEmail, '@') ?: '@localhost', 1);

    $h = [];
    if ($full) {
        $h[] = 'Date: ' . date('r');
        $h[] = 'To: <' . $to . '>';
        $h[] = 'Subject: ' . mail_encode_header($subject);
    }
    $h[] = "From: {$fromName} <{$fromEmail}>";
    $h[] = "Reply-To: {$fromEmail}";
    $h[] = 'Message-ID: <' . bin2hex(random_bytes(10)) . '@' . $domain . '>';
    $h[] = 'MIME-Version: 1.0';
    $h[] = "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";

    $body = "--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($text))
        . "--{$boundary}\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($html))
        . "--{$boundary}--\r\n";

    return [implode("\r\n", $h), $body];
}

function smtp_send(string $to, string $subject, string $html, string $text): bool
{
    $host = (string) mcfg('host');
    $port = (int) mcfg('port', 465);
    $enc  = (string) mcfg('encryption', 'ssl');
    $remote = ($enc === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;

    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true]]);
    $fp = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) {
        throw new RuntimeException("SMTP connect failed: {$errstr} ({$errno})");
    }
    stream_set_timeout($fp, 15);

    $read = function () use ($fp): string {
        $data = '';
        while (($line = fgets($fp, 515)) !== false) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $data;
    };
    $cmd = function (string $command, array $expect) use ($fp, $read): string {
        fwrite($fp, $command . "\r\n");
        $res = $read();
        if (!in_array((int) substr($res, 0, 3), $expect, true)) {
            throw new RuntimeException('SMTP error after "' . (str_starts_with($command, 'AUTH') ? 'AUTH' : strtok($command, ' ')) . '": ' . trim($res));
        }
        return $res;
    };

    $read();
    $ehloHost = $_SERVER['SERVER_NAME'] ?? 'localhost';
    $cmd('EHLO ' . $ehloHost, [250]);
    if ($enc === 'tls') {
        $cmd('STARTTLS', [220]);
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
            throw new RuntimeException('STARTTLS negotiation failed');
        }
        $cmd('EHLO ' . $ehloHost, [250]);
    }
    if (mcfg('username')) {
        $cmd('AUTH LOGIN', [334]);
        $cmd(base64_encode((string) mcfg('username')), [334]);
        $cmd(base64_encode((string) mcfg('password')), [235]);
    }
    $cmd('MAIL FROM:<' . mcfg('from_email') . '>', [250]);
    $cmd('RCPT TO:<' . $to . '>', [250, 251]);
    // Silent archive copy (no Bcc header), so a record of every email sent survives outside this server.
    $archive = (string) mcfg('archive_bcc', '');
    if ($archive !== '' && strcasecmp($archive, $to) !== 0 && filter_var($archive, FILTER_VALIDATE_EMAIL)) {
        $cmd('RCPT TO:<' . $archive . '>', [250, 251]);
    }
    $cmd('DATA', [354]);

    [$headers, $body] = mail_build_mime($to, $subject, $html, $text, true);
    $message = $headers . "\r\n\r\n" . $body;
    $message = preg_replace('/^\./m', '..', $message); // dot-stuffing
    $cmd($message . "\r\n.", [250]);
    $cmd('QUIT', [221]);
    fclose($fp);
    return true;
}

/** Branded HTML wrapper for transactional emails. */
function mail_template(string $heading, string $bodyHtml): string
{
    $logo = url('assets/img/brand/gmf-logo-orange.png');
    $acsa = url('assets/img/brand/cellc-white.png');
    $year = date('Y');
    return <<<HTML
<!doctype html><html><body style="margin:0;background:#f5f1ed;font-family:Arial,Helvetica,sans-serif;color:#161616;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5f1ed;padding:28px 12px;"><tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#ffffff;border-radius:14px;overflow:hidden;">
<tr><td style="background:#2b2b2b;padding:20px 28px;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
<td align="left"><img src="{$logo}" alt="George Musical Festival" height="44" style="display:block;height:44px;"></td>
<td align="right"><img src="{$acsa}" alt="Cell C" height="40" style="display:inline-block;height:40px;"></td></tr></table></td></tr>
<tr><td style="padding:30px 28px 10px;"><h1 style="margin:0 0 14px;font-size:22px;line-height:1.25;color:#1f1f1f;">{$heading}</h1>{$bodyHtml}</td></tr>
<tr><td style="padding:18px 28px 26px;font-size:12px;color:#6b645e;border-top:1px solid #ebe4dd;">WIN A SAMSUNG · George Musical Festival × Cell C<br>&copy; {$year} George Musical Festival</td></tr>
</table></td></tr></table></body></html>
HTML;
}
