<?php
/**
 * Leads: business contacts who have NOT opted in, kept apart from entrants and the GMF mailing
 * list. They appear in Write an email as their own list for either competition.
 */
require __DIR__ . '/_layout.php';

$admin = require_admin('admin');
campaign_schema();

/** Bytes from a php.ini size such as "8M". */
$iniBytes = function (string $v): int {
    $n = (int) $v;
    return match (strtolower(substr(trim($v), -1))) { 'g' => $n << 30, 'm' => $n << 20, 'k' => $n << 10, default => $n };
};
$maxPost = $iniBytes((string) ini_get('post_max_size'));
$maxFile = $iniBytes((string) ini_get('upload_max_filesize'));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    flash('error', 'Those files are too big to upload together (limit ' . round($maxPost / 1048576) . ' MB). Upload fewer at a time.');
    redirect('admin/leads.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'import') {
        set_time_limit(600);
        $files = $_FILES['sheets'] ?? null;
        $added = $skipped = 0;
        $done = [];
        $errors = [];
        $st = db()->prepare('INSERT IGNORE INTO leads (email, company, region, brand, source, created_at) VALUES (?, ?, ?, ?, ?, ?)');
        foreach ((array) ($files['tmp_name'] ?? []) as $i => $tmp) {
            $name = (string) $files['name'][$i];
            if ((int) $files['error'][$i] !== UPLOAD_ERR_OK || !is_uploaded_file($tmp)) {
                $errors[] = $name . ' did not upload' . ((int) $files['error'][$i] === UPLOAD_ERR_INI_SIZE ? ' (over ' . round($maxFile / 1048576) . ' MB)' : '');
                continue;
            }
            try {
                $rows = spreadsheet_rows($tmp, $name);
                $head = array_map(fn ($h) => mb_strtolower(trim((string) $h)), $rows[0] ?? []);
                $ce = array_search('email', $head, true);
                $cc = array_search('company', $head, true);
                if ($ce === false) {
                    throw new RuntimeException('needs a column headed "Email"');
                }
                db()->beginTransaction();
                foreach (array_slice($rows, 1) as $r) {
                    $email = mb_strtolower(trim((string) ($r[$ce] ?? '')));
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $skipped++;
                        continue;
                    }
                    $company = $cc !== false ? mb_substr(trim((string) ($r[$cc] ?? '')), 0, 120) : '';
                    $st->execute([$email, $company ?: null, 'Western Cape', 'all', mb_substr($name, 0, 160), now()]);
                    $st->rowCount() ? $added++ : $skipped++;
                }
                db()->commit();
                $done[] = $name;
            } catch (Throwable $e) {
                if (db()->inTransaction()) {
                    db()->rollBack();
                }
                $errors[] = $name . ': ' . $e->getMessage();
            }
        }
        audit('leads.import', ['files' => $done, 'added' => $added, 'skipped' => $skipped, 'errors' => count($errors)]);
        if ($done) {
            flash('success', number_format($added) . ' leads added from ' . count($done) . ' file' . (count($done) === 1 ? '' : 's')
                . ($skipped ? ', ' . number_format($skipped) . ' skipped (already there or not a valid email)' : '') . '.');
        }
        if ($errors) {
            flash('error', implode(' · ', $errors));
        }
        if (!$done && !$errors) {
            flash('error', 'Choose one or more files to import.');
        }
    } elseif ($action === 'clear') {
        $n = q('DELETE FROM leads')->rowCount();
        audit('leads.clear', ['removed' => $n]);
        flash('success', number_format($n) . ' leads removed.');
    }
    redirect('admin/leads.php');
}

$total = (int) q('SELECT COUNT(*) FROM leads')->fetchColumn();
$unsub = (int) q('SELECT COUNT(*) FROM leads l JOIN email_suppressions s ON s.email = l.email')->fetchColumn();
$sources = q('SELECT source, COUNT(*) n FROM leads GROUP BY source ORDER BY source LIMIT 30')->fetchAll();

admin_head('Leads', 'leads');
?>
<div class="grid g-21">
  <div class="card">
    <h2>Western Cape business leads</h2>
    <div class="camp-kpis" style="grid-template-columns:repeat(2,1fr);margin-bottom:16px">
      <div><strong><?= number_format($total) ?></strong><span>Leads</span></div>
      <div><strong><?= number_format($unsub) ?></strong><span>Unsubscribed, skipped when sending</span></div>
    </div>
    <p class="mail-warn" style="margin-top:0"><i class="fa-solid fa-triangle-exclamation"></i> These people have not opted in. Send them one email inviting them to enter or join the list, and keep sends small: the mailbox allows only a few hundred emails a day.</p>
    <?php if ($sources): ?>
      <table class="t" style="margin-top:14px"><thead><tr><th>Imported from</th><th class="num">Leads</th></tr></thead><tbody>
        <?php foreach ($sources as $s): ?><tr><td><?= e($s['source'] ?: '—') ?></td><td class="num"><?= number_format((int) $s['n']) ?></td></tr><?php endforeach; ?>
      </tbody></table>
    <?php endif; ?>
    <p style="margin-top:16px;display:flex;gap:8px;flex-wrap:wrap">
      <a class="btn btn-primary" href="<?= e(url('admin/compose.php?brand=acsa')) ?>"><i class="fa-solid fa-pen-to-square"></i> Email for FLY FOR TWO</a>
      <a class="btn btn-primary" href="<?= e(url('admin/compose.php?brand=cellc')) ?>"><i class="fa-solid fa-pen-to-square"></i> Email for WIN A SAMSUNG</a>
    </p>
  </div>
  <div class="card">
    <h2>Import leads</h2>
    <form method="post" enctype="multipart/form-data" class="grid" style="gap:12px">
      <?= csrf_field() ?><input type="hidden" name="action" value="import">
      <div><label class="f">Excel (.xlsx) or CSV files with Email and Company columns. Select several at once</label><input class="in" type="file" name="sheets[]" multiple accept=".xlsx,.csv,text/csv" required></div>
      <button class="btn btn-primary" type="submit"><i class="fa-solid fa-file-import"></i> Import</button>
    </form>
    <p class="help">Up to <?= round($maxFile / 1048576) ?> MB per file and <?= round($maxPost / 1048576) ?> MB per upload. Leads are never mixed with entrants or the GMF mailing list, and only the email and company name are kept. Importing the same file twice adds nothing new.</p>
    <?php if ($total): ?>
      <form method="post" style="margin-top:16px" onsubmit="return confirm('Remove all <?= $total ?> leads?')"><?= csrf_field() ?><input type="hidden" name="action" value="clear"><button class="btn btn-danger" type="submit"><i class="fa-solid fa-trash"></i> Remove all leads</button></form>
    <?php endif; ?>
  </div>
</div>
<?php admin_foot();
