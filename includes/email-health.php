<?php
/**
 * Email health: catch mistyped addresses at entry, and flag entrants whose emails bounce.
 * Bounces are read from the sending mailbox by cron/check-bounces.php.
 */
declare(strict_types=1);

/** Common misspellings of popular email domains → the domain people meant. */
const EMAIL_DOMAIN_TYPOS = [
    'gmail.co' => 'gmail.com', 'gmail.con' => 'gmail.com', 'gmail.cm' => 'gmail.com', 'gmail.om' => 'gmail.com', 'gmail.comm' => 'gmail.com',
    'gmial.com' => 'gmail.com', 'gmai.com' => 'gmail.com', 'gamil.com' => 'gmail.com', 'gnail.com' => 'gmail.com', 'gmal.com' => 'gmail.com',
    'gmaill.com' => 'gmail.com', 'gmail.co.za' => 'gmail.com', 'gmailcom' => 'gmail.com', 'gmsil.com' => 'gmail.com', 'fmail.com' => 'gmail.com',
    'yahoo.con' => 'yahoo.com', 'yaho.com' => 'yahoo.com', 'yahho.com' => 'yahoo.com', 'yahoo.cm' => 'yahoo.com',
    'hotmial.com' => 'hotmail.com', 'hotmai.com' => 'hotmail.com', 'hotmail.con' => 'hotmail.com', 'hotmal.com' => 'hotmail.com',
    'outlook.con' => 'outlook.com', 'outlok.com' => 'outlook.com', 'outloo.com' => 'outlook.com',
    'icloud.co' => 'icloud.com', 'iclod.com' => 'icloud.com', 'icoud.com' => 'icloud.com', 'icloud.con' => 'icloud.com',
];

/** A likely correction for a mistyped domain, e.g. "thandi@gmial.com" → "thandi@gmail.com", or null. */
function email_typo_suggestion(string $email): ?string
{
    [$user, $domain] = array_pad(explode('@', mb_strtolower($email), 2), 2, '');
    $fix = EMAIL_DOMAIN_TYPOS[$domain] ?? null;
    return $fix ? $user . '@' . $fix : null;
}

/**
 * True only when DNS clearly says the domain has no mail or web records (it cannot receive email).
 * A failed lookup counts as fine, so a DNS glitch never blocks a genuine entrant.
 */
function email_domain_missing(string $email): bool
{
    $domain = substr((string) strrchr($email, '@'), 1);
    if ($domain === '' || !function_exists('dns_get_record')) {
        return false;
    }
    static $cache = [];
    if (!isset($cache[$domain])) {
        $mx = @dns_get_record($domain . '.', DNS_MX);
        $a  = @dns_get_record($domain . '.', DNS_A);
        $cache[$domain] = is_array($mx) && is_array($a) && !$mx && !$a;
    }
    return $cache[$domain];
}

/** Friendly problem text for an entry form, or null when the email looks deliverable. */
function email_entry_problem(string $email): ?string
{
    if ($fix = email_typo_suggestion($email)) {
        return "Did you mean {$fix}? Please check the spelling of your email address.";
    }
    if (email_domain_missing($email)) {
        $domain = substr((string) strrchr($email, '@'), 1);
        return "We can't deliver email to \"@{$domain}\". Please check the spelling of your email address.";
    }
    return null;
}

/** Record a delivery problem on every entrant with this email. $status: bounced | invalid | send_failed. */
function mark_email_problem(string $email, string $status, string $reason): int
{
    return q('UPDATE entrants SET email_status = ?, email_issue = ?, email_issue_at = ? WHERE email = ?',
        [$status, mb_substr($reason, 0, 255), now(), mb_strtolower(trim($email))])->rowCount();
}

/** Clear a delivery problem (after an admin fixes the address, or when mail gets through). */
function clear_email_problem(int $entrantId): void
{
    q('UPDATE entrants SET email_status = NULL, email_issue = NULL, email_issue_at = NULL WHERE id = ?', [$entrantId]);
}

/** The server's response quoted in the human-readable part of a failure report. */
function bounce_text_reason(string $raw): string
{
    if (preg_match('/destination host IP "DNSNULL"/i', $raw)) {
        return 'dnsnull: the email domain has no mail server';
    }
    return preg_match('/response from the destination host[^:]*:\s*"([^"]+)"/is', $raw, $d) ? str_replace(['\r\n', '
'], ' ', $d[1]) : '';
}

/** Plain-language reason from an SMTP status / diagnostic line. */
function bounce_reason(string $diagnostic): string
{
    $d = strtolower($diagnostic);
    return match (true) {
        str_contains($d, '5.1.1') || str_contains($d, 'does not exist') || str_contains($d, 'user unknown') || str_contains($d, 'no such user')
            || str_contains($d, 'recipient address rejected') || str_contains($d, 'invalid recipient') => 'Email address does not exist',
        str_contains($d, 'dnsnull') || str_contains($d, '5.1.2') || str_contains($d, 'domain') && str_contains($d, 'not found') || str_contains($d, 'host unknown') => 'Email domain does not exist',
        str_contains($d, '5.2.2') || str_contains($d, 'quota') || str_contains($d, 'mailbox full') => 'Mailbox is full',
        str_contains($d, '5.7.') || str_contains($d, 'blocked') || str_contains($d, 'spam') => 'Rejected by the receiving server (blocked or treated as spam)',
        default => 'Delivery failed: ' . trim(preg_replace('/\s+/', ' ', mb_substr($diagnostic, 0, 160))),
    };
}

/**
 * Parse one delivery-failure report. Returns [['email' => ..., 'reason' => ..., 'permanent' => bool], ...].
 * Handles the standard message/delivery-status part (Final-Recipient / Diagnostic-Code / Status).
 */
function parse_bounce(string $raw): array
{
    $out = [];
    if (preg_match_all('/Final-Recipient:\s*rfc822;\s*<?([^\s<>;]+@[^\s<>;]+)>?(.*?)(?=Final-Recipient:|\z)/is', $raw, $m, PREG_SET_ORDER)) {
        foreach ($m as $block) {
            $diag = preg_match('/Diagnostic-Code:[ 	]*[a-z]+;[ 	]*(.*)/i', $block[2], $d) ? trim($d[1]) : '';
            if ($diag === '') {
                $diag = bounce_text_reason($raw);   // some reports leave the code empty and explain it in the text part
            }
            $status = preg_match('/^Status:\s*([245]\.\d+\.\d+)/mi', $block[2], $s) ? $s[1] : (preg_match('/\b([245]\.\d+\.\d+)\b/', $diag, $s) ? $s[1] : '');
            $permanent = $status === '' ? true : $status[0] === '5';
            $out[] = ['email' => mb_strtolower(trim($block[1])), 'reason' => bounce_reason($diag . ' ' . $status), 'permanent' => $permanent];
        }
    } elseif (preg_match('/failed permanently:\s*\*?\s*<?([^\s<>]+@[^\s<>]+)>?/i', $raw, $m)) {
        $diag = bounce_text_reason($raw);
        $out[] = ['email' => mb_strtolower($m[1]), 'reason' => bounce_reason($diag), 'permanent' => true];
    }
    return $out;
}

/** Friendly label for the admin. */
function email_problem_label(?string $status): string
{
    return match ($status) {
        'bounced'     => 'Email bounced',
        'invalid'     => 'Email invalid',
        'send_failed' => 'Email not sent',
        default       => '',
    };
}
