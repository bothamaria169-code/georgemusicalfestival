<?php
/**
 * Minimal mail sender: SMTP (SSL or STARTTLS with AUTH LOGIN), PHP mail(), or log-to-file.
 * Kept dependency-free so the platform uploads to any cPanel host as-is.
 */
declare(strict_types=1);

function send_mail(string $to, string $subject, string $html, string $text = ''): bool
{
    $driver = config('mail.driver', 'log');
    if ($text === '') {
        // Plain-text part: keep links visible ("Confirm my entry: https://…") and paragraphs on their own lines.
        $plain = preg_replace('#<a\s[^>]*href="([^"]+)"[^>]*>(.*?)</a>#is', '$2: $1', $html);
        $plain = preg_replace('#<br\s*/?>|</p>|</h1>|</tr>#i', "\n", (string) $plain);
        $plain = html_entity_decode(strip_tags((string) $plain), ENT_QUOTES, 'UTF-8');
        $text = trim(preg_replace("/[ \t]+/", ' ', preg_replace("/\n\s*\n+/", "\n\n", $plain)));
    }

    try {
        if ($driver === 'smtp') {
            return smtp_send($to, $subject, $html, $text);
        }
        if ($driver === 'mail') {
            [$headers, $body] = mail_build_mime($to, $subject, $html, $text, false);
            return mail($to, mail_encode_header($subject), $body, $headers);
        }
    } catch (Throwable $e) {
        mail_log("FAILED to {$to}: " . $e->getMessage());
        return false;
    }

    mail_log("TO: {$to}\nSUBJECT: {$subject}\n{$text}\n");
    return true;
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
    $fromEmail = config('mail.from_email');
    $fromName = mail_encode_header((string) config('mail.from_name', 'George Music Festival'));
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
    $host = (string) config('mail.host');
    $port = (int) config('mail.port', 465);
    $enc  = (string) config('mail.encryption', 'ssl');
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
    if (config('mail.username')) {
        $cmd('AUTH LOGIN', [334]);
        $cmd(base64_encode((string) config('mail.username')), [334]);
        $cmd(base64_encode((string) config('mail.password')), [235]);
    }
    $cmd('MAIL FROM:<' . config('mail.from_email') . '>', [250]);
    $cmd('RCPT TO:<' . $to . '>', [250, 251]);
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
    $year = date('Y');
    return <<<HTML
<!doctype html><html><body style="margin:0;background:#f4efe6;font-family:Arial,Helvetica,sans-serif;color:#1d1a16;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4efe6;padding:28px 12px;"><tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#ffffff;border-radius:14px;overflow:hidden;">
<tr><td style="background:#0b1d2a;padding:22px 28px;"><img src="{$logo}" alt="George Music Festival" height="44" style="display:block;height:44px;"></td></tr>
<tr><td style="padding:30px 28px 10px;"><h1 style="margin:0 0 14px;font-size:22px;line-height:1.25;color:#0b1d2a;">{$heading}</h1>{$bodyHtml}</td></tr>
<tr><td style="padding:18px 28px 26px;font-size:12px;color:#7a7266;border-top:1px solid #eee4d3;">FLY FOR TWO · George Music Festival × Airports Company South Africa<br>&copy; {$year} George Music Festival</td></tr>
</table></td></tr></table></body></html>
HTML;
}
