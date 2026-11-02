<?php
/**
 * Daily database backup: dumps every table to a gzipped .sql file, keeps the last 14 in
 * storage/backups and emails the file to the backup inboxes. Run from cron once a day:
 *   php /home/georgemu/public_html/cron/backup-database.php
 * Recipients come from config 'backup_emails' (list), defaulting to the two below.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../includes/bootstrap.php';

$recipients = config('backup_emails') ?: ['info@georgemusicalfestival.co.za', 'victor1bounceideas@gmail.com'];
$maxAttach = 20 * 1024 * 1024;   // most mailboxes refuse attachments over ~25 MB

$pdo = db();
$dbName = (string) config('db.name');
$sql = "-- George Musical Festival database backup\n-- Database: {$dbName}\n-- Made: " . date('Y-m-d H:i:s') . "\n\n"
    . "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n";
$counts = [];
foreach ($pdo->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_NUM) as [$table, $type]) {
    if ($type !== 'BASE TABLE') {
        continue;
    }
    $create = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_NUM)[1];
    $sql .= "DROP TABLE IF EXISTS `{$table}`;\n{$create};\n\n";
    $n = 0;
    $rows = $pdo->query("SELECT * FROM `{$table}`", PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $vals = array_map(fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), $row);
        $sql .= "INSERT INTO `{$table}` (`" . implode('`, `', array_keys($row)) . '`) VALUES (' . implode(', ', $vals) . ");\n";
        $n++;
    }
    $counts[$table] = $n;
    $sql .= "\n";
}
$sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";

$dir = APP_ROOT . '/storage/backups';
if (!is_dir($dir)) {
    @mkdir($dir, 0775, true);
}
$name = 'gmf-backup-' . date('Y-m-d-His') . '.sql.gz';
$gz = gzencode($sql, 9);
file_put_contents($dir . '/' . $name, $gz);
foreach (array_slice(array_reverse(glob($dir . '/gmf-backup-*.sql.gz') ?: []), 14) as $old) {
    @unlink($old);
}

$summary = '';
foreach (['entrants', 'subscribers', 'referral_clicks'] as $t) {
    if (isset($counts[$t])) {
        $summary .= '<li>' . e($t) . ': <strong>' . number_format($counts[$t]) . '</strong> rows</li>';
    }
}
$size = round(strlen($gz) / 1048576, 2);
$attach = strlen($gz) <= $maxAttach;
$html = mail_template('Daily database backup', '<p style="font-size:15px;line-height:1.6;">Backup of <strong>' . e($dbName) . '</strong> made ' . e(date('j F Y, H:i')) . " ({$size} MB).</p>"
    . '<ul style="font-size:15px;line-height:1.6;">' . $summary . '</ul>'
    . '<p style="font-size:15px;line-height:1.6;">' . ($attach
        ? 'The full database is attached as a gzipped SQL file. To restore it, import it in phpMyAdmin.'
        : 'The file is too large to attach. It is saved on the server in storage/backups/' . e($name) . '.')
    . '</p><p style="font-size:13px;color:#55707a;">This file contains personal information of entrants. Keep it private.</p>');

$ok = 0;
foreach ($recipients as $to) {
    $sent = send_mail($to, 'GMF database backup — ' . date('j M Y'), $html, '',
        $attach ? [['name' => $name, 'data' => $gz, 'type' => 'application/gzip']] : []);
    $ok += $sent ? 1 : 0;
}
echo date('Y-m-d H:i:s') . " backup {$name} ({$size} MB), emailed to {$ok}/" . count($recipients) . "\n";
