<?php
/**
 * Bulk email: campaigns are queued per recipient and sent in small batches (cron or the admin
 * "Send next batch" button), so mailbox sending limits are respected and a failure never
 * stops the rest. Every email carries a personal unsubscribe link.
 *
 * Every campaign belongs to one brand: FLY FOR TWO (ACSA) or WIN A SAMSUNG (Cell C). The brand
 * sets the email's logos, links and templates. ACSA entrants are ACSA-owned data and can only be
 * added to ACSA campaigns; Cell C campaigns take imported lists and the GMF mailing list.
 *
 * Optional config 'bulk_mail' (same keys as 'mail') sends campaigns through a separate SMTP
 * service such as SMTP2GO or Brevo; without it campaigns use the normal 'mail' settings.
 */
declare(strict_types=1);

require_once __DIR__ . '/spreadsheet.php';

const CAMPAIGN_FIELDS = ['{first_name}', '{full_name}', '{share_link}', '{restore_link}', '{entry_link}', '{close_date}'];

/** The two competitions GMF emails for. The Cell C competition runs as its own app under /cellc/. */
function campaign_brands(): array
{
    return [
        'acsa' => [
            'label'     => 'FLY FOR TWO · ACSA',
            'comp'      => (string) setting('comp_name'),
            'partner'   => 'Airports Company South Africa',
            'entry'     => url('flyfortwo/'),
            'login'     => url('flyfortwo/login'),
            'share'     => 'r/',
            'close'     => fmt_date(setting('comp_close_at'), 'j F Y'),
            'btn_bg'    => '#f7801a',
            'btn_ink'   => '#2a1402',
            'audiences' => ['verified', 'all', 'marketing', 'subscribers', 'everyone'],
        ],
        'cellc' => [
            'label'     => 'WIN A SAMSUNG · Cell C',
            'comp'      => 'WIN A SAMSUNG',
            'partner'   => 'Cell C',
            'entry'     => url('cellc/win/'),
            'login'     => url('cellc/win/login'),
            'share'     => 'cellc/r/',
            'close'     => '',
            'btn_bg'    => '#e8590c',
            'btn_ink'   => '#ffffff',
            'audiences' => ['subscribers'],   // Cell C entrants live in the Cell C app: export them there and import here
        ],
    ];
}

function campaign_brand(?string $key): array
{
    $brands = campaign_brands();
    return $brands[$key ?? ''] ?? $brands['acsa'];
}

const CAMPAIGN_AUDIENCES = [
    'verified'    => 'All verified entrants',
    'all'         => 'All registered entrants (verified or not)',
    'marketing'   => 'Verified entrants who opted in to marketing',
    'subscribers' => 'GMF mailing list sign-ups',
    'everyone'    => 'Everyone: all entrants + mailing list',
];

/** Adds the brand column and the saved-templates table to databases from before 3 Nov 2026. Runs once. */
function campaign_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    if (q("SELECT 1 FROM settings WHERE skey = 'campaigns_schema' AND svalue = '2'")->fetchColumn()) {
        return;
    }
    if (!q("SHOW COLUMNS FROM email_campaigns LIKE 'brand'")->fetch()) {
        q("ALTER TABLE email_campaigns ADD COLUMN brand VARCHAR(10) NOT NULL DEFAULT 'acsa' AFTER id");
    }
    if (!q("SHOW COLUMNS FROM email_queue LIKE 'source'")->fetch()) {
        q('ALTER TABLE email_queue ADD COLUMN source VARCHAR(120) NULL AFTER ref_code');
    }
    q("CREATE TABLE IF NOT EXISTS email_templates (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        brand       VARCHAR(10)  NOT NULL DEFAULT 'acsa',
        name        VARCHAR(120) NOT NULL,
        subject     VARCHAR(190) NOT NULL,
        heading     VARCHAR(190) NOT NULL,
        body_html   MEDIUMTEXT   NOT NULL,
        created_by  INT UNSIGNED NULL,
        created_at  DATETIME     NOT NULL
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    save_setting('campaigns_schema', '2');
}

/* ──────────────────────────── Templates ──────────────────────────── */

function campaign_button(string $brand, string $label, string $field = '{entry_link}'): string
{
    $b = campaign_brand($brand);
    return "<p><a href=\"{$field}\" style=\"display:inline-block;background:{$b['btn_bg']};color:{$b['btn_ink']};text-decoration:none;font-weight:bold;padding:12px 20px;border-radius:10px;\">{$label}</a></p>";
}

/** Ready-made emails for each brand. Keys are stable ("b:acsa-back") so the picker can link to them. */
function campaign_builtin_templates(): array
{
    $contact = (string) setting('contact_email');
    $ask = "<p>Questions: <a href=\"mailto:{$contact}\">{$contact}</a>. T's &amp; C's apply.</p>";
    $a = campaign_brand('acsa');
    $fly = $a['comp'];
    $c = campaign_brand('cellc');
    $sam = $c['comp'];
    $t = [];

    $t['b:acsa-back'] = ['brand' => 'acsa', 'name' => "{$fly} is back — please enter again",
        'subject' => "{$fly} is back — entered before? Please enter again",
        'heading' => "Hi {first_name}, {$fly} is back",
        'body'    => "<p>{$fly} is back, and the prize is bigger: win a getaway for two (return flights, accommodation and ground transfers) plus R1,500 cash.</p>\n"
            . "<p><strong>Entered before? Please enter again.</strong> Entries close {close_date}.</p>\n"
            . campaign_button('acsa', 'Enter now') . "\n" . $ask];
    $t['b:acsa-share'] = ['brand' => 'acsa', 'name' => 'Share your link — climb the leaderboard',
        'subject' => 'Your FLY FOR TWO link is your ticket — share it today',
        'heading' => 'Hi {first_name}, share your link',
        'body'    => "<p>Thanks for entering {$fly}. The entrant whose personal link is opened by the most different people wins the getaway for two plus R1,500 cash.</p>\n"
            . "<p>Your personal link: <a href=\"{share_link}\">{share_link}</a></p>\n"
            . "<p>Send it on WhatsApp, post it on your status and share it in your groups. Every different person who opens it adds 1 to your score.</p>\n"
            . campaign_button('acsa', 'Open my link', '{share_link}') . "\n" . $ask];
    $t['b:acsa-reminder'] = ['brand' => 'acsa', 'name' => 'Reminder — entries close soon',
        'subject' => "Entries for {$fly} close {close_date}",
        'heading' => 'Hi {first_name}, there is still time',
        'body'    => "<p>Entries for {$fly} close on <strong>{close_date}</strong>. Win a getaway for two (return flights, accommodation and ground transfers) plus R1,500 cash.</p>\n"
            . "<p>Not entered yet? It's free and takes a minute. Already in? Keep sharing your link: <a href=\"{share_link}\">{share_link}</a></p>\n"
            . campaign_button('acsa', 'Enter now') . "\n" . $ask];
    $t['b:acsa-final'] = ['brand' => 'acsa', 'name' => 'Final call — last 48 hours',
        'subject' => "Final call: {$fly} closes {close_date}",
        'heading' => 'Final call, {first_name}',
        'body'    => "<p>This is the last chance to enter {$fly} and win a getaway for two plus R1,500 cash. Entries close on <strong>{close_date}</strong> at 23:59.</p>\n"
            . "<p>Already entered? One more share could move you up the leaderboard: <a href=\"{share_link}\">{share_link}</a></p>\n"
            . campaign_button('acsa', 'Enter before it closes') . "\n" . $ask];
    $t['b:acsa-thanks'] = ['brand' => 'acsa', 'name' => 'Thank you — entries closed',
        'subject' => "Thank you for entering {$fly}",
        'heading' => 'Thank you, {first_name}',
        'body'    => "<p>Entries for {$fly} have closed. Thank you for entering and for sharing your link.</p>\n"
            . "<p>The leaderboard is now checked for fair play and the winners will be announced on the competition page soon.</p>\n"
            . campaign_button('acsa', 'See the competition page') . "\n" . $ask];

    $t['b:cellc-enter'] = ['brand' => 'cellc', 'name' => "{$sam} — enter now",
        'subject' => "Win a Samsung Galaxy A56 + accessories worth R10 000",
        'heading' => "Hi {first_name}, win a Samsung with Cell C",
        'body'    => "<p>George Musical Festival and Cell C are giving away a Samsung Galaxy A56 plus accessories, worth R10 000 altogether.</p>\n"
            . "<p>Entry is free and takes about a minute. Then share your personal link: the more people who open it, the higher you climb.</p>\n"
            . campaign_button('cellc', 'Enter now') . "\n" . $ask];
    $t['b:cellc-share'] = ['brand' => 'cellc', 'name' => 'Share your link — climb the leaderboard',
        'subject' => "Your {$sam} link — share it today",
        'heading' => 'Hi {first_name}, share your link',
        'body'    => "<p>Thanks for entering {$sam}. Your personal link: <a href=\"{share_link}\">{share_link}</a></p>\n"
            . "<p>Send it on WhatsApp and share it in your groups. Every different person who opens it adds 1 to your score.</p>\n"
            . campaign_button('cellc', 'Open my link', '{share_link}') . "\n" . $ask];
    $t['b:cellc-final'] = ['brand' => 'cellc', 'name' => 'Final call',
        'subject' => "Final call: {$sam} closes soon",
        'heading' => 'Final call, {first_name}',
        'body'    => "<p>This is the last chance to enter {$sam} and win a Samsung Galaxy A56 plus accessories worth R10 000.</p>\n"
            . campaign_button('cellc', 'Enter now') . "\n" . $ask];
    $t['b:cellc-thanks'] = ['brand' => 'cellc', 'name' => 'Thank you — entries closed',
        'subject' => "Thank you for entering {$sam}",
        'heading' => 'Thank you, {first_name}',
        'body'    => "<p>Entries for {$sam} have closed. Thank you for entering and sharing.</p>\n"
            . "<p>The winner will be announced on the competition page soon.</p>\n"
            . campaign_button('cellc', 'See the competition page') . "\n" . $ask];
    return $t;
}

/** Built-in and saved templates for a brand, keyed "b:…" (built-in) or "s:ID" (saved). */
function campaign_templates(string $brand): array
{
    $out = array_filter(campaign_builtin_templates(), fn ($t) => $t['brand'] === $brand);
    foreach (q('SELECT * FROM email_templates WHERE brand = ? ORDER BY id DESC', [$brand])->fetchAll() as $s) {
        $out['s:' . $s['id']] = ['brand' => $s['brand'], 'name' => $s['name'], 'subject' => $s['subject'], 'heading' => $s['heading'], 'body' => $s['body_html'], 'saved' => (int) $s['id']];
    }
    return $out;
}

/** Used by the "New campaign" form when no template is picked. */
function campaign_default_template(string $brand = 'acsa'): array
{
    $all = campaign_templates($brand);
    return reset($all);
}

/* ──────────────────────────── Recipients ──────────────────────────── */

/** Recipients for a built-in audience: registered entrants and/or the GMF mailing list. Refuses entrants for Cell C. */
function campaign_audience_rows(string $audience, string $brand = 'acsa'): array
{
    if (!in_array($audience, campaign_brand($brand)['audiences'], true)) {
        return [];
    }
    $entrants = "SELECT email, full_name, ref_code FROM entrants WHERE email IS NOT NULL AND email <> '' AND (email_status IS NULL OR email_status <> 'bounced')";
    $list = "SELECT email, '' AS full_name, NULL AS ref_code FROM subscribers";
    $sql = match ($audience) {
        'all'         => $entrants,
        'verified'    => $entrants . " AND status = 'verified'",
        'marketing'   => $entrants . " AND status = 'verified' AND consent_marketing = 1",
        'subscribers' => $list,
        'everyone'    => $entrants . ' UNION ALL ' . $list,   // entrants first, so their names and links win the duplicate check
        default       => null,
    };
    return $sql ? q($sql)->fetchAll() : [];
}

/**
 * Recipient rows from a spreadsheet: needs an Email column; Full name (or First name + Surname)
 * and Share code / Referral code are used when present. The header can be on any of the first 10 rows.
 */
function campaign_sheet_recipients(array $rows): array
{
    $find = function (array $head, array $names): ?int {
        foreach ($names as $n) {
            $i = array_search($n, $head, true);
            if ($i !== false) {
                return (int) $i;
            }
        }
        return null;
    };
    foreach (array_slice($rows, 0, 10, true) as $at => $line) {
        $head = array_map(fn ($h) => mb_strtolower(trim((string) $h)), $line);
        $ce = $find($head, ['email', 'email address', 'e-mail', 'e-mail address', 'emails']);
        if ($ce === null) {
            continue;
        }
        $cn = $find($head, ['full name', 'name', 'full_name', 'names']);
        $cf = $find($head, ['first name', 'firstname', 'first_name']);
        $cl = $find($head, ['surname', 'last name', 'lastname', 'last_name']);
        $cc = $find($head, ['share code', 'referral code', 'ref code', 'ref_code', 'code']);
        $out = [];
        foreach (array_slice($rows, $at + 1) as $r) {
            $name = $cn !== null ? ($r[$cn] ?? '') : trim(($cf !== null ? ($r[$cf] ?? '') : '') . ' ' . ($cl !== null ? ($r[$cl] ?? '') : ''));
            $out[] = ['email' => $r[$ce] ?? '', 'full_name' => $name, 'ref_code' => $cc !== null ? ($r[$cc] ?? '') : ''];
        }
        return $out;
    }
    // No header: take the first column that holds email addresses.
    $out = [];
    foreach ($rows as $r) {
        foreach ($r as $v) {
            if (filter_var(trim((string) $v), FILTER_VALIDATE_EMAIL)) {
                $out[] = ['email' => $v, 'full_name' => '', 'ref_code' => ''];
                break;
            }
        }
    }
    return $out;
}

/**
 * Add recipients to a campaign. Each row: email, full_name, ref_code (optional).
 * Returns counts: added, invalid, duplicate (already on this campaign), unsubscribed.
 */
function campaign_add_recipients(int $campaignId, iterable $rows, string $source = ''): array
{
    $n = ['added' => 0, 'invalid' => 0, 'duplicate' => 0, 'unsubscribed' => 0];
    $st = db()->prepare('INSERT IGNORE INTO email_queue (campaign_id, email, full_name, ref_code, source, status) VALUES (?, ?, ?, ?, ?, ?)');
    foreach ($rows as $r) {
        $email = mb_strtolower(trim((string) ($r['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email === '' || $n['invalid']++;
            continue;
        }
        if (campaign_suppressed($email)) {
            $n['unsubscribed']++;
            continue;
        }
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) ($r['ref_code'] ?? '')) ?? '');
        $st->execute([$campaignId, $email, mb_substr(trim((string) ($r['full_name'] ?? '')), 0, 120) ?: null, $code ?: null, mb_substr($source, 0, 120) ?: null, 'queued']);
        $st->rowCount() ? $n['added']++ : $n['duplicate']++;
    }
    return $n;
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

/* ──────────────────────────── Rendering and sending ──────────────────────────── */

/** The finished email for one recipient: merge fields filled, branded wrapper, unsubscribe footer. */
function campaign_render(array $campaign, array $r): array
{
    $brandKey = (string) ($campaign['brand'] ?? 'acsa');
    $b = campaign_brand($brandKey);
    $first = trim(explode(' ', trim((string) ($r['full_name'] ?? '')))[0] ?? '');
    $values = [
        e($first !== '' ? $first : 'there'),
        e((string) ($r['full_name'] ?? '')),
        e(!empty($r['ref_code']) ? url($b['share'] . $r['ref_code']) : $b['entry']),
        e($b['login']),   // {restore_link}: kept for older drafts, now the sign-in page
        e($b['entry']),
        e($b['close']),
    ];
    $body = str_replace(CAMPAIGN_FIELDS, $values, (string) $campaign['body_html']);
    $heading = str_replace(CAMPAIGN_FIELDS, $values, (string) $campaign['heading']);
    $subject = str_replace(CAMPAIGN_FIELDS, array_map('html_entity_decode', $values), (string) $campaign['subject']);
    $unsub = e(campaign_unsubscribe_link((string) $r['email']));
    $body .= "<p style=\"margin:22px 0 0;font-size:12px;color:#55707a;\">You're receiving this because you entered " . e($b['comp'])
        . " or joined the George Musical Festival mailing list. <a href=\"{$unsub}\" style=\"color:#55707a;\">Unsubscribe</a> from further emails like this.</p>";
    $inner = '<div style="font-size:15px;line-height:1.6;">' . $body . '</div>';
    return [$subject, $brandKey === 'cellc' ? campaign_cellc_template($heading, $inner) : mail_template($heading, $inner)];
}

/** Cell C version of the email wrapper: white header with the GMF and Cell C logos. */
function campaign_cellc_template(string $heading, string $bodyHtml): string
{
    $logo = url('assets/img/brand/gmf-logo-orange.png');
    $cellc = url('assets/img/brand/cellc-logo.png');
    $year = date('Y');
    return <<<HTML
<!doctype html><html><body style="margin:0;background:#f4f1ee;font-family:Arial,Helvetica,sans-serif;color:#1f1a17;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f1ee;padding:28px 12px;"><tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#ffffff;border-radius:14px;overflow:hidden;">
<tr><td style="background:#ffffff;padding:20px 28px;border-bottom:4px solid #e8590c;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
<td align="left"><img src="{$logo}" alt="George Musical Festival" height="44" style="display:block;height:44px;"></td>
<td align="right"><img src="{$cellc}" alt="Cell C" height="36" style="display:inline-block;height:36px;"></td></tr></table></td></tr>
<tr><td style="padding:30px 28px 10px;"><h1 style="margin:0 0 14px;font-size:22px;line-height:1.25;color:#1f1a17;">{$heading}</h1>{$bodyHtml}</td></tr>
<tr><td style="padding:18px 28px 26px;font-size:12px;color:#6b645e;border-top:1px solid #ebe4dd;">WIN A SAMSUNG · George Musical Festival × Cell C<br>&copy; {$year} George Musical Festival</td></tr>
</table></td></tr></table></body></html>
HTML;
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

/** Send up to $limit queued emails across campaigns that are sending. Returns [sent, failed]. */
function campaign_send_batch(int $limit = 40): array
{
    campaign_schema();
    $sent = $failed = 0;
    $rows = q("SELECT q.*, c.brand, c.subject, c.heading, c.body_html FROM email_queue q JOIN email_campaigns c ON c.id = q.campaign_id
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
