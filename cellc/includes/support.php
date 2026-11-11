<?php
/**
 * "Chat with us": competition help requests sent from the floating button on the website.
 * Messages are kept in this app's own database (support_messages) and answered from the
 * GMF admin → Support page. Only what the visitor types into the form is stored.
 */
declare(strict_types=1);

const SUPPORT_TOPICS = [
    'code'    => "I didn't get my code email",
    'signin'  => 'Signing in or my entry',
    'link'    => 'My share link or score',
    'prize'   => 'Prizes and winners',
    'other'   => 'Something else about the competition',
];

function support_schema(?PDO $pdo = null): void
{
    static $done = [];
    $key = $pdo ? spl_object_id($pdo) : 0;
    if (isset($done[$key])) {
        return;
    }
    $done[$key] = true;
    $sql = "CREATE TABLE IF NOT EXISTS support_messages (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name        VARCHAR(120) NOT NULL,
        email       VARCHAR(190) NOT NULL,
        topic       VARCHAR(20)  NOT NULL,
        message     TEXT         NOT NULL,
        page        VARCHAR(255) NULL,
        status      ENUM('open','answered','closed') NOT NULL DEFAULT 'open',
        reply       TEXT         NULL,
        replied_at  DATETIME     NULL,
        replied_by  VARCHAR(120) NULL,
        ip_hash     CHAR(64)     NULL,
        created_at  DATETIME     NOT NULL,
        KEY ix_status (status, created_at),
        KEY ix_email (email)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo ? $pdo->exec($sql) : q($sql);
}

/**
 * Validate and store one help request. Returns ['ok' => true, 'id' => n] or ['ok' => false, 'error' => text].
 * Sends the visitor a short "we got your message" email and tells the GMF team.
 */
function support_submit(array $in): array
{
    support_schema();
    $name = trim(mb_substr((string) ($in['name'] ?? ''), 0, 120));
    $email = mb_strtolower(trim((string) ($in['email'] ?? '')));
    $topic = (string) ($in['topic'] ?? '');
    $message = trim(mb_substr((string) ($in['message'] ?? ''), 0, 2000));
    $page = mb_substr((string) ($in['page'] ?? ''), 0, 255);

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Please give your name and a valid email address so we can reply.'];
    }
    if (!isset(SUPPORT_TOPICS[$topic])) {
        return ['ok' => false, 'error' => 'Please choose what your question is about.'];
    }
    if (mb_strlen($message) < 5) {
        return ['ok' => false, 'error' => 'Please tell us a little more about the problem.'];
    }
    if (!rate_limit('support_ip', client_ip(), 5, 3600) || !rate_limit('support_email', $email, 5, 86400)) {
        return ['ok' => false, 'error' => 'You have sent a few messages already. We will reply to those soon.'];
    }

    q('INSERT INTO support_messages (name, email, topic, message, page, ip_hash, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
        [$name, $email, $topic, $message, $page, app_hash(client_ip()), now()]);
    $id = (int) db()->lastInsertId();

    $comp = (string) setting('comp_name');
    $first = e(explode(' ', $name)[0]);
    send_mail($email, "We got your message — {$comp} (ref #{$id})", mail_template('Thanks, we got your message',
        "<p style=\"margin:0 0 14px;font-size:15px;line-height:1.6;\">Hi {$first},</p>"
        . "<p style=\"margin:0 0 14px;font-size:15px;line-height:1.6;\">Thanks for contacting the {$comp} team about: <strong>" . e(SUPPORT_TOPICS[$topic]) . "</strong>. We'll reply to this email address, usually within one working day.</p>"
        . "<p style=\"margin:0;font-size:13px;color:#6b645e;\">Your reference: #{$id}</p>"));

    $team = (string) setting('contact_email');
    if (filter_var($team, FILTER_VALIDATE_EMAIL) && strcasecmp($team, $email) !== 0) {
        send_mail($team, "Website help request #{$id} — {$comp}: " . SUPPORT_TOPICS[$topic], mail_template('New help request',
            '<p style="margin:0 0 14px;font-size:15px;line-height:1.6;">Someone asked for help on the ' . e($comp) . ' website. Answer it in the GMF admin → <strong>Support</strong>.</p>'
            . '<p style="margin:0;font-size:13px;color:#6b645e;">Reference #' . $id . ' · ' . e(SUPPORT_TOPICS[$topic]) . '</p>'));
    }
    return ['ok' => true, 'id' => $id];
}
