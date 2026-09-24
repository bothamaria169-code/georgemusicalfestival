<?php
/**
 * USSD entry channel (Africa's Talking-style callback, text/plain "CON …" / "END …").
 * Gateway callback URL:  https://<subdomain>/api/ussd.php?secret=<ussd_secret>
 * POST fields: sessionId, serviceCode, phoneNumber, text  (inputs joined with "*")
 *
 * The mobile network has already proven the phone number, so USSD entries are
 * verified immediately (verified_via = ussd) and written to the same tables as web entries.
 */
require dirname(__DIR__) . '/includes/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');

function ussd(string $type, string $message): void
{
    echo $type . ' ' . $message;
    exit;
}

$secret = (string) config('ussd_secret');
if ($secret === '' || !hash_equals($secret, (string) ($_GET['secret'] ?? ''))) {
    http_response_code(403);
    ussd('END', 'Service unavailable.');
}

$phone = normalize_phone((string) ($_POST['phoneNumber'] ?? ''));
$text  = trim((string) ($_POST['text'] ?? ''));
$parts = $text === '' ? [] : explode('*', $text);
$comp  = (string) setting('comp_name');

if (!$phone) {
    ussd('END', 'We could not read your number. Please try again.');
}
if (comp_state() !== 'open') {
    ussd('END', comp_state() === 'upcoming' ? "{$comp} opens soon. Dial again later!" : "{$comp} has closed. Thank you for your support!");
}

$existing = q('SELECT * FROM entrants WHERE phone = ?', [$phone])->fetch();
$verified = $existing && $existing['status'] === 'verified';

if (!$parts) {
    ussd('CON', "{$comp}: win " . setting('prize_headline') . "!\n1. Enter now\n2. Check my entry");
}

if ($parts[0] === '2') {
    if (!$verified) {
        ussd('END', "No entry found for this number. Dial again and choose 1 to enter.");
    }
    $n = entry_count((int) $existing['id']);
    ussd('END', "You're in the draw with {$n} entr" . ($n === 1 ? 'y' : 'ies') . ". Share code " . $existing['ref_code'] . " with friends. Good luck!");
}

if ($parts[0] !== '1') {
    ussd('END', 'Invalid choice. Please dial again.');
}
if ($verified) {
    ussd('END', "This number is already entered. One entry per number. Good luck!");
}

$options = lines((string) setting('travel_options'));
$towns   = lines((string) setting('towns'));
$step    = count($parts);

if ($step === 1) {
    ussd('CON', 'Enter your first name and surname:');
}
$name = trim($parts[1]);
if ($step === 2) {
    if (!str_contains($name, ' ') || mb_strlen($name) < 3) {
        ussd('END', 'Please enter your first name AND surname. Dial again to retry.');
    }
    $menu = setting('travel_question');
    foreach ($options as $i => $o) {
        $menu .= "\n" . ($i + 1) . '. ' . $o;
    }
    ussd('CON', $menu);
}
$fly = $options[(int) $parts[2] - 1] ?? null;
if (!$fly) {
    ussd('END', 'Invalid choice. Please dial again.');
}
if ($step === 3) {
    $menu = 'Your town:';
    foreach ($towns as $i => $t) {
        $menu .= "\n" . ($i + 1) . '. ' . $t;
    }
    ussd('CON', $menu);
}
$town = $towns[(int) $parts[3] - 1] ?? null;
if ($step === 4) {
    $termsUrl = preg_replace('#^https?://#', '', url('flyfortwo/terms'));
    ussd('CON', "Do you accept the {$comp} terms & conditions? (See {$termsUrl})\n1. I accept\n2. Cancel");
}
if ($parts[4] !== '1') {
    ussd('END', 'Entry cancelled. You need to accept the terms to enter.');
}
if ($step === 5) {
    ussd('CON', "Optional: receive GMF x ACSA event news and travel offers?\n1. Yes\n2. No");
}

[$data, $errors] = validate_entry([
    'full_name'         => $name,
    'phone'             => $phone,
    'town'              => $town,
    'fly_frequency'     => $fly,
    'consent_terms'     => 1,
    'consent_marketing' => ($parts[5] ?? '') === '1',
], false);
if ($errors) {
    ussd('END', reset($errors));
}
if (!rate_limit('ussd_entry', $phone, 5, 3600)) {
    ussd('END', 'Too many attempts. Please try again later.');
}

$result = upsert_pending_entrant($data, [
    'channel'          => 'ussd',
    'activation_point' => mb_substr((string) ($_POST['serviceCode'] ?? ''), 0, 120) ?: null,
    'source_town'      => $town,
    'time_slot'        => date('H:00'),
], null, null);
if (isset($result['error'])) {
    ussd('END', 'This number is already entered. One entry per number. Good luck!');
}
verify_entrant((int) $result['entrant']['id'], 'ussd');
$ref = $result['entrant']['ref_code'];

ussd('END', "You're entered in {$comp}! Draws on " . fmt_date(draw_by_key('event_day')['scheduled_for'] ?? null, 'j M') . ' & ' . fmt_date(draw_by_key('final')['scheduled_for'] ?? null, 'j M') . ". Your share code: {$ref}");
