<?php
/**
 * Side-by-side numbers for the two competitions in the GMF admin. FLY FOR TWO (ACSA) lives in this
 * database; WIN A SAMSUNG (Cell C) lives in its own app and database under /cellc/, read here
 * with that app's own config. Totals only: entrant records stay in each competition's own admin.
 */
declare(strict_types=1);

/** A connection to the Cell C database, or null when it can't be reached. */
function cellc_pdo(): ?PDO
{
    static $pdo = false;
    if ($pdo !== false) {
        return $pdo;
    }
    $pdo = null;
    foreach ([dirname(__DIR__) . '/cellc/config/config.php', dirname(__DIR__, 2) . '/cellc/config/config.php'] as $path) {
        if (!is_file($path)) {
            continue;
        }
        $config = require $path;
        $c = is_array($config) ? ($config['db'] ?? null) : null;
        if (!$c) {
            continue;
        }
        try {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $c['host'], (int) ($c['port'] ?? 3306), $c['name']);
            $pdo = new PDO($dsn, $c['user'], $c['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            $offset = (new DateTime())->format('P');
            $pdo->exec("SET time_zone = '{$offset}'");
        } catch (Throwable $e) {
            $pdo = null;
        }
        break;
    }
    return $pdo;
}

/** Status, dates and entrant totals for one competition database. */
function campaign_snapshot(PDO $pdo): array
{
    $settings = $pdo->query("SELECT skey, svalue FROM settings WHERE skey IN ('comp_name', 'comp_open_at', 'comp_close_at')")->fetchAll(PDO::FETCH_KEY_PAIR);
    $open  = (string) ($settings['comp_open_at'] ?? '');
    $close = (string) ($settings['comp_close_at'] ?? '');
    $state = time() < strtotime($open) ? 'upcoming' : (time() > strtotime($close) ? 'closed' : 'open');
    $t = $pdo->query("SELECT
              SUM(status = 'verified')                                   AS verified,
              SUM(status = 'pending')                                    AS pending,
              SUM(status = 'verified' AND consent_marketing = 1)         AS marketing,
              SUM(status = 'verified' AND DATE(verified_at) = CURDATE()) AS today,
              SUM(email_status IS NOT NULL)                              AS email_problems
            FROM entrants")->fetch();
    return [
        'name'           => (string) ($settings['comp_name'] ?? ''),
        'open_at'        => $open,
        'close_at'       => $close,
        'state'          => $state,
        'verified'       => (int) $t['verified'],
        'pending'        => (int) $t['pending'],
        'marketing'      => (int) $t['marketing'],
        'today'          => (int) $t['today'],
        'email_problems' => (int) $t['email_problems'],
        'link_opens'     => (int) $pdo->query('SELECT COUNT(*) FROM referral_clicks')->fetchColumn(),
        'by_channel'     => $pdo->query("SELECT channel, SUM(status = 'verified') AS verified, SUM(status = 'pending') AS pending
                                         FROM entrants GROUP BY channel ORDER BY verified DESC")->fetchAll(),
        'by_town'        => $pdo->query("SELECT COALESCE(NULLIF(source_town, ''), NULLIF(town, ''), 'Unknown') AS town, COUNT(*) AS verified
                                         FROM entrants WHERE status = 'verified' GROUP BY 1 ORDER BY verified DESC LIMIT 10")->fetchAll(),
    ];
}

/** Email campaigns for one brand, newest first, each with its sent / not sent / failed counts. */
function campaign_email_stats(string $brand): array
{
    campaign_schema();
    $rows = q("SELECT c.id, c.name, c.status, c.started_at, c.finished_at,
                      SUM(m.status = 'sent')    AS sent,
                      SUM(m.status = 'queued')  AS queued,
                      SUM(m.status = 'failed')  AS failed,
                      SUM(m.status = 'skipped') AS skipped,
                      COUNT(m.id)               AS total
               FROM email_campaigns c LEFT JOIN email_queue m ON m.campaign_id = c.id
               WHERE c.brand = ? GROUP BY c.id ORDER BY c.id DESC", [$brand])->fetchAll();
    $totals = ['campaigns' => count($rows), 'sent' => 0, 'queued' => 0, 'failed' => 0, 'skipped' => 0];
    foreach ($rows as $r) {
        foreach (['sent', 'queued', 'failed', 'skipped'] as $k) {
            $totals[$k] += (int) $r[$k];
        }
    }
    return ['totals' => $totals, 'campaigns' => $rows];
}

/** One-line answer to "has it all gone out?" for a brand's email totals. */
function campaign_email_verdict(array $t): array
{
    if ($t['campaigns'] === 0) {
        return ['pill-muted', 'No campaigns yet'];
    }
    if ($t['failed'] > 0) {
        return ['pill-bad', number_format($t['failed']) . ' failed'];
    }
    if ($t['queued'] > 0) {
        return ['pill-warn', number_format($t['queued']) . ' not sent yet'];
    }
    return $t['sent'] > 0 ? ['pill-ok', 'All sent'] : ['pill-muted', 'Nothing sent yet'];
}

/** The campaign card shown on the Overview and at the top of the Cell C page. */
function campaign_card(string $title, string $partner, string $accent, ?array $s, array $mail, array $links): void
{
    [$verdictClass, $verdict] = campaign_email_verdict($mail['totals']);
    ?>
    <section class="card camp-card" style="--camp:<?= e($accent) ?>">
      <header class="camp-head">
        <div>
          <small><?= e($partner) ?></small>
          <h2><?= e($title) ?></h2>
        </div>
        <?php if ($s): ?><span class="pill <?= $s['state'] === 'open' ? 'pill-ok' : ($s['state'] === 'upcoming' ? 'pill-warn' : 'pill-bad') ?>">Entries <?= e($s['state']) ?></span><?php endif; ?>
      </header>
      <?php if ($s): ?>
        <p class="camp-dates"><?= e(fmt_date($s['open_at'], 'j M Y')) ?> → <strong><?= e(fmt_date($s['close_at'], 'j M Y')) ?></strong></p>
        <div class="camp-kpis">
          <div><strong><?= number_format($s['verified']) ?></strong><span>Verified entrants</span></div>
          <div><strong>+<?= number_format($s['today']) ?></strong><span>Today</span></div>
          <div><strong><?= number_format($s['link_opens']) ?></strong><span>Link opens</span></div>
          <div><strong><?= number_format($s['pending']) ?></strong><span>Unverified</span></div>
          <div><strong><?= number_format($s['marketing']) ?></strong><span>Marketing opt-in</span></div>
          <div class="<?= $s['email_problems'] ? 'is-bad' : '' ?>"><strong><?= number_format($s['email_problems']) ?></strong><span>Email problems</span></div>
        </div>
      <?php else: ?>
        <p class="muted">This campaign's database can't be reached from here. Its own admin still works.</p>
      <?php endif; ?>
      <div class="camp-mail">
        <span><i class="fa-solid fa-paper-plane"></i> Email campaigns: <?= number_format($mail['totals']['campaigns']) ?> · <?= number_format($mail['totals']['sent']) ?> sent</span>
        <span class="pill <?= e($verdictClass) ?>"><?= e($verdict) ?></span>
      </div>
      <div class="camp-links">
        <?php foreach ($links as [$label, $href, $external]): ?>
          <a class="btn" href="<?= e($href) ?>"<?= $external ? ' target="_blank" rel="noopener"' : '' ?>><?= e($label) ?></a>
        <?php endforeach; ?>
      </div>
    </section>
    <?php
}
