<?php
/**
 * One-tap confirmation from the code email: /flyfortwo/verify?t=TOKEN&c=CODE&p=entry|login|restore
 * Works in whatever browser the email app opens, so nobody has to switch back and type the code.
 */
require __DIR__ . '/includes/bootstrap.php';

$purpose = in_array($_GET['p'] ?? '', ['login', 'restore'], true) ? $_GET['p'] : 'entry';
$entrant = entrant_from_token((string) ($_GET['t'] ?? ''));
$code    = (string) ($_GET['c'] ?? '');

if (!$entrant || !rate_limit('verify_link', client_ip(), 30, 3600)) {
    flash('error', 'That link is not valid any more. Please enter again or sign in.');
    redirect('flyfortwo/');
}

if ($purpose === 'entry' && $entrant['status'] === 'verified') {
    // Already confirmed (maybe by typing the code). Only sign in if this browser already belongs to them.
    if ((int) ($_SESSION['entrant_id'] ?? 0) === (int) $entrant['id']) {
        redirect('flyfortwo/dashboard');
    }
    flash('success', "Your entry is already confirmed — you're in the draw. Sign in with your email to open your dashboard.");
    redirect('flyfortwo/login');
}
if ($entrant['status'] === 'disqualified' || ($purpose === 'login' && $entrant['status'] !== 'verified')) {
    flash('error', 'This entry cannot be used. Please contact us if you think this is a mistake.');
    redirect('flyfortwo/');
}
if ($purpose === 'entry' && comp_state() !== 'open') {
    flash('error', 'The competition is not open for entries right now.');
    redirect('flyfortwo/');
}

$check = otp_check((int) $entrant['id'], $purpose, $code);
if ($check !== 'ok') {
    flash('error', otp_error_message($check) . ($purpose === 'login' ? '' : ' Your details are saved — tap “Resend code” on the ' . ($purpose === 'restore' ? 'restore' : 'entry') . ' page.'));
    redirect(['login' => 'flyfortwo/login', 'restore' => 'flyfortwo/restore'][$purpose] ?? 'flyfortwo/');
}

if ($purpose === 'restore') {
    $approved = restore_email_confirmed((int) $entrant['id']);
    $entrant = q('SELECT * FROM entrants WHERE id = ?', [$entrant['id']])->fetch();
    unset($_SESSION['restore_entrant_id']);
    flash('success', $approved
        ? 'Your entry is restored. Your old link ' . implode(', ', $approved) . ' is yours again, with every open since the site came back.'
        : "Your email is confirmed and your entry is active. We're checking your old link — keep sharing it, every open is being saved.");
} elseif ($purpose === 'entry') {
    verify_entrant((int) $entrant['id'], 'email');
    $entrant = q('SELECT * FROM entrants WHERE id = ?', [$entrant['id']])->fetch();
    send_welcome_email($entrant);
    flash('success', "You're in! Share your personal link below — every person who opens it adds to your leaderboard score.");
} else {
    unset($_SESSION['login_entrant_id']);
}
login_entrant((int) $entrant['id']);
mark_owner_device($entrant);
redirect('flyfortwo/dashboard');
