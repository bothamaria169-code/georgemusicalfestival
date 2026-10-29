<?php
/**
 * FLY FOR TWO domain logic: entrants, verification codes, entries,
 * share bonus / referrals and source attribution.
 */
declare(strict_types=1);

const CHANNELS = [
    'web'        => 'Web landing page',
    'qr_ground'  => 'QR — ground activation',
    'qr_event'   => 'QR — event day',
    'ussd'       => 'USSD',
    'acsa_embed' => 'ACSA website embed',
    'referral'   => 'Referral link',
];

/* ──────────────────────────── Competition window ──────────────────────────── */

function comp_state(): string
{
    $t = time();
    if ($t < strtotime((string) setting('comp_open_at'))) {
        return 'upcoming';
    }
    if ($t > strtotime((string) setting('comp_close_at'))) {
        return 'closed';
    }
    return 'open';
}

/* ──────────────────────────── Formatting ──────────────────────────── */

/** Normalise to international digits. SA numbers become 27XXXXXXXXX. Returns null when invalid. */
function normalize_phone(string $raw): ?string
{
    $d = preg_replace('/\D+/', '', $raw) ?? '';
    if (str_starts_with($d, '00')) {
        $d = substr($d, 2);
    }
    if (strlen($d) === 10 && $d[0] === '0') {
        $d = '27' . substr($d, 1);
    }
    if (str_starts_with($d, '27')) {
        return (strlen($d) === 11 && in_array($d[2], ['6', '7', '8'], true)) ? $d : null;
    }
    return (strlen($d) >= 9 && strlen($d) <= 15) ? $d : null;
}

function display_phone(?string $p): string
{
    if (!$p) {
        return '';
    }
    if (strlen($p) === 11 && str_starts_with($p, '27')) {
        $local = '0' . substr($p, 2);
        return substr($local, 0, 3) . ' ' . substr($local, 3, 3) . ' ' . substr($local, 6);
    }
    return '+' . $p;
}

function mask_phone(?string $p): string
{
    $d = display_phone($p);
    return $d === '' ? '' : substr($d, 0, 3) . ' ••• ' . substr($d, -4);
}

function mask_email(?string $email): string
{
    if (!$email || !str_contains($email, '@')) {
        return '';
    }
    [$user, $domain] = explode('@', $email, 2);
    return mb_substr($user, 0, 2) . str_repeat('•', max(2, min(6, mb_strlen($user) - 2))) . '@' . $domain;
}

/** "Thandiwe Nkosi" → "Thandiwe N." — used anywhere names are shown publicly. */
function short_name(string $full): string
{
    $parts = preg_split('/\s+/', trim($full)) ?: [];
    $first = $parts[0] ?? '';
    $last = count($parts) > 1 ? mb_strtoupper(mb_substr(end($parts), 0, 1)) . '.' : '';
    return trim($first . ' ' . $last);
}

function channel_label(string $channel): string
{
    return CHANNELS[$channel] ?? ucfirst(str_replace('_', ' ', $channel));
}

/* ──────────────────────────── Source attribution ──────────────────────────── */

/**
 * Store attribution from the landing URL in the session, e.g.
 *   /flyfortwo/?src=qr_ground&pt=Garden+Route+Mall&town=George&slot=sat-am
 * The first tagged visit wins, so a later untagged visit doesn't erase it.
 */
function capture_source(): array
{
    $src = $_GET['src'] ?? $_GET['channel'] ?? null;
    if ($src !== null && isset(CHANNELS[$src])) {
        $_SESSION['source'] = [
            'channel'          => $src,
            'activation_point' => mb_substr(trim((string) ($_GET['pt'] ?? '')), 0, 120) ?: null,
            'source_town'      => mb_substr(trim((string) ($_GET['town'] ?? '')), 0, 80) ?: null,
            'time_slot'        => mb_substr(trim((string) ($_GET['slot'] ?? '')), 0, 40) ?: null,
        ];
    }
    foreach (['utm_source', 'utm_medium', 'utm_campaign'] as $k) {
        if (!empty($_GET[$k])) {
            $_SESSION['utm'][$k] = mb_substr((string) $_GET[$k], 0, 80);
        }
    }
    return source_data();
}

/** Attribution + referral handling for any page that shows the entry form. */
function competition_landing_context(): void
{
    capture_source();
    if (!empty($_GET['ref']) && ($r = entrant_by_ref((string) $_GET['ref']))) {
        record_referral_click($r);
        $_SESSION['ref_by'] = $r['ref_code'];
    }
}

function source_data(): array
{
    $s = $_SESSION['source'] ?? [];
    $channel = $s['channel'] ?? (!empty($_SESSION['ref_by']) ? 'referral' : 'web');
    // Event-day QR entries are reported by hour when no explicit slot was given.
    $slot = $s['time_slot'] ?? ($channel === 'qr_event' ? date('H:00') : null);
    return [
        'channel'          => $channel,
        'activation_point' => $s['activation_point'] ?? null,
        'source_town'      => $s['source_town'] ?? null,
        'time_slot'        => $slot,
        'utm_source'       => $_SESSION['utm']['utm_source'] ?? null,
        'utm_medium'       => $_SESSION['utm']['utm_medium'] ?? null,
        'utm_campaign'     => $_SESSION['utm']['utm_campaign'] ?? null,
    ];
}

/* ──────────────────────────── Entrants ──────────────────────────── */

function new_ref_code(): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do {
        $code = '';
        for ($i = 0; $i < 7; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
    } while (ref_code_in_use($code));   // never reissue a code that circulated before the server loss
    return $code;
}

/** The verified entrant a share code leads to — their own code or a restored alias. */
function entrant_by_ref(string $code): ?array
{
    $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');
    if ($code === '') {
        return null;
    }
    $row = q("SELECT * FROM entrants WHERE ref_code = ? AND status = 'verified'", [$code])->fetch()
        ?: q("SELECT e.* FROM ref_aliases a JOIN entrants e ON e.id = a.entrant_id WHERE a.ref_code = ? AND e.status = 'verified'", [$code])->fetch();
    return $row ?: null;
}

/** True for the "Under 18" option (or any wording that starts with "under"). */
function is_under_18_group(string $age): bool
{
    return $age !== '' && (bool) preg_match('/^\s*(under|<)\s*18/i', $age);
}

/**
 * Validate an entry submission. Returns [clean data, errors].
 * $requireEmail is false only for USSD, where the network verifies the phone.
 */
function validate_entry(array $in, bool $requireEmail = true): array
{
    $errors = [];
    $name = trim(preg_replace('/\s+/', ' ', (string) ($in['full_name'] ?? '')) ?? '');
    $email = mb_strtolower(trim((string) ($in['email'] ?? '')));
    $phone = normalize_phone((string) ($in['phone'] ?? ''));
    $options = lines((string) setting('travel_options'));
    $towns = lines((string) setting('towns'));
    $fly = trim((string) ($in['fly_frequency'] ?? ''));
    $town = trim((string) ($in['town'] ?? ''));
    $ages = lines((string) setting('age_groups'));
    $age = trim((string) ($in['age_group'] ?? ''));

    if (mb_strlen($name) < 3 || !str_contains($name, ' ')) {
        $errors['full_name'] = 'Please enter your first name and surname.';
    } elseif (mb_strlen($name) > 120 || preg_match('/[<>{}\d@]/', $name)) {
        $errors['full_name'] = 'Please enter a valid name.';
    }
    if ($requireEmail && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address — your verification code is sent there.';
    } elseif ($email !== '' && ($problem = email_entry_problem($email))) {
        $errors['email'] = $problem;
    }
    if (!$phone) {
        $errors['phone'] = 'Please enter a valid cellphone number, e.g. 082 123 4567.';
    }
    if ($options && !in_array($fly, $options, true)) {
        $errors['fly_frequency'] = 'Please answer the travel question.';
    }
    if ($towns && $town !== '' && !in_array($town, $towns, true)) {
        $errors['town'] = 'Please choose your town from the list.';
    }
    if ($ages && !in_array($age, $ages, true)) {
        $errors['age_group'] = 'Please choose your age group.';
    } elseif (is_under_18_group($age)) {
        $errors['age_group'] = 'Sorry — you must be 18 or older to enter.';
    }
    if (empty($in['consent_popia'])) {
        $errors['consent_popia'] = 'POPIA consent is required to enter.';
    }
    if (empty($in['consent_terms'])) {
        $errors['consent_terms'] = 'You need to accept the competition terms to enter.';
    }

    return [[
        'full_name'         => $name,
        'email'             => $email !== '' ? $email : null,
        'phone'             => $phone,
        'town'              => $town !== '' ? $town : null,
        'fly_frequency'     => $fly,
        'age_group'         => $age !== '' ? $age : null,
        'consent_terms'     => 1,
        'consent_popia'     => 1,
        'consent_marketing' => !empty($in['consent_marketing']) ? 1 : 0,
    ], $errors];
}

/**
 * Create or refresh a pending entrant. Returns ['entrant' => row] or ['error' => code, 'entrant' => row].
 * One valid entry per verified phone and per verified email — duplicates are routed to login instead.
 */
function upsert_pending_entrant(array $data, array $source, ?int $referredBy, ?string $fingerprint): array
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $byEmail = $data['email'] ? q('SELECT * FROM entrants WHERE email = ? FOR UPDATE', [$data['email']])->fetch() : null;
        $byPhone = q('SELECT * FROM entrants WHERE phone = ? FOR UPDATE', [$data['phone']])->fetch();

        foreach ([$byEmail, $byPhone] as $row) {
            if ($row && $row['status'] !== 'pending') {
                $pdo->commit();
                return ['error' => $row['status'] === 'verified' ? 'already_entered' : 'disqualified', 'entrant' => $row];
            }
        }
        // Two different unverified drafts collide: keep the email one, drop the stale phone draft.
        if ($byEmail && $byPhone && $byEmail['id'] !== $byPhone['id']) {
            q('DELETE FROM entrants WHERE id = ?', [$byPhone['id']]);
            $byPhone = null;
        }
        $existing = $byEmail ?: $byPhone;

        $fields = $data + $source + [
            'referred_by' => $referredBy,
            'device_fp'   => $fingerprint ? app_hash('fp|' . $fingerprint) : null,
            'ip_hash'     => ip_hash(),
            'user_agent'  => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            'consent_at'  => now(),
        ];

        if ($existing) {
            $sets = implode(', ', array_map(fn ($k) => "$k = :$k", array_keys($fields)));
            q("UPDATE entrants SET $sets WHERE id = :id", $fields + ['id' => $existing['id']]);
            $id = (int) $existing['id'];
        } else {
            $fields += ['ref_code' => new_ref_code(), 'status' => 'pending', 'created_at' => now()];
            $cols = implode(', ', array_keys($fields));
            $vals = ':' . implode(', :', array_keys($fields));
            q("INSERT INTO entrants ($cols) VALUES ($vals)", $fields);
            $id = (int) $pdo->lastInsertId();
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return ['entrant' => q('SELECT * FROM entrants WHERE id = ?', [$id])->fetch()];
}

/* ──────────────────────────── Entry tokens ──────────────────────────── */

/** Opaque reference to one entrant, used by the entry API and the email confirm link. */
function pending_token(array $entrant): string
{
    return $entrant['id'] . '.' . substr(app_hash('pending|' . $entrant['id'] . '|' . $entrant['ref_code']), 0, 32);
}

function entrant_from_token(string $token): ?array
{
    if (!preg_match('/^(\d+)\.([a-f0-9]{32})$/', $token, $m)) {
        return null;
    }
    $e = q('SELECT * FROM entrants WHERE id = ?', [(int) $m[1]])->fetch();
    return ($e && hash_equals(pending_token($e), $token)) ? $e : null;
}

/* ──────────────────────────── Verification codes ──────────────────────────── */

function otp_hash(int $entrantId, string $purpose, string $code): string
{
    return app_hash("otp|{$entrantId}|{$purpose}|{$code}");
}

/** Seconds until another code may be sent (0 = now). */
function otp_cooldown_left(int $entrantId, string $purpose): int
{
    $last = q('SELECT sent_at FROM otps WHERE entrant_id = ? AND purpose = ? ORDER BY id DESC LIMIT 1', [$entrantId, $purpose])->fetchColumn();
    if (!$last) {
        return 0;
    }
    return max(0, (int) setting('otp_cooldown') - (time() - strtotime($last)));
}

/**
 * Send a 6-digit code to the entrant's email.
 * Returns ['ok' => true, 'cooldown' => int, 'dev_code' => ?string] or ['ok' => false, 'error' => msg, 'cooldown' => int].
 */
function otp_issue(array $entrant, string $purpose): array
{
    $id = (int) $entrant['id'];
    $wait = otp_cooldown_left($id, $purpose);
    if ($wait > 0) {
        return ['ok' => false, 'error' => "Please wait {$wait} seconds before requesting another code.", 'cooldown' => $wait];
    }
    if (!rate_limit('otp_entrant', (string) $id, 6, 3600) || !rate_limit('otp_ip', client_ip(), 300, 3600)) {
        return ['ok' => false, 'error' => 'Too many codes requested. Please try again in an hour.', 'cooldown' => 0];
    }
    if (empty($entrant['email'])) {
        return ['ok' => false, 'error' => 'No email address on this entry.', 'cooldown' => 0];
    }

    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $ttl = max(2, (int) setting('otp_ttl_minutes'));
    q('UPDATE otps SET consumed_at = ? WHERE entrant_id = ? AND purpose = ? AND consumed_at IS NULL', [now(), $id, $purpose]);
    q('INSERT INTO otps (entrant_id, purpose, code_hash, sent_at, expires_at) VALUES (?, ?, ?, ?, ?)', [
        $id, $purpose, otp_hash($id, $purpose, $code), now(), date('Y-m-d H:i:s', time() + $ttl * 60),
    ]);

    $first = e(explode(' ', $entrant['full_name'])[0]);
    $link = e(url('flyfortwo/verify') . '?' . http_build_query(['t' => pending_token($entrant), 'c' => $code, 'p' => $purpose]));
    $intro = [
        'login'   => 'Tap the button to sign in to your FLY FOR TWO dashboard, or type the code on the sign-in page.',
        'restore' => 'Tap the button to confirm your email and restore your FLY FOR TWO entry, or type the code on the restore page.',
    ][$purpose] ?? 'Tap the button to confirm your FLY FOR TWO entry, or type the code on the entry page. Your entry only counts once it is confirmed.';
    $button = ['login' => 'Sign in to my dashboard', 'restore' => 'Restore my entry'][$purpose] ?? 'Confirm my entry';
    $html = mail_template("Your code: {$code}", "<p style=\"margin:0 0 14px;font-size:15px;line-height:1.6;\">Hi {$first},</p>"
        . "<p style=\"margin:0 0 20px;font-size:15px;line-height:1.6;\">{$intro}</p>"
        . "<p style=\"margin:0 0 22px;\"><a href=\"{$link}\" style=\"display:inline-block;background:#f7801a;color:#2a1402;text-decoration:none;font-weight:bold;font-size:16px;padding:14px 26px;border-radius:10px;\">{$button}</a></p>"
        . "<p style=\"margin:0 0 6px;font-size:13px;color:#55707a;\">Or enter this code:</p>"
        . "<p style=\"margin:0 0 18px;font-size:32px;letter-spacing:10px;font-weight:bold;color:#00485a;\">{$code}</p>"
        . "<p style=\"margin:0 0 8px;font-size:13px;color:#55707a;\">The code and button expire in {$ttl} minutes. If you didn't request this, you can ignore this email.</p>");
    $sent = send_mail($entrant['email'], "{$code} is your FLY FOR TWO code", $html);
    if (!$sent) {
        mark_email_problem((string) $entrant['email'], 'send_failed', 'The code email could not be sent (' . date('j M H:i') . ')');
        return ['ok' => false, 'error' => "We couldn't send the email right now. Please check the address and try again.", 'cooldown' => 0];
    }

    $showCode = config('env') !== 'production' && config('mail.driver') === 'log';
    return ['ok' => true, 'cooldown' => (int) setting('otp_cooldown'), 'dev_code' => $showCode ? $code : null];
}

/** Returns 'ok' | 'invalid' | 'expired' | 'locked' | 'none'. */
function otp_check(int $entrantId, string $purpose, string $code): string
{
    $otp = q('SELECT * FROM otps WHERE entrant_id = ? AND purpose = ? AND consumed_at IS NULL ORDER BY id DESC LIMIT 1', [$entrantId, $purpose])->fetch();
    if (!$otp) {
        return 'none';
    }
    if (strtotime($otp['expires_at']) < time()) {
        return 'expired';
    }
    if ((int) $otp['attempts'] >= (int) setting('otp_max_attempts')) {
        return 'locked';
    }
    $code = preg_replace('/\D/', '', $code) ?? '';
    if (!hash_equals($otp['code_hash'], otp_hash($entrantId, $purpose, $code))) {
        q('UPDATE otps SET attempts = attempts + 1 WHERE id = ?', [$otp['id']]);
        return ((int) $otp['attempts'] + 1 >= (int) setting('otp_max_attempts')) ? 'locked' : 'invalid';
    }
    q('UPDATE otps SET consumed_at = ? WHERE id = ?', [now(), $otp['id']]);
    return 'ok';
}

function otp_error_message(string $result): string
{
    return [
        'invalid' => "That code isn't right. Check the email and try again.",
        'expired' => 'That code has expired. Tap “Resend code” for a new one.',
        'locked'  => 'Too many incorrect attempts. Request a new code to continue.',
        'none'    => 'No active code — tap “Resend code” to get one.',
    ][$result] ?? 'Verification failed.';
}

/* ──────────────────────────── Entries ──────────────────────────── */

function grant_entry(int $entrantId, string $kind, int $source = 0): bool
{
    $st = q('INSERT IGNORE INTO entries (entrant_id, kind, source_entrant, created_at) VALUES (?, ?, ?, ?)', [$entrantId, $kind, $source, now()]);
    return $st->rowCount() > 0;
}

function entry_count(int $entrantId): int
{
    return (int) q('SELECT COUNT(*) FROM entries WHERE entrant_id = ?', [$entrantId])->fetchColumn();
}

function entry_breakdown(int $entrantId): array
{
    $out = ['base' => 0, 'share_bonus' => 0, 'referral_bonus' => 0];
    foreach (q('SELECT kind, COUNT(*) c FROM entries WHERE entrant_id = ? GROUP BY kind', [$entrantId])->fetchAll() as $r) {
        $out[$r['kind']] = (int) $r['c'];
    }
    return $out;
}

/** Mark an entrant verified, create their base entry and credit whoever referred them. */
function verify_entrant(int $entrantId, string $via): void
{
    $e = q('SELECT * FROM entrants WHERE id = ?', [$entrantId])->fetch();
    if (!$e || $e['status'] === 'verified') {
        return;
    }
    q("UPDATE entrants SET status = 'verified', verified_at = ?, verified_via = ? WHERE id = ?", [now(), $via, $entrantId]);
    grant_entry($entrantId, 'base');

    if ($e['referred_by']) {
        $per = (int) setting('referral_entries_per_signup');
        $cap = (int) setting('referral_entry_cap');
        $have = (int) q("SELECT COUNT(*) FROM entries WHERE entrant_id = ? AND kind = 'referral_bonus'", [$e['referred_by']])->fetchColumn();
        if ($per > 0 && $have < $cap) {
            grant_entry((int) $e['referred_by'], 'referral_bonus', $entrantId);
        }
    }
}

/** Send the post-verification email with the entrant's personal link. */
function send_welcome_email(array $entrant): void
{
    if (empty($entrant['email'])) {
        return;
    }
    $first = e(explode(' ', $entrant['full_name'])[0]);
    $link = e(ref_link($entrant));
    $dash = e(url('flyfortwo/dashboard'));
    $html = mail_template("You're in, {$first}.", '<p style="margin:0 0 14px;font-size:15px;line-height:1.6;">Your FLY FOR TWO entry is confirmed. You could win <strong>' . e((string) setting('prize_headline')) . '</strong>.</p>'
        . '<p style="margin:0 0 10px;font-size:15px;line-height:1.6;">Now share your personal link. Every different person who opens it adds 1 to your leaderboard score — the top score wins the Grand Prize and the runner-up wins the Second Prize:</p>'
        . "<p style=\"margin:0 0 18px;\"><a href=\"{$link}\" style=\"font-size:16px;font-weight:bold;color:#c9620a;\">{$link}</a></p>"
        . "<p style=\"margin:0 0 18px;\"><a href=\"{$dash}\" style=\"display:inline-block;background:#f7801a;color:#2a1402;text-decoration:none;font-weight:bold;padding:12px 20px;border-radius:10px;\">Open my dashboard</a></p>"
        . '<p style="margin:0;font-size:13px;color:#55707a;">' . e((string) setting('draw_reassurance')) . '</p>');
    send_mail($entrant['email'], "You're entered — FLY FOR TWO", $html);
}

/* ──────────────────────────── Sharing & referrals ──────────────────────────── */

function ref_link(array $entrant): string
{
    return url('r/' . $entrant['ref_code']);
}

function visitor_id(): string
{
    $v = $_COOKIE['gmf_vid'] ?? '';
    if (!preg_match('/^[a-f0-9]{32}$/', $v)) {
        $v = bin2hex(random_bytes(16));
        setcookie('gmf_vid', $v, ['expires' => time() + 31536000, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
        $_COOKIE['gmf_vid'] = $v;
    }
    return $v;
}

/** Remember on this device that it belongs to an entrant, so opening your own link never counts. */
function mark_owner_device(array $entrant): void
{
    $owned = array_filter(explode(',', $_COOKIE['gmf_own'] ?? ''));
    if (!in_array($entrant['ref_code'], $owned, true)) {
        $owned[] = $entrant['ref_code'];
        $value = implode(',', array_slice($owned, -5));
        setcookie('gmf_own', $value, ['expires' => time() + 31536000, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
        $_COOKIE['gmf_own'] = $value;
    }
}

/**
 * Record a click on someone's share link. The first genuine click-through from
 * another person earns the referrer their share bonus (capped per entrant).
 * Self-clicks (signed-in owner, or the device the entrant registered on) never count.
 * IP matching is deliberately not used: mobile networks put whole crowds behind one IP.
 */
function record_referral_click(array $referrer): void
{
    $vid = visitor_id();
    $isOwner = (int) (current_entrant()['id'] ?? 0) === (int) $referrer['id']
        || array_intersect(entrant_codes((int) $referrer['id']), device_codes());
    if ($isOwner) {
        return;
    }
    if (comp_state() !== 'open') {
        return;   // link opens only score while entries are open
    }
    if (!rate_limit('ref_click', client_ip() . '|' . $referrer['id'], 3, 86400)) {
        return;
    }
    $st = q('INSERT IGNORE INTO referral_clicks (referrer_id, ip_hash, visitor_id, user_agent, clicked_at) VALUES (?, ?, ?, ?, ?)', [
        $referrer['id'], ip_hash(), $vid, mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255), now(),
    ]);
    if ($st->rowCount() === 0) {
        return;
    }
    $clickId = (int) db()->lastInsertId();
    $cap = (int) setting('share_bonus_cap');
    if ($cap > 0 && comp_state() === 'open') {
        $have = (int) q("SELECT COUNT(*) FROM entries WHERE entrant_id = ? AND kind = 'share_bonus'", [$referrer['id']])->fetchColumn();
        if ($have < $cap && grant_entry((int) $referrer['id'], 'share_bonus', $have)) {
            q('UPDATE referral_clicks SET counted = 1 WHERE id = ?', [$clickId]);
        }
    }
}

function referral_stats(int $entrantId): array
{
    $row = q("SELECT
            (SELECT COUNT(*) FROM referral_clicks WHERE referrer_id = ?) AS clicks,
            (SELECT COUNT(*) FROM entrants WHERE referred_by = ? AND status = 'verified') AS verified,
            (SELECT COUNT(*) FROM entrants WHERE referred_by = ? AND status = 'pending') AS pending", [$entrantId, $entrantId, $entrantId])->fetch();
    return array_map('intval', $row);
}

/**
 * The competition leaderboard. Score = link opens: unique visitors who opened the entrant's
 * personal link while entries were open (the entrant's own devices never count, and one
 * network can add at most 3 opens per referrer per day). Ties: more friends who entered,
 * then whoever reached their score first.
 */
function leaderboard_scores(?string $cutoff = null, int $limit = 0): array
{
    $open  = (string) setting('comp_open_at');
    $close = $cutoff ?? date('Y-m-d H:i:s', min(time(), strtotime((string) setting('comp_close_at'))));
    $sql = "SELECT r.id, r.full_name, r.town, r.photo, r.ref_code, r.email, r.phone,
                   COUNT(c.id) AS clicks,
                   COUNT(DISTINCT c.ip_hash) AS networks,
                   (SELECT COUNT(*) FROM entrants f WHERE f.referred_by = r.id AND f.status = 'verified' AND f.verified_at <= ?) AS referrals,
                   MAX(c.clicked_at) AS last_click
            FROM entrants r
            JOIN referral_clicks c ON c.referrer_id = r.id AND c.clicked_at BETWEEN ? AND ?
            WHERE r.status = 'verified'
            GROUP BY r.id
            ORDER BY clicks DESC, referrals DESC, last_click ASC, r.id ASC";
    if ($limit > 0) {
        $sql .= ' LIMIT ' . $limit;
    }
    return q($sql, [$close, $open, $close])->fetchAll();
}

/** 1-based position of an entrant on the leaderboard, or null when they have no link opens yet. */
function leaderboard_rank(int $entrantId): ?int
{
    foreach (leaderboard_scores() as $i => $row) {
        if ((int) $row['id'] === $entrantId) {
            return $i + 1;
        }
    }
    return null;
}

/**
 * Public top 10: first name + surname initial, town and score. The leader (#1) is hidden
 * until the winners are published, so nobody but GMF admins knows who is winning.
 */
function public_leaderboard(int $limit = 10): array
{
    $revealed = (bool) q("SELECT 1 FROM draws WHERE draw_key = 'final' AND status = 'published'")->fetchColumn();
    $board = [];
    foreach (leaderboard_scores(null, $limit) as $r) {
        $board[] = ['id' => (int) $r['id'], 'name' => short_name($r['full_name']), 'town' => $r['town'] ?: '', 'clicks' => (int) $r['clicks'], 'leader' => false];
    }
    // Carried-over standings (display only) until each entrant's live entry is back.
    // A row drops out once its code or its name and town appear among the live entrants.
    $live = array_map(fn ($b) => mb_strtolower($b['name'] . '|' . $b['town']), $board);
    try {
        $carry = q('SELECT * FROM leaderboard_carryover ORDER BY is_leader DESC, opens DESC')->fetchAll();
    } catch (Throwable $e) {
        $carry = [];   // table not installed yet
    }
    foreach ($carry as $c) {
        if (($c['ref_code'] && entrant_by_ref($c['ref_code'])) || in_array(mb_strtolower($c['name'] . '|' . ($c['town'] ?? '')), $live, true)) {
            continue;
        }
        $board[] = ['id' => 0, 'name' => $c['name'], 'town' => $c['town'] ?? '', 'clicks' => (int) $c['opens'], 'leader' => (bool) $c['is_leader']];
    }
    usort($board, fn ($a, $b) => [$b['leader'], $b['clicks']] <=> [$a['leader'], $a['clicks']]);
    $hasLeader = (bool) array_filter($board, fn ($b) => $b['leader']);

    $rows = [];
    foreach (array_slice($board, 0, $limit) as $i => $b) {
        $hidden = !$revealed && ($b['leader'] || (!$hasLeader && $i === 0));
        $rows[] = [
            'rank'   => $i + 1,
            'id'     => $b['id'],
            'name'   => $hidden ? 'Mystery leader' : $b['name'],
            'town'   => $hidden ? 'Revealed on draw day' : $b['town'],
            'clicks' => $hidden ? null : $b['clicks'],
            'hidden' => $hidden,
        ];
    }
    return $rows;
}

/** Recent public activity for the live ticker: new entrants and friends joining through a link. */
function recent_activity(int $limit = 20): array
{
    $rows = q("SELECT p.full_name, p.town, p.verified_at, r.full_name AS ref_name
               FROM entrants p LEFT JOIN entrants r ON r.id = p.referred_by
               WHERE p.status = 'verified' ORDER BY p.verified_at DESC LIMIT " . (int) $limit)->fetchAll();
    $out = [];
    foreach ($rows as $row) {
        $who = short_name($row['full_name']) . ($row['town'] ? ' from ' . $row['town'] : '');
        $out[] = [
            'type' => $row['ref_name'] ? 'referral' : 'join',
            'text' => $row['ref_name']
                ? $who . ' joined through ' . short_name($row['ref_name']) . "'s link"
                : $who . ' just entered',
            'at'   => $row['verified_at'],
        ];
    }
    return $out;
}

/** Store an entrant photo: re-encoded square JPEG (strips location and camera data). */
function save_entrant_photo(array $file, int $entrantId): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || $file['size'] > 12 * 1024 * 1024) {
        return null;
    }
    $info = @getimagesize($file['tmp_name']);
    $mime = $info['mime'] ?? '';
    $src = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($file['tmp_name']),
        'image/png'  => @imagecreatefrompng($file['tmp_name']),
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file['tmp_name']) : false,
        default      => false,
    };
    if (!$src) {
        return null;
    }
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $o = (int) (@exif_read_data($file['tmp_name'])['Orientation'] ?? 1);
        $src = match ($o) { 3 => imagerotate($src, 180, 0), 6 => imagerotate($src, -90, 0), 8 => imagerotate($src, 90, 0), default => $src };
    }
    $w = imagesx($src); $h = imagesy($src); $side = min($w, $h);
    $out = imagecreatetruecolor(720, 720);
    imagecopyresampled($out, $src, 0, 0, (int) (($w - $side) / 2), (int) (($h - $side) / 3), 720, 720, $side, $side);
    $dir = APP_ROOT . '/uploads/entrants';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $name = 'uploads/entrants/' . $entrantId . '-' . bin2hex(random_bytes(8)) . '.jpg';
    imagejpeg($out, APP_ROOT . '/' . $name, 85);
    $old = q('SELECT photo FROM entrants WHERE id = ?', [$entrantId])->fetchColumn();
    if ($old && str_starts_with($old, 'uploads/entrants/')) {
        @unlink(APP_ROOT . '/' . $old);
    }
    q('UPDATE entrants SET photo = ? WHERE id = ?', [$name, $entrantId]);
    return $name;
}

/* ──────────────────────────── Draws ──────────────────────────── */

/** Every ticket in the pool, in a deterministic order, for entrants verified before the cutoff. */
function draw_pool(string $cutoff, array $excludeEntrants = []): array
{
    $sql = "SELECT en.id AS entry_id, en.entrant_id
            FROM entries en JOIN entrants p ON p.id = en.entrant_id
            WHERE p.status = 'verified' AND en.created_at <= ?";
    $params = [$cutoff];
    if ($excludeEntrants) {
        $sql .= ' AND en.entrant_id NOT IN (' . implode(',', array_fill(0, count($excludeEntrants), '?')) . ')';
        $params = array_merge($params, array_values($excludeEntrants));
    }
    return q($sql . ' ORDER BY en.id', $params)->fetchAll();
}

function draw_by_key(string $key): ?array
{
    return q('SELECT * FROM draws WHERE draw_key = ?', [$key])->fetch() ?: null;
}

function prize_lines(?array $draw): array
{
    return $draw ? lines((string) $draw['prize_description']) : [];
}
