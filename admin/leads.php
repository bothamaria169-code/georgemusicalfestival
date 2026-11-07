<?php
/**
 * Cell C leads: business contacts who have NOT opted in, kept apart from entrants and the GMF
 * mailing list. They appear in Write an email (Cell C only) as their own list.
 */
require __DIR__ . '/_layout.php';

$admin = require_admin('admin');
campaign_schema();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'import' && !empty($_FILES['sheet']['tmp_name']) && is_uploaded_file($_FILES['sheet']['tmp_name'])) {
        set_time_limit(300);
        $name = (string) $_FILES['sheet']['name'];
        try {
            $rows = spreadsheet_rows($_FILES['sheet']['tmp_name'], $name);
            $head = array_map(fn ($h) => mb_strtolower(trim((string) $h)), $rows[0] ?? []);
            $ce = array_search('email', $head, true);
            $cc = array_search('company', $head, true);
            if ($ce === false) {
                throw new RuntimeException('The file needs a column headed "Email".');
            }
            $added = $skipped = 0;
            $st = db()->prepare('INSERT IGNORE INTO leads (email, company, region, brand, source, created_at) VALUES (?, ?, ?, ?, ?, ?)');
            db()->beginTransaction();
            foreach (array_slice($rows, 1) as $r) {
                $email = mb_strtolower(trim((string) ($r[$ce] ?? '')));
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $skipped++;
                    continue;
                }
                $company = $cc !== false ? mb_substr(trim((string) ($r[$cc] ?? '')), 0, 120) : '';
                $st->execute([$email, $company ?: null, 'Western Cape', 'cellc', mb_substr($name, 0, 160), now()]);
                $st->rowCount() ? $added++ : $skipped++;
            }
            db()->commit();
            audit('leads.import', ['file' => $name, 'added' => $added, 'skipped' => $skipped]);
            flash('success', number_format($added) . ' leads added' . ($skipped ? ', ' . number_format($skipped) . ' skipped (already there or not a valid email)' : '') . '.');
        } catch (Throwable $e) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            flash('error', $e->getMessage());
        }
    } elseif ($action === 'clear') {
        $n = q("DELETE FROM leads WHERE brand = 'cellc'")->rowCount();
        audit('leads.clear', ['removed' => $n]);
        flash('success', number_format($n) . ' leads removed.');
    }
    redirect('admin/leads.php');
}

$total = (int) q("SELECT COUNT(*) FROM leads WHERE brand = 'cellc'")->fetchColumn();
$unsub = (int) q("SELECT COUNT(*) FROM leads l JOIN email_suppressions s ON s.email = l.email WHERE l.brand = 'cellc'")->fetchColumn();
$sources = q("SELECT source, COUNT(*) n FROM leads WHERE brand = 'cellc' GROUP BY source ORDER BY n DESC LIMIT 20")->fetchAll();

admin_head('Cell C leads', 'leads');
?>
<div class="grid g-21">
  <div class="card">
    <h2>Western Cape business leads</h2>
    <div class="camp-kpis" style="grid-template-columns:repeat(2,1fr);margin-bottom:16px">
      <div><strong><?= number_format($total) ?></strong><span>Leads</span></div>
      <div><strong><?= number_format($unsub) ?></strong><span>Unsubscribed, skipped when sending</span></div>
    </div>
    <p class="mail-warn" style="margin-top:0"><i class="fa-solid fa-triangle-exclamation"></i> These people have not opted in. Send them one email inviting them to enter WIN A SAMSUNG or join the list, and keep sends small: the mailbox allows only a few hundred emails a day.</p>
    <?php if ($sources): ?>
      <table class="t" style="margin-top:14px"><thead><tr><th>Imported from</th><th class="num">Leads</th></tr></thead><tbody>
        <?php foreach ($sources as $s): ?><tr><td><?= e($s['source'] ?: '—') ?></td><td class="num"><?= number_format((int) $s['n']) ?></td></tr><?php endforeach; ?>
      </tbody></table>
    <?php endif; ?>
    <p style="margin-top:16px"><a class="btn btn-primary" href="<?= e(url('admin/compose.php?brand=cellc')) ?>"><i class="fa-solid fa-pen-to-square"></i> Write an email</a></p>
  </div>
  <div class="card">
    <h2>Import leads</h2>
    <form method="post" enctype="multipart/form-data" class="grid" style="gap:12px">
      <?= csrf_field() ?><input type="hidden" name="action" value="import">
      <div><label class="f">Excel (.xlsx) or CSV file with Email and Company columns</label><input class="in" type="file" name="sheet" accept=".xlsx,.csv,text/csv" required></div>
      <button class="btn btn-primary" type="submit"><i class="fa-solid fa-file-import"></i> Import</button>
    </form>
    <p class="help">Leads are only used for Cell C emails and never mixed with entrants or the GMF mailing list. Only the email and company name are kept.</p>
    <?php if ($total): ?>
      <form method="post" style="margin-top:16px" onsubmit="return confirm('Remove all <?= $total ?> Cell C leads?')"><?= csrf_field() ?><input type="hidden" name="action" value="clear"><button class="btn btn-danger" type="submit"><i class="fa-solid fa-trash"></i> Remove all leads</button></form>
    <?php endif; ?>
  </div>
</div>
<?php admin_foot();
