<?php
/**
 * Automated, auditable prize draws.
 *
 * Selection is verifiable after the fact: a fresh 256-bit random seed is generated for
 * every run and stored in the log together with the SHA-256 hash of the ticket pool.
 * The winning index is derived as uniform( SHA-256(seed ‖ pool_hash ‖ counter) ) with
 * rejection sampling, so anyone holding the exported pool file and the seed can
 * recompute the exact same winner. The pool snapshot is written to storage/draws/.
 */
declare(strict_types=1);

function draw_pool_dir(): string
{
    $dir = APP_ROOT . '/storage/draws';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    return $dir;
}

/** Uniform index in [0, $n) from seed + pool hash (rejection sampling on 48-bit values). */
function draw_pick_index(string $seed, string $poolHash, int $n): int
{
    $space = 2 ** 48;
    $limit = intdiv($space, $n) * $n;
    for ($counter = 0; ; $counter++) {
        $h = hash('sha256', $seed . $poolHash . $counter, true);
        $v = hexdec(bin2hex(substr($h, 0, 6)));
        if ($v < $limit) {
            return (int) ($v % $n);
        }
    }
}

function draw_runs(int $drawId): array
{
    return q('SELECT r.*, p.full_name, p.phone, p.email, p.town, p.channel, a.name AS admin_name
              FROM draw_runs r
              JOIN entrants p ON p.id = r.selected_entrant_id
              LEFT JOIN admins a ON a.id = r.triggered_by
              WHERE r.draw_id = ? ORDER BY r.run_no', [$drawId])->fetchAll();
}

/**
 * Run (or re-run) a draw. Returns the new run row, or throws with a readable message.
 * A redraw is only allowed after the previous winner was marked disqualified; all
 * previously selected entrants for this draw are excluded from the pool.
 */
function run_draw(int $drawId, string $witness = '', string $reason = ''): array
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $draw = q('SELECT * FROM draws WHERE id = ? FOR UPDATE', [$drawId])->fetch();
        if (!$draw) {
            throw new RuntimeException('Draw not found.');
        }
        $runs = q('SELECT * FROM draw_runs WHERE draw_id = ? ORDER BY run_no', [$drawId])->fetchAll();
        $last = end($runs) ?: null;
        if ($last && $last['outcome'] !== 'disqualified') {
            throw new RuntimeException('This draw already has a winner. Mark the winner as disqualified to trigger a redraw.');
        }
        $isRedraw = (bool) $last;
        $exclude  = array_map('intval', array_column($runs, 'selected_entrant_id'));

        // Cut-off: the trigger moment, never later than the competition close.
        $cutoff = date('Y-m-d H:i:s', min(time(), strtotime((string) setting('comp_close_at'))));
        if ($isRedraw) {
            $cutoff = $runs[0]['cutoff_at'];    // redraws use the original pool cut-off
        }

        $pool = draw_pool($cutoff, $exclude);
        $n = count($pool);
        if ($n === 0) {
            throw new RuntimeException('There are no eligible entries in the pool.');
        }
        $poolHash = hash('sha256', implode(',', array_column($pool, 'entry_id')));
        $seed     = random_bytes(32);
        $index    = draw_pick_index($seed, $poolHash, $n);
        $winner   = $pool[$index];
        $runNo    = $last ? (int) $last['run_no'] + 1 : 1;

        q('INSERT INTO draw_runs (draw_id, run_no, is_redraw, redraw_reason, cutoff_at, pool_entrants, pool_entries, pool_hash, random_proof,
                                  selected_index, selected_entry_id, selected_entrant_id, outcome, witness, triggered_by, created_at)
           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'selected\', ?, ?, ?)', [
            $drawId, $runNo, $isRedraw ? 1 : 0, $isRedraw ? ($reason ?: $last['outcome_note']) : null, $cutoff,
            count(array_unique(array_column($pool, 'entrant_id'))), $n, $poolHash, bin2hex($seed),
            $index, $winner['entry_id'], $winner['entrant_id'], $witness ?: null, current_admin()['id'] ?? null, now(),
        ]);
        $runId = (int) $pdo->lastInsertId();
        q("UPDATE draws SET status = 'drawn' WHERE id = ?", [$drawId]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    // Pool snapshot for independent verification (line order = index order).
    $fh = fopen(draw_pool_dir() . "/run_{$runId}_pool.csv", 'w');
    fputcsv($fh, ['index', 'entry_id', 'entrant_id']);
    foreach ($pool as $i => $row) {
        fputcsv($fh, [$i, $row['entry_id'], $row['entrant_id']]);
    }
    fclose($fh);

    audit('draw.run', ['draw_id' => $drawId, 'run_id' => $runId, 'redraw' => $isRedraw, 'pool_entries' => $n]);
    return q('SELECT * FROM draw_runs WHERE id = ?', [$runId])->fetch();
}

function set_run_outcome(int $runId, string $outcome, string $note = ''): array
{
    $run = q('SELECT * FROM draw_runs WHERE id = ?', [$runId])->fetch();
    if (!$run) {
        throw new RuntimeException('Draw run not found.');
    }
    $latest = (int) q('SELECT MAX(id) FROM draw_runs WHERE draw_id = ?', [$run['draw_id']])->fetchColumn();
    if ($latest !== $runId) {
        throw new RuntimeException('Only the most recent run can be changed.');
    }
    if ($outcome === 'disqualified' && trim($note) === '') {
        throw new RuntimeException('Please record why the winner is ineligible.');
    }
    q('UPDATE draw_runs SET outcome = ?, outcome_note = ? WHERE id = ?', [$outcome, $note ?: null, $runId]);
    q('UPDATE draws SET status = ? WHERE id = ?', [$outcome === 'confirmed' ? 'confirmed' : 'drawn', $run['draw_id']]);
    audit('draw.' . $outcome, ['run_id' => $runId, 'note' => $note]);
    return q('SELECT * FROM draw_runs WHERE id = ?', [$runId])->fetch();
}

/** Masked winner card for the live screen — never shows full contact details. */
function public_winner(array $run): array
{
    $p = q('SELECT full_name, phone, town FROM entrants WHERE id = ?', [$run['selected_entrant_id']])->fetch();
    return [
        'name'  => short_name($p['full_name']),
        'phone' => mask_phone($p['phone']),
        'town'  => $p['town'] ?: '',
        'entry' => '#' . str_pad((string) $run['selected_entry_id'], 6, '0', STR_PAD_LEFT),
    ];
}
