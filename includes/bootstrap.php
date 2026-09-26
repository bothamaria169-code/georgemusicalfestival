<?php
/**
 * Core bootstrap: config, database, session, settings and shared helpers.
 * Every page starts with: require __DIR__ . '/includes/bootstrap.php';
 */
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

$configFile = APP_ROOT . '/config/config.php';
if (!is_file($configFile)) {
    if (basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'install.php') {
        http_response_code(503);
        exit('Platform not configured yet. Copy config/config.sample.php to config/config.php, then run install.php.');
    }
    $configFile = APP_ROOT . '/config/config.sample.php';
}
$GLOBALS['__config'] = require $configFile;

date_default_timezone_set(config('timezone', 'Africa/Johannesburg'));

if (config('env') === 'production') {
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
    ini_set('log_errors', '1');
    ini_set('error_log', APP_ROOT . '/storage/logs/php-error.log');
}

// The VPS panel terminates SSL and forwards plain HTTP; honour its forwarded scheme.
if (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
    $_SERVER['HTTPS'] = 'on';
}

if (session_status() === PHP_SESSION_NONE && PHP_SAPI !== 'cli') {
    session_name('gmf_sess');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/competition.php';
require_once __DIR__ . '/email-health.php';
require_once __DIR__ . '/legal-defaults.php';

/* ──────────────────────────── Config & DB ──────────────────────────── */

function config(string $key, $default = null)
{
    $value = $GLOBALS['__config'];
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $c = config('db');
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $c['host'], (int) ($c['port'] ?? 3306), $c['name']);
        $pdo = new PDO($dsn, $c['user'], $c['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $offset = (new DateTime())->format('P');
        $pdo->exec("SET time_zone = '{$offset}'");
    }
    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

/* ──────────────────────────── URLs ──────────────────────────── */

function base_url(): string
{
    static $base = null;
    if ($base === null) {
        $base = rtrim((string) config('base_url', ''), '/');
        if ($base === '') {
            $https  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
            $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $docRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '');
            $appRoot = str_replace('\\', '/', APP_ROOT);
            $path   = ($docRoot && stripos($appRoot, $docRoot) === 0) ? substr($appRoot, strlen($docRoot)) : '';
            $base   = ($https ? 'https' : 'http') . '://' . $host . rtrim($path, '/');
        }
    }
    return $base;
}

function url(string $path = ''): string
{
    return base_url() . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $file = APP_ROOT . '/' . ltrim($path, '/');
    $v = is_file($file) ? filemtime($file) : 1;
    return url($path) . '?v=' . $v;
}

/** Resolve a media src: absolute URLs pass through, local paths get the base URL. */
function media_url(?string $src): string
{
    if (!$src) {
        return '';
    }
    return preg_match('#^https?://#i', $src) ? $src : url($src);
}

function redirect(string $path): void
{
    header('Location: ' . (preg_match('#^https?://#', $path) ? $path : url($path)));
    exit;
}

/* ──────────────────────────── Output helpers ──────────────────────────── */

function e($s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function json_out(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function lines(string $text): array
{
    return array_values(array_filter(array_map('trim', preg_split('/\R/', $text) ?: [])));
}

function fmt_date(?string $date, string $format = 'j F Y'): string
{
    return $date ? date($format, strtotime($date)) : '';
}

/* ──────────────────────────── Security ──────────────────────────── */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_ok(?string $token = null): bool
{
    $token = $token ?? ($_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return is_string($token) && hash_equals(csrf_token(), $token);
}

function app_hash(string $value): string
{
    return hash_hmac('sha256', $value, (string) config('app_secret'));
}

/**
 * Visitor IP. Behind the VPS panel's NAT/reverse proxy REMOTE_ADDR is the proxy's
 * private address, so trust X-Forwarded-For / X-Real-IP only when the direct peer is private.
 */
function client_ip(): string
{
    $remote = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $public = fn ($ip) => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    if ($public($remote)) {
        return $remote;
    }
    $chain = array_merge(
        array_map('trim', explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''))),
        [trim((string) ($_SERVER['HTTP_X_REAL_IP'] ?? ''))]
    );
    foreach ($chain as $ip) {
        if ($ip !== '' && $public($ip)) {
            return $ip;
        }
    }
    return $remote;
}

function ip_hash(): string
{
    return app_hash('ip|' . client_ip());
}

/** Signed timestamp used to reject forms submitted faster than a human can type. */
function form_timer(): string
{
    $t = (string) time();
    return $t . '.' . substr(app_hash('timer|' . $t), 0, 16);
}

function form_timer_ok(?string $token, int $minSeconds = 3): bool
{
    if (!$token || !str_contains($token, '.')) {
        return false;
    }
    [$t, $sig] = explode('.', $token, 2);
    if (!hash_equals(substr(app_hash('timer|' . $t), 0, 16), $sig)) {
        return false;
    }
    $age = time() - (int) $t;
    return $age >= $minSeconds && $age < 7200;
}

function turnstile_enabled(): bool
{
    return config('turnstile.site_key') && config('turnstile.secret_key');
}

function turnstile_ok(?string $token): bool
{
    if (!turnstile_enabled()) {
        return true;
    }
    if (!$token) {
        return false;
    }
    $ctx = stream_context_create(['http' => [
        'method'  => 'POST',
        'header'  => 'Content-Type: application/x-www-form-urlencoded',
        'content' => http_build_query(['secret' => config('turnstile.secret_key'), 'response' => $token, 'remoteip' => client_ip()]),
        'timeout' => 8,
    ]]);
    $res = @file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, $ctx);
    $data = $res ? json_decode($res, true) : null;
    return !empty($data['success']);
}

/**
 * Sliding-window rate limit. Returns true when the action is allowed (and records it).
 */
function rate_limit(string $action, string $subject, int $max, int $seconds): bool
{
    $subjectHash = app_hash($subject);
    $since = date('Y-m-d H:i:s', time() - $seconds);
    $count = (int) q('SELECT COUNT(*) FROM rate_events WHERE action = ? AND subject = ? AND created_at > ?', [$action, $subjectHash, $since])->fetchColumn();
    if ($count >= $max) {
        return false;
    }
    q('INSERT INTO rate_events (action, subject, created_at) VALUES (?, ?, ?)', [$action, $subjectHash, now()]);
    if (random_int(1, 200) === 1) {
        q('DELETE FROM rate_events WHERE created_at < ?', [date('Y-m-d H:i:s', time() - 86400)]);
    }
    return true;
}

/* ──────────────────────────── Settings ──────────────────────────── */

function setting_defaults(): array
{
    return [
        // Homepage hero (competition-first)
        'hero_kicker'       => 'ACSA × GMF: Getting the Unflown Flying',
        'hero_headline'     => 'Win a ticket for two',
        'hero_text'         => 'In partnership with Airports Company South Africa (ACSA), GMF is making air travel more accessible and inspiring new journeys across South Africa. Enter for your chance to win a Weekend Getaway for Two and experience the journey for yourself.',

        // Event
        'event_name'        => 'Eden Soul & Jazz Reimagined',
        'event_tagline'     => 'A day of soul, jazz and Garden Route culture — reimagined for a new generation.',
        'event_date'        => '2026-10-03',
        'event_time'        => 'Gates open 12:00',
        'event_venue'       => 'Thembalethu Stadium, George',
        'ticket_url'        => '',
        'ticket_note'       => 'Tickets on sale soon',
        'hero_video_url'    => '',
        'announcement'      => 'FLY FOR TWO is live — enter free and win a weekend getaway for two.',
        'contact_email'     => 'info@georgemusicalfestival.co.za',
        'contact_phone'     => '',
        'social_facebook'   => '',
        'social_instagram'  => '',
        'social_tiktok'     => '',
        'upcoming_events'   => "12 Dec 2026|Fill Up Thembalethu Stadium — 2nd Annual|Thembalethu Stadium, George\n1 Jan 2027|Garden Route New Year's Day Picnic|Garden Route",

        // Competition
        'comp_name'         => 'FLY FOR TWO',
        'comp_open_at'      => '2026-09-24 00:00:00',
        'comp_close_at'     => '2026-10-31 23:59:59',
        'prize_headline'    => 'A Weekend Getaway for Two',
        'travel_question'   => 'Have you flown before?',
        'travel_options'    => "No, I haven't flown yet\nOccasionally — once or twice a year\nFrequently — three or more times a year",
        'age_groups'        => "Under 18
18–24
25–34
35–44
45–54
55+",
        'popia_consent_text'=> 'I consent to Airports Company South Africa (ACSA) collecting and processing my personal information for the purposes of the Passenger Mobilisation Programme.',
        'towns'             => "George\nThembalethu\nMossel Bay\nOudtshoorn\nPlettenberg Bay\nKnysna\nOther",
        'draw_reassurance'  => 'The winners are the two entrants whose personal links are opened by the most different people. The leaderboard is frozen when entries close, checked for fair play and recorded before the winners are revealed.',
        'share_bonus_cap'   => '0',
        'referral_entries_per_signup' => '0',
        'referral_entry_cap'          => '0',
        'referral_reward_text'        => 'Every open from a different person counts. Fake or automated clicks are removed.',
        'otp_cooldown'      => '45',
        'otp_ttl_minutes'   => '10',
        'otp_max_attempts'  => '5',
        'terms_html'        => default_terms_html(),
        'privacy_html'      => default_privacy_html(),
    ];
}

function setting(string $key, $default = null)
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach (q('SELECT skey, svalue FROM settings')->fetchAll() as $row) {
                $cache[$row['skey']] = $row['svalue'];
            }
        } catch (Throwable $e) {
            // settings table not installed yet
        }
    }
    if (array_key_exists($key, $cache) && $cache[$key] !== null) {
        return $cache[$key];
    }
    return $default ?? (setting_defaults()[$key] ?? null);
}

function save_setting(string $key, ?string $value): void
{
    q('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)', [$key, $value]);
}

/* ──────────────────────────── Website media ──────────────────────────── */

function media_items(string $kind, int $limit = 0, bool $featuredFirst = true): array
{
    $sql = 'SELECT * FROM media WHERE kind = ? ORDER BY ' . ($featuredFirst ? 'featured DESC, ' : '') . 'sort_order ASC, id ASC';
    if ($limit > 0) {
        $sql .= ' LIMIT ' . $limit;
    }
    try {
        return q($sql, [$kind])->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

/** Poster image for a video: explicit poster, else the YouTube thumbnail, else a festival photo. */
function video_poster(array $v): string
{
    if (!empty($v['poster'])) {
        return media_url($v['poster']);
    }
    if (preg_match('#(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([\w-]{11})#', (string) $v['src'], $m)) {
        return 'https://i.ytimg.com/vi/' . $m[1] . '/hqdefault.jpg';
    }
    return url('assets/img/gallery/crowd-night.jpg');
}

/** "12 Dec 2026|Title|Venue" lines → rows. */
function upcoming_events(): array
{
    $rows = [];
    foreach (lines((string) setting('upcoming_events')) as $line) {
        $p = array_map('trim', explode('|', $line));
        $rows[] = ['date' => $p[0] ?? '', 'title' => $p[1] ?? '', 'venue' => $p[2] ?? ''];
    }
    return $rows;
}

/* ──────────────────────────── Sessions: entrants & admins ──────────────────────────── */

function current_entrant(): ?array
{
    static $cached = false;
    if ($cached !== false) {
        return $cached;
    }
    $cached = null;
    if (!empty($_SESSION['entrant_id'])) {
        $row = q("SELECT * FROM entrants WHERE id = ? AND status = 'verified'", [$_SESSION['entrant_id']])->fetch();
        $cached = $row ?: null;
    }
    return $cached;
}

function login_entrant(int $id): void
{
    session_regenerate_id(true);
    $_SESSION['entrant_id'] = $id;
    q('UPDATE entrants SET last_login_at = ? WHERE id = ?', [now(), $id]);
}

function current_admin(): ?array
{
    static $cached = false;
    if ($cached !== false) {
        return $cached;
    }
    $cached = null;
    if (!empty($_SESSION['admin_id'])) {
        $cached = q('SELECT id, name, email, role FROM admins WHERE id = ?', [$_SESSION['admin_id']])->fetch() ?: null;
    }
    return $cached;
}

/** Viewers (ACSA read access) may see reports; only admins may change anything. */
function require_admin(string $role = 'viewer'): array
{
    $admin = current_admin();
    if (!$admin) {
        redirect('admin/login.php');
    }
    if ($role === 'admin' && $admin['role'] !== 'admin') {
        http_response_code(403);
        exit('This area is limited to GMF administrators.');
    }
    return $admin;
}

function audit(string $action, $detail = null): void
{
    q('INSERT INTO audit_log (admin_id, action, detail, ip_hash, created_at) VALUES (?, ?, ?, ?, ?)', [
        current_admin()['id'] ?? null,
        $action,
        is_string($detail) || $detail === null ? $detail : json_encode($detail, JSON_UNESCAPED_SLASHES),
        ip_hash(),
        now(),
    ]);
}
