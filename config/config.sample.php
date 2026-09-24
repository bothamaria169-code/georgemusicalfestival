<?php
/**
 * GMF × ACSA — FLY FOR TWO platform configuration.
 *
 * Copy this file to config/config.php and fill in the values for the
 * server you are deploying to. config.php is never overwritten by updates.
 */
return [
    // ── App ────────────────────────────────────────────────────────────
    // Full public URL of the site, no trailing slash. Leave empty to auto-detect.
    'base_url'   => '',
    // 'production' hides debug output. 'development' shows OTP codes on screen
    // when email is not configured, so the flow can be tested locally.
    'env'        => 'development',
    'timezone'   => 'Africa/Johannesburg',
    // Random 64-char string. Used to sign tokens and hash IPs. Change it once, then never again.
    'app_secret' => 'CHANGE-ME-to-a-long-random-string',

    // ── Database (MySQL / MariaDB) ─────────────────────────────────────
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'gmf_flyfortwo',
        'user' => 'root',
        'pass' => '',
    ],

    // ── Email (verification codes) ─────────────────────────────────────
    // driver: 'smtp' (recommended — use the cPanel mailbox), 'mail' (PHP mail()), or 'log' (write to storage/logs/mail.log)
    'mail' => [
        'driver'     => 'log',
        'host'       => 'mail.georgemusicalfestival.co.za',
        'port'       => 465,          // 465 = SSL, 587 = STARTTLS
        'encryption' => 'ssl',        // 'ssl' | 'tls' | ''
        'username'   => 'noreply@georgemusicalfestival.co.za',
        'password'   => '',
        'from_email' => 'noreply@georgemusicalfestival.co.za',
        'from_name'  => 'George Music Festival',
    ],

    // ── Bot protection (optional) ──────────────────────────────────────
    // Cloudflare Turnstile keys. Leave empty to rely on honeypot + timing + rate limits only.
    'turnstile' => [
        'site_key'   => '',
        'secret_key' => '',
    ],

    // ── Partner API ────────────────────────────────────────────────────
    // Key ACSA's systems send as the X-Api-Key header (or ?key=) for the
    // entry API and the read-only reporting API. Generate a long random value.
    'partner_api_key' => '',
    // Origins allowed to call the entry API from a browser (ACSA website).
    'cors_origins'    => ['https://www.airports.co.za'],

    // Shared secret your USSD gateway sends (?secret=) — leave empty to disable the USSD endpoint.
    'ussd_secret' => '',
];
