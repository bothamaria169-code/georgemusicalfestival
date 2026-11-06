<?php
/** System check: what the live server is running, for GMF admins. Shows no passwords. */
require __DIR__ . '/_layout.php';
require dirname(__DIR__) . '/includes/campaign-overview.php';

require_admin('admin');

/** Row for the checks table: [label, ok?, detail]. */
$probe = function (string $label, callable $fn): array {
    try {
        $detail = $fn();
        return [$label, true, is_string($detail) ? $detail : 'OK'];
    } catch (Throwable $e) {
        return [$label, false, $e->getMessage()];
    }
};

$tables = ['settings', 'entrants', 'entries', 'referral_clicks', 'draws', 'email_campaigns', 'email_queue', 'email_suppressions', 'email_templates', 'subscribers', 'restore_requests', 'orphan_clicks', 'ref_aliases', 'score_adjustments', 'leaderboard_carryover'];
$checks = [
    $probe('Database', fn () => (string) q('SELECT VERSION()')->fetchColumn() . ' · ' . (string) q('SELECT @@sql_mode')->fetchColumn()),
    $probe('Campaign tables set up', function () {
        campaign_schema();
        return 'Setting: ' . (string) q("SELECT svalue FROM settings WHERE skey = 'campaigns_schema'")->fetchColumn();
    }),
    $probe('Campaign columns', fn () => implode(', ', array_column(q('SHOW COLUMNS FROM email_campaigns')->fetchAll(), 'Field'))),
    $probe('Queue columns', fn () => implode(', ', array_column(q('SHOW COLUMNS FROM email_queue')->fetchAll(), 'Field'))),
    $probe('Email templates', fn () => count(campaign_templates('acsa')) . ' ACSA, ' . count(campaign_templates('cellc')) . ' Cell C'),
    $probe('Campaign list', fn () => (string) count(campaign_email_stats('acsa')['campaigns']) . ' ACSA, ' . count(campaign_email_stats('cellc')['campaigns']) . ' Cell C' . (!empty(campaign_email_stats('acsa')['error']) ? ' (read failed)' : '')),
    $probe('Cell C database', function () {
        $p = cellc_pdo();
        if (!$p) {
            throw new RuntimeException('Cannot connect, or cellc/config/config.php was not found.');
        }
        return 'Connected · ' . (string) $p->query('SELECT COUNT(*) FROM entrants')->fetchColumn() . ' entrants';
    }),
];
foreach ($tables as $table) {
    $checks[] = $probe('Table ' . $table, fn () => number_format((int) q("SELECT COUNT(*) FROM {$table}")->fetchColumn()) . ' rows');
}

$extensions = [];
foreach (['pdo_mysql', 'mbstring', 'openssl', 'zip', 'simplexml', 'imap', 'zlib'] as $ext) {
    $extensions[$ext] = extension_loaded($ext);
}
$mail = (array) config('mail', []);
$mailInfo = [
    'Driver'   => (string) ($mail['driver'] ?? ''),
    'Host'     => (string) ($mail['host'] ?? ''),
    'From'     => (string) ($mail['from_email'] ?? ''),
    'Password' => (string) ($mail['password'] ?? '') !== '' ? 'Set' : 'Missing',
    'Bulk mail service' => is_array(config('bulk_mail')) && !empty(config('bulk_mail')['host']) ? 'Set' : 'Using the main mailbox',
];

$logFile = APP_ROOT . '/storage/logs/php-error.log';
$logLines = is_readable($logFile) ? array_slice(file($logFile, FILE_IGNORE_NEW_LINES) ?: [], -25) : [];

admin_head('System check', 'system');
?>
<div class="grid g-2" style="margin-bottom:16px;">
  <div class="card">
    <h2>Server</h2>
    <table class="t"><tbody>
      <tr><td>PHP</td><td><?= e(PHP_VERSION) ?></td></tr>
      <?php foreach ($extensions as $ext => $on): ?>
        <tr><td><?= e($ext) ?></td><td><span class="pill <?= $on ? 'pill-ok' : 'pill-bad' ?>"><?= $on ? 'Loaded' : 'Missing' ?></span></td></tr>
      <?php endforeach; ?>
    </tbody></table>
  </div>
  <div class="card">
    <h2>Email</h2>
    <table class="t"><tbody>
      <?php foreach ($mailInfo as $label => $value): ?><tr><td><?= e($label) ?></td><td><?= e($value) ?></td></tr><?php endforeach; ?>
    </tbody></table>
  </div>
</div>
<div class="card" style="margin-bottom:16px;">
  <h2>Checks</h2>
  <div class="t-wrap"><table class="t"><tbody>
    <?php foreach ($checks as [$label, $ok, $detail]): ?>
      <tr><td style="white-space:nowrap"><?= e($label) ?></td><td><span class="pill <?= $ok ? 'pill-ok' : 'pill-bad' ?>"><?= $ok ? 'OK' : 'Failed' ?></span></td><td><?= e($detail) ?></td></tr>
    <?php endforeach; ?>
  </tbody></table></div>
</div>
<div class="card">
  <h2>Latest PHP errors</h2>
  <?php if ($logLines): ?>
    <pre style="white-space:pre-wrap;font-size:.76rem;margin:0"><?= e(implode("\n", $logLines)) ?></pre>
  <?php else: ?>
    <p class="muted" style="margin:0">Nothing in the log.</p>
  <?php endif; ?>
</div>
<?php admin_foot();
