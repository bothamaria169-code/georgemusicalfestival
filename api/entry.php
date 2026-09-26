<?php
/**
 * FLY FOR TWO entry API — used by the landing page, the ACSA embed, and
 * ACSA's own systems (with X-Api-Key). Every channel writes to the same tables.
 *
 * POST action=start   full_name, email, phone, town, age_group, fly_frequency, consent_terms, consent_popia, consent_marketing,
 *                     channel, pt, town_src, slot, ref, fp, timer, website (honeypot), cf-turnstile-response
 * POST action=verify  token, code
 * POST action=resend  token
 */
require dirname(__DIR__) . '/includes/bootstrap.php';

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin && in_array($origin, (array) config('cors_origins', []), true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
    header('Access-Control-Allow-Headers: Content-Type, X-Api-Key');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_out(['ok' => false, 'error' => 'POST required'], 405);
}

$in = $_POST;
if (!$in && str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
    $in = json_decode((string) file_get_contents('php://input'), true) ?: [];
}

$apiKey = (string) ($_SERVER['HTTP_X_API_KEY'] ?? '');
$isPartner = config('partner_api_key') && $apiKey !== '' && hash_equals((string) config('partner_api_key'), $apiKey);

$action = $in['action'] ?? '';

try {
    if (comp_state() !== 'open' && !in_array($action, ['verify', 'status'], true)) {
        json_out(['ok' => false, 'error' => comp_state() === 'upcoming' ? 'Entries open soon — check back shortly.' : 'The competition has closed. Thank you for your support!']);
    }

    /* ── Step 1: details → send code ─────────────────────────── */
    if ($action === 'start') {
        if (!$isPartner) {
            if (!empty($in['website'])) {                       // honeypot filled → bot
                json_out(['ok' => false, 'error' => 'Something went wrong. Please try again.'], 400);
            }
            if (!form_timer_ok($in['timer'] ?? null)) {
                json_out(['ok' => false, 'error' => 'Please take a moment to check your details, then try again.', 'refresh_timer' => form_timer()], 400);
            }
            if (!turnstile_ok($in['cf-turnstile-response'] ?? null)) {
                json_out(['ok' => false, 'error' => 'Please complete the security check.'], 400);
            }
        }
        if (!rate_limit('entry_start', client_ip(), $isPartner ? 600 : 300, 3600)) {
            json_out(['ok' => false, 'error' => 'Too many attempts from this connection. Please try again later.'], 429);
        }

        [$data, $errors] = validate_entry($in);
        if ($errors) {
            json_out(['ok' => false, 'errors' => $errors, 'error' => 'Please fix the highlighted fields.'], 422);
        }

        $channel = (string) ($in['channel'] ?? 'web');
        if (!isset(CHANNELS[$channel]) || $channel === 'ussd') {
            $channel = $isPartner ? 'acsa_embed' : 'web';
        }
        $referrer = !empty($in['ref']) ? entrant_by_ref((string) $in['ref']) : null;
        if ($referrer && $channel === 'web') {
            $channel = 'referral';
        }
        $clip = fn ($v, $n) => ($v = trim((string) $v)) === '' ? null : mb_substr($v, 0, $n);
        $source = [
            'channel'          => $channel,
            'activation_point' => $clip($in['pt'] ?? '', 120),
            'source_town'      => $clip($in['town_src'] ?? '', 80),
            'time_slot'        => $clip($in['slot'] ?? '', 40) ?? ($channel === 'qr_event' ? date('H:00') : null),
            'utm_source'       => $clip($in['utm_source'] ?? '', 80),
            'utm_medium'       => $clip($in['utm_medium'] ?? '', 80),
            'utm_campaign'     => $clip($in['utm_campaign'] ?? '', 80),
        ];

        $result = upsert_pending_entrant($data, $source, $referrer ? (int) $referrer['id'] : null, $in['fp'] ?? null);
        if (isset($result['error'])) {
            $existing = $result['entrant'];
            $samePerson = $existing['email'] && $existing['email'] === $data['email'];
            if ($result['error'] !== 'already_entered') {
                $msg = 'This entry cannot be accepted. Please contact us if you think this is a mistake.';
            } elseif ($samePerson || !$existing['email']) {
                $msg = "You're already entered with this email. Sign in to see your dashboard and share link.";
            } else {
                // The phone number matched an entry that uses a different email: say which one, so they can sign in.
                $msg = 'This cellphone number is already entered with the email ' . mask_email($existing['email'])
                    . '. Sign in with that email or with your cellphone number. If that email is wrong, contact '
                    . setting('contact_email') . ' and we will correct it.';
            }
            json_out(['ok' => false, 'code' => $result['error'], 'error' => $msg, 'login_url' => url('flyfortwo/login')], 409);
        }

        $entrant = $result['entrant'];
        $sent = otp_issue($entrant, 'entry');
        if (!$sent['ok'] && otp_cooldown_left((int) $entrant['id'], 'entry') === 0) {
            json_out(['ok' => false, 'error' => $sent['error']], 429);
        }
        json_out([
            'ok'       => true,
            'step'     => 'verify',
            'token'    => pending_token($entrant),
            'email'    => mask_email($entrant['email']),
            'cooldown' => $sent['cooldown'] ?: otp_cooldown_left((int) $entrant['id'], 'entry'),
            'dev_code' => $sent['dev_code'] ?? null,
        ]);
    }

    /* ── Resume check: is a saved in-progress entry still waiting for its code? ── */
    if ($action === 'status') {
        $entrant = entrant_from_token((string) ($in['token'] ?? ''));
        $status = $entrant['status'] ?? 'unknown';
        json_out([
            'ok'       => true,
            'status'   => $status,
            'signedIn' => $entrant && (int) ($_SESSION['entrant_id'] ?? 0) === (int) $entrant['id'],
            'cooldown' => $entrant && $status === 'pending' ? otp_cooldown_left((int) $entrant['id'], 'entry') : 0,
        ]);
    }

    /* ── Resend code ─────────────────────────────────────────── */
    if ($action === 'resend') {
        $entrant = entrant_from_token((string) ($in['token'] ?? ''));
        if (!$entrant || $entrant['status'] !== 'pending') {
            json_out(['ok' => false, 'error' => 'This entry session has expired. Please start again.'], 404);
        }
        $sent = otp_issue($entrant, 'entry');
        json_out($sent + ['email' => mask_email($entrant['email'])], $sent['ok'] ? 200 : 429);
    }

    /* ── Step 2: code → verified entry ───────────────────────── */
    if ($action === 'verify') {
        $entrant = entrant_from_token((string) ($in['token'] ?? ''));
        if (!$entrant) {
            json_out(['ok' => false, 'error' => 'This entry session has expired. Please start again.'], 404);
        }
        if ($entrant['status'] === 'pending') {
            if (comp_state() !== 'open') {
                json_out(['ok' => false, 'error' => 'The competition has closed.']);
            }
            $check = otp_check((int) $entrant['id'], 'entry', (string) ($in['code'] ?? ''));
            if ($check !== 'ok') {
                json_out(['ok' => false, 'code' => $check, 'error' => otp_error_message($check)], 422);
            }
            verify_entrant((int) $entrant['id'], 'email');
            $entrant = q('SELECT * FROM entrants WHERE id = ?', [$entrant['id']])->fetch();
            send_welcome_email($entrant);
        } elseif ($entrant['status'] !== 'verified' || (int) ($_SESSION['entrant_id'] ?? 0) !== (int) $entrant['id']) {
            // A token alone never signs anyone in once the entry is verified.
            json_out(['ok' => false, 'error' => 'This entry is already verified. Please sign in to your dashboard.', 'login_url' => url('flyfortwo/login')], 403);
        }

        if (!$isPartner) {
            login_entrant((int) $entrant['id']);
            mark_owner_device($entrant);
        }
        $link = ref_link($entrant);
        json_out([
            'ok'         => true,
            'step'       => 'share',
            'name'       => short_name($entrant['full_name']),
            'entries'    => entry_count((int) $entrant['id']),
            'ref_link'   => $link,
            'share_text' => "I just entered FLY FOR TWO — win a weekend getaway for two with George Musical Festival × ACSA. Enter free here: {$link}",
            'dashboard'  => url('flyfortwo/dashboard'),
        ]);
    }

    json_out(['ok' => false, 'error' => 'Unknown action'], 400);
} catch (Throwable $e) {
    error_log('[entry api] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    json_out(['ok' => false, 'error' => config('env') === 'production' ? 'Something went wrong on our side. Please try again.' : $e->getMessage()], 500);
}
