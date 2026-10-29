<?php
/**
 * Entry restoration after the October 2026 server loss.
 *
 * Share links (/r/CODE) that went out before the loss still circulate. Until the owner
 * restores their entry, opens of those links are kept in orphan_clicks. A restore claim
 * (email-verified, then approved by an admin or by the device cookie) attaches the old
 * code to the entrant and moves its captured opens into referral_clicks. Scores from
 * before the loss come back as admin adjustments backed by evidence.
 */
declare(strict_types=1);

/** Accepts a bare code or a pasted link (".../r/QEP6GP9", ".../cellc/r/QEP6GP9"). */
function normalize_ref_code(string $raw): string
{
    $raw = trim($raw);
    if (preg_match('#/r/([A-Za-z0-9]{4,12})#', $raw, $m)) {
        $raw = $m[1];
    }
    return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $raw) ?? '');
}

/** Codes are 7 characters from new_ref_code()'s alphabet. Anything else was never issued. */
function is_ref_code_shape(string $code): bool
{
    return (bool) preg_match('/^[A-HJ-NP-Z2-9]{7}$/', $code);
}

/** The entrant a code leads to (own code or alias), in any status. */
function code_owner(string $code): ?array
{
    $row = q('SELECT * FROM entrants WHERE ref_code = ?', [$code])->fetch();
    if (!$row) {
        $row = q('SELECT e.* FROM ref_aliases a JOIN entrants e ON e.id = a.entrant_id WHERE a.ref_code = ?', [$code])->fetch();
    }
    return $row ?: null;
}

/** True when a code is taken, or was in circulation before the loss and may still be claimed. */
function ref_code_in_use(string $code): bool
{
    return (bool) q('SELECT 1 FROM entrants WHERE ref_code = ?
                     UNION SELECT 1 FROM ref_aliases WHERE ref_code = ?
                     UNION SELECT 1 FROM orphan_clicks WHERE ref_code = ?
                     UNION SELECT 1 FROM restore_requests WHERE ref_code = ? LIMIT 1', [$code, $code, $code, $code])->fetchColumn();
}

/** Every code that leads to this entrant: their own plus any aliases. */
function entrant_codes(int $entrantId): array
{
    $own = (string) q('SELECT ref_code FROM entrants WHERE id = ?', [$entrantId])->fetchColumn();
    $aliases = q('SELECT ref_code FROM ref_aliases WHERE entrant_id = ?', [$entrantId])->fetchAll(PDO::FETCH_COLUMN);
    return array_values(array_filter(array_unique(array_merge([$own], $aliases))));
}

/** Codes this browser registered or signed in with (the year-long gmf_own cookie). */
function device_codes(): array
{
    $codes = array_map('normalize_ref_code', explode(',', (string) ($_COOKIE['gmf_own'] ?? '')));
    return array_values(array_filter(array_unique($codes), 'is_ref_code_shape'));
}

/** Codes on this device that nobody has restored yet — the "welcome back" prompt. */
function unclaimed_device_codes(): array
{
    return array_values(array_filter(device_codes(), fn ($c) => !code_owner($c)));
}

/**
 * An open of a share link nobody has restored yet. Same fair-play rules as live opens:
 * only while entries are open, never from the owner's own device, at most 3 per network per day.
 */
function record_orphan_click(string $code): void
{
    if (!is_ref_code_shape($code) || comp_state() !== 'open' || in_array($code, device_codes(), true)) {
        return;
    }
    $me = current_entrant();
    if ($me && q("SELECT 1 FROM restore_requests WHERE entrant_id = ? AND ref_code = ? AND status IN ('email','review')", [$me['id'], $code])->fetchColumn()) {
        return;
    }
    if (!rate_limit('orphan_click', client_ip() . '|' . $code, 3, 86400)) {
        return;
    }
    q('INSERT IGNORE INTO orphan_clicks (ref_code, ip_hash, visitor_id, user_agent, clicked_at) VALUES (?, ?, ?, ?, ?)', [
        $code, ip_hash(), visitor_id(), mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255), now(),
    ]);
}

function orphan_open_count(string $code): int
{
    return (int) q('SELECT COUNT(*) FROM orphan_clicks WHERE ref_code = ?', [$code])->fetchColumn();
}

/** The entrant's restore claims that are not finished yet. */
function open_restore_requests(int $entrantId): array
{
    return q("SELECT * FROM restore_requests WHERE entrant_id = ? AND status IN ('email','review') ORDER BY id DESC", [$entrantId])->fetchAll();
}

/**
 * The claimant proved they own the email. Confirms a new entrant's entry, moves their claims
 * to review, and approves straight away when this phone registered the code originally.
 * Returns the claims that were approved automatically.
 */
function restore_email_confirmed(int $entrantId): array
{
    $e = q('SELECT * FROM entrants WHERE id = ?', [$entrantId])->fetch();
    if ($e && $e['status'] === 'pending') {
        verify_entrant($entrantId, 'restore');
    }
    $claims = q("SELECT * FROM restore_requests WHERE entrant_id = ? AND status = 'email'", [$entrantId])->fetchAll();
    q("UPDATE restore_requests SET status = 'review' WHERE entrant_id = ? AND status = 'email'", [$entrantId]);

    $approved = [];
    if (setting('restore_auto_approve_device') === '1') {
        foreach (q("SELECT * FROM restore_requests WHERE entrant_id = ? AND status = 'review' AND device_match = 1", [$entrantId])->fetchAll() as $r) {
            $contested = (int) q("SELECT COUNT(*) FROM restore_requests WHERE ref_code = ? AND entrant_id <> ? AND status IN ('review','approved')", [$r['ref_code'], $entrantId])->fetchColumn();
            if (!$contested && restore_approve((int) $r['id'], null, 'Approved automatically: this phone registered the code originally')['ok']) {
                $approved[] = $r['ref_code'];
            }
        }
    }
    if ($claims) {
        notify_restore(q('SELECT * FROM entrants WHERE id = ?', [$entrantId])->fetch(), $claims, $approved);
    }
    return $approved;
}

/** Email the competition inbox a record of every restored entrant, so the team holds a copy off the server. */
function notify_restore(array $entrant, array $claims, array $approved): void
{
    $to = (string) setting('contact_email');
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return;
    }
    $rows = [
        'Name'           => $entrant['full_name'],
        'Email'          => $entrant['email'],
        'Phone'          => display_phone($entrant['phone']),
        'Town'           => $entrant['town'] ?: '—',
        'Age group'      => $entrant['age_group'] ?: '—',
        'Marketing opt-in' => $entrant['consent_marketing'] ? 'yes' : 'no',
        'Current link'   => ref_link($entrant),
    ];
    foreach ($claims as $c) {
        $rows['Old link ' . $c['ref_code']] = (in_array($c['ref_code'], $approved, true) ? 'restored automatically (same phone)' : 'waiting for review in Admin → Restoration')
            . ($c['claimed_opens'] !== null ? ' · says score was ' . (int) $c['claimed_opens'] : '')
            . ($c['evidence'] ? ' · note: ' . $c['evidence'] : '');
    }
    $table = '';
    foreach ($rows as $k => $v) {
        $table .= '<tr><td style="padding:4px 12px 4px 0;color:#55707a;vertical-align:top;white-space:nowrap">' . e($k) . '</td><td style="padding:4px 0">' . e($v) . '</td></tr>';
    }
    send_mail($to, 'Entry restored: ' . $entrant['full_name'] . ' (' . implode(', ', array_column($claims, 'ref_code')) . ')',
        mail_template('Entry restored', '<p style="margin:0 0 14px;font-size:15px;line-height:1.6;">An entrant confirmed their email and restored their entry on ' . e(date('j F Y H:i')) . '.</p><table style="font-size:14px;border-collapse:collapse">' . $table . '</table>'));
}

/**
 * Give the old code back to the claimant and move the opens captured since the loss.
 * Returns ['ok' => true] or ['ok' => false, 'error' => msg].
 */
function restore_approve(int $requestId, ?int $adminId, string $note = ''): array
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $r = q('SELECT * FROM restore_requests WHERE id = ? FOR UPDATE', [$requestId])->fetch();
        if (!$r || $r['status'] !== 'review') {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'This claim is not waiting for review.'];
        }
        $code = $r['ref_code'];
        $id = (int) $r['entrant_id'];
        $e = q("SELECT * FROM entrants WHERE id = ? FOR UPDATE", [$id])->fetch();
        if (!$e || $e['status'] !== 'verified') {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'The claimant has no confirmed entry (disqualified or never verified).'];
        }
        $owner = code_owner($code);
        if ($owner && (int) $owner['id'] !== $id) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => "Code {$code} already belongs to " . short_name($owner['full_name']) . '.'];
        }
        if (!$owner) {
            if ((int) $r['new_entrant'] === 1) {
                // A new entry restored onto its old code: the old link becomes their main link,
                // and the temporary code from re-entering keeps working as an alias.
                q('INSERT IGNORE INTO ref_aliases (ref_code, entrant_id, created_at) VALUES (?, ?, ?)', [$e['ref_code'], $id, now()]);
                q('UPDATE entrants SET ref_code = ? WHERE id = ?', [$code, $id]);
            } else {
                q('INSERT INTO ref_aliases (ref_code, entrant_id, created_at) VALUES (?, ?, ?)', [$code, $id, now()]);
            }
        }
        $moved = q("INSERT IGNORE INTO referral_clicks (referrer_id, ip_hash, visitor_id, user_agent, counted, origin, clicked_at)
                    SELECT ?, ip_hash, visitor_id, user_agent, 0, 'orphan', clicked_at FROM orphan_clicks WHERE ref_code = ?", [$id, $code])->rowCount();
        q('DELETE FROM orphan_clicks WHERE ref_code = ?', [$code]);
        q("UPDATE restore_requests SET status = 'approved', reviewed_by = ?, reviewed_at = ?, admin_note = ? WHERE id = ?",
            [$adminId, now(), mb_substr($note, 0, 255) ?: null, $requestId]);
        q("UPDATE restore_requests SET status = 'rejected', reviewed_at = ?, admin_note = ? WHERE ref_code = ? AND id <> ? AND status IN ('email','review')",
            [now(), 'Code restored to another claimant', $code, $requestId]);
        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack();
        throw $ex;
    }
    q('INSERT INTO audit_log (admin_id, action, detail, ip_hash, created_at) VALUES (?, ?, ?, ?, ?)', [
        $adminId, 'restore.approve', json_encode(['request' => $requestId, 'entrant' => $id, 'code' => $code, 'opens_moved' => $moved, 'auto' => $adminId === null]), ip_hash(), now(),
    ]);
    return ['ok' => true, 'moved' => $moved];
}

function restore_reject(int $requestId, int $adminId, string $note): void
{
    q("UPDATE restore_requests SET status = 'rejected', reviewed_by = ?, reviewed_at = ?, admin_note = ? WHERE id = ? AND status IN ('email','review')",
        [$adminId, now(), mb_substr($note, 0, 255) ?: null, $requestId]);
    audit('restore.reject', ['request' => $requestId, 'note' => $note]);
}

/**
 * Restore link opens from evidence. Each open becomes a referral_clicks row (origin 'restored')
 * dated $asAt, so the leaderboard, dashboard, exports and draw snapshots all count it.
 */
function restore_add_opens(int $entrantId, int $opens, string $asAt, string $reason, string $evidence, int $adminId): int
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        q('INSERT INTO score_adjustments (entrant_id, opens, as_at, reason, evidence, admin_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$entrantId, $opens, $asAt, mb_substr($reason, 0, 255), $evidence ?: null, $adminId, now()]);
        $adjId = (int) $pdo->lastInsertId();
        $ipHash = app_hash('restored|' . $adjId);
        $st = $pdo->prepare("INSERT INTO referral_clicks (referrer_id, ip_hash, visitor_id, user_agent, counted, origin, adjustment_id, clicked_at)
                             VALUES (?, ?, ?, 'restored from evidence', 0, 'restored', ?, ?)");
        for ($i = 0; $i < $opens; $i++) {
            $st->execute([$entrantId, $ipHash, substr(hash('sha256', "restored|{$adjId}|{$i}"), 0, 32), $adjId, $asAt]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    audit('restore.add_opens', ['entrant' => $entrantId, 'adjustment' => $adjId, 'opens' => $opens, 'as_at' => $asAt, 'reason' => $reason]);
    return $adjId;
}

function restore_remove_adjustment(int $adjustmentId): void
{
    $adj = q('SELECT * FROM score_adjustments WHERE id = ?', [$adjustmentId])->fetch();
    if (!$adj) {
        return;
    }
    q('DELETE FROM referral_clicks WHERE adjustment_id = ?', [$adjustmentId]);
    q('DELETE FROM score_adjustments WHERE id = ?', [$adjustmentId]);
    audit('restore.remove_opens', ['entrant' => (int) $adj['entrant_id'], 'adjustment' => $adjustmentId, 'opens' => (int) $adj['opens'], 'reason' => $adj['reason']]);
}

/** Opens counted for an entrant, split by where they came from. */
function opens_by_origin(int $entrantId): array
{
    $out = ['live' => 0, 'orphan' => 0, 'restored' => 0];
    foreach (q('SELECT origin, COUNT(*) c FROM referral_clicks WHERE referrer_id = ? GROUP BY origin', [$entrantId])->fetchAll() as $r) {
        $out[$r['origin']] = (int) $r['c'];
    }
    return $out;
}
