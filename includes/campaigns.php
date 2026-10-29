<?php
/**
 * Bulk email: campaigns are queued per recipient and sent in small batches (cron or the admin
 * "Send next batch" button), so mailbox sending limits are respected and a failure never
 * stops the rest. Every email carries a personal unsubscribe link.
 *
 * Optional config 'bulk_mail' (same keys as 'mail') sends campaigns through a separate SMTP
 * service such as SMTP2GO or Brevo; without it campaigns use the normal 'mail' settings.
 */
declare(strict_types=1);

const CAMPAIGN_FIELDS = ['{first_name}', '{full_name}', '{share_link}', '{restore_link}', '{entry_link}'];

function campaign_restore_template(): array
{
    $comp = (string) setting('comp_name');
    $contact = (string) setting('contact_email');
    return [
        'name'    => 'Restore your entry',
        'subject' => "Your {$comp} entry — please restore it (your link still works)",
        'heading' => 'Hi {first_name}, please restore your entry',
        'body'    => "<p>Our hosting provider had a serious outage at the end of October and recent {$comp} entries were lost from our system. We're sorry for the trouble.</p>\n"
            . "<p><strong>Your personal link still works</strong>, and every person who has opened it since the site came back has been saved.</p>\n"
            . "<p>To get your entry and your score back, it takes about a minute:</p>\n"
            . "<ol>\n<li>Find the email <strong>\"You're entered — {$comp}\"</strong> we sent you and copy your link.</li>\n<li>Tap the button below and fill in your details with the same email and cellphone number.</li>\n<li>Enter the code we email you. Done.</li>\n</ol>\n"
            . "<p><a href=\"{restore_link}\" style=\"display:inline-block;background:#f7801a;color:#2a1402;text-decoration:none;font-weight:bold;padding:12px 20px;border-radius:10px;\">Restore my entry</a></p>\n"
            . "<p>Can't find the email? Restore anyway with the same email and cellphone number, and tell us what you remember. Questions: <a href=\"mailto:{$contact}\">{$contact}</a>.</p>",
    ];
}

function campaign_unsubscribe_token(string $email): string
{
    return substr(app_hash('unsub|' . mb_strtolower($email)), 0, 24);
}

function campaign_unsubscribe_link(string $email): string
{
    return url('unsubscribe') . '?' . http_build_query(['e' => mb_strtolower($email), 't' => campaign_unsubscribe_token($email)]);
}

function campaign_suppressed(string $email): bool
{
    return (bool) q('SELECT 1 FROM email_suppressions WHERE email = ?', [mb_strtolower($email)])->fetchColumn();
}

/** The finished email for one recipient: merge fields filled, branded wrapper, unsubscribe footer. */
function campaign_render(array $campaign, array $r): array
{
    $first = trim(explode(' ', trim((string) ($r['full_name'] ?? '')))[0] ?? '');
    $values = [
        e($first !== '' ? $first : 'there'),
        e((string) ($r['full_name'] ?? '')),
        e(!empty($r['ref_code']) ? url('r/' . $r['ref_code']) : url('flyfortwo/')),
        e(url('flyfortwo/restore') . (!empty($r['ref_code']) ? '?code=' . rawurlencode($r['ref_code']) : '')),
        e(url('flyfortwo/')),
    ];
    $body = str_replace(CAMPAIGN_FIELDS, $values, (string) $campaign['body_html']);
    $heading = str_replace(CAMPAIGN_FIELDS, $values, (string) $campaign['heading']);
    $subject = str_replace(CAMPAIGN_FIELDS, array_map('html_entity_decode', $values), (string) $campaign['subject']);
    $unsub = e(campaign_unsubscribe_link((string) $r['email']));
    $body .= "<p style=\"margin:22px 0 0;font-size:12px;color:#55707a;\">You're receiving this because you entered " . e((string) setting('comp_name'))
        . ". <a href=\"{$unsub}\" style=\"color:#55707a;\">Unsubscribe</a> from further emails like this.</p>";
    return [$subject, mail_template($heading, '<div style="font-size:15px;line-height:1.6;">' . $body . '</div>')];
}

/**
 * Send through the bulk SMTP service when configured, otherwise the normal mailbox.
 * Campaign emails are never archive-copied: that would double every send against the mailbox limit.
 */
function send_campaign_mail(string $to, string $subject, string $html): bool
{
    $saved = $GLOBALS['__config']['mail'];
    $bulk = config('bulk_mail');
    $GLOBALS['__config']['mail'] = (is_array($bulk) && !empty($bulk['host'])
        ? $bulk + ['driver' => 'smtp', 'from_name' => $saved['from_name'] ?? 'George Musical Festival']
        : $saved);
    $GLOBALS['__config']['mail']['archive_bcc'] = '';
    try {
        return send_mail($to, $subject, $html);
    } finally {
        $GLOBALS['__config']['mail'] = $saved;
    }
}

/**
 * Add recipients to a campaign. Each row: email, full_name, ref_code (optional).
 * Returns [added, skipped] — skipped = invalid, duplicate or unsubscribed.
 */
function campaign_add_recipients(int $campaignId, iterable $rows): array
{
    $added = $skipped = 0;
    $st = db()->prepare('INSERT IGNORE INTO email_queue (campaign_id, email, full_name, ref_code, status) VALUES (?, ?, ?, ?, ?)');
    foreach ($rows as $r) {
        $email = mb_strtolower(trim((string) ($r['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || campaign_suppressed($email)) {
            $skipped++;
            continue;
        }
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) ($r['ref_code'] ?? '')) ?? '');
        $st->execute([$campaignId, $email, mb_substr(trim((string) ($r['full_name'] ?? '')), 0, 120) ?: null, $code ?: null, 'queued']);
        $st->rowCount() ? $added++ : $skipped++;
    }
    return [$added, $skipped];
}

/** Read a CSV upload: needs an Email column; Full name and Share code / Referral code are used when present. */
function campaign_csv_rows(string $path): array
{
    $fh = fopen($path, 'r');
    if (!$fh) {
        return [];
    }
    $head = fgetcsv($fh) ?: [];
    $head = array_map(fn ($h) => mb_strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), $head);
    $col = fn (array $names) => array_values(array_intersect($names, $head))[0] ?? null;
    $ce = $col(['email', 'email address', 'e-mail']);
    $cn = $col(['full name', 'name', 'full_name']);
    $cc = $col(['share code', 'referral code', 'ref_code', 'code']);
    $rows = [];
    while ($ce !== null && ($line = fgetcsv($fh)) !== false) {
        $r = array_combine($head, array_pad(array_slice($line, 0, count($head)), count($head), ''));
        $rows[] = ['email' => $r[$ce] ?? '', 'full_name' => $cn ? ($r[$cn] ?? '') : '', 'ref_code' => $cc ? ($r[$cc] ?? '') : ''];
    }
    fclose($fh);
    return $rows;
}

/** Send up to $limit queued emails across campaigns that are sending. Returns [sent, failed]. */
function campaign_send_batch(int $limit = 40): array
{
    $sent = $failed = 0;
    $rows = q("SELECT q.*, c.subject, c.heading, c.body_html FROM email_queue q JOIN email_campaigns c ON c.id = q.campaign_id
               WHERE c.status = 'sending' AND q.status = 'queued' ORDER BY q.id LIMIT " . max(1, $limit))->fetchAll();
    foreach ($rows as $r) {
        if (campaign_suppressed($r['email'])) {
            q("UPDATE email_queue SET status = 'skipped', error = 'unsubscribed' WHERE id = ?", [$r['id']]);
            continue;
        }
        [$subject, $html] = campaign_render($r, $r);
        $ok = false;
        try {
            $ok = send_campaign_mail($r['email'], $subject, $html);
            $err = $ok ? null : 'send failed';
        } catch (Throwable $e) {
            $err = mb_substr($e->getMessage(), 0, 255);
        }
        q('UPDATE email_queue SET status = ?, error = ?, sent_at = ? WHERE id = ?', [$ok ? 'sent' : 'failed', $err, now(), $r['id']]);
        $ok ? $sent++ : $failed++;
    }
    q("UPDATE email_campaigns c SET status = 'done', finished_at = ? WHERE status = 'sending'
       AND NOT EXISTS (SELECT 1 FROM email_queue q WHERE q.campaign_id = c.id AND q.status = 'queued')", [now()]);
    return [$sent, $failed];
}
