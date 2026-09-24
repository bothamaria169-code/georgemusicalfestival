<?php
/** Newsletter / notify-me list — builds the GMF database of potential clients. */
require dirname(__DIR__) . '/includes/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_out(['ok' => false, 'error' => 'POST required'], 405);
}
if (!empty($_POST['website']) || !form_timer_ok($_POST['timer'] ?? null, 2)) {
    json_out(['ok' => false, 'error' => 'Please try again in a moment.'], 400);
}
if (!rate_limit('subscribe', client_ip(), 40, 3600)) {
    json_out(['ok' => false, 'error' => 'Too many attempts. Please try again later.'], 429);
}

$email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
$rawPhone = trim((string) ($_POST['phone'] ?? ''));
$phone = $rawPhone !== '' ? normalize_phone($rawPhone) : null;

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_out(['ok' => false, 'error' => 'Please enter a valid email address.'], 422);
}
if ($rawPhone !== '' && !$phone) {
    json_out(['ok' => false, 'error' => 'Please check your cellphone number.'], 422);
}
if (empty($_POST['consent'])) {
    json_out(['ok' => false, 'error' => 'Please tick the box to confirm you want to hear from us.'], 422);
}

$source = preg_replace('/[^a-z0-9_-]/', '', strtolower((string) ($_POST['source'] ?? 'website'))) ?: 'website';
q('INSERT INTO subscribers (email, phone, source, created_at) VALUES (?, ?, ?, ?)
   ON DUPLICATE KEY UPDATE phone = COALESCE(VALUES(phone), phone)', [$email, $phone, $source, now()]);

json_out(['ok' => true, 'message' => "You're on the list. Watch your inbox for the line-up drop."]);
