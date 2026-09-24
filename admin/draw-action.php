<?php
/** JSON actions for the draws screens (admin only). */
require __DIR__ . '/_layout.php';
require dirname(__DIR__) . '/includes/draw.php';

$admin = current_admin();
if (!$admin || $admin['role'] !== 'admin') {
    json_out(['ok' => false, 'error' => 'Admins only.'], 403);
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !csrf_ok()) {
    json_out(['ok' => false, 'error' => 'Session expired — reload the page.'], 400);
}

$action = $_POST['action'] ?? '';
$drawId = (int) ($_POST['draw_id'] ?? 0);

try {
    switch ($action) {
        case 'sample':
            // Masked names for the on-screen shuffle (display only — the winner is chosen server-side).
            $names = q("SELECT full_name, town FROM entrants WHERE status = 'verified' ORDER BY RAND() LIMIT 60")->fetchAll();
            json_out(['ok' => true, 'names' => array_map(fn ($n) => ['name' => short_name($n['full_name']), 'town' => $n['town'] ?: ''], $names)]);

        case 'run':
            $witness = mb_substr(trim((string) ($_POST['witness'] ?? '')), 0, 160);
            $run = run_draw($drawId, $witness);
            json_out(['ok' => true, 'run' => audit_view($run), 'winner' => public_winner($run)]);

        case 'outcome':
            $runId = (int) ($_POST['run_id'] ?? 0);
            $outcome = $_POST['outcome'] === 'confirmed' ? 'confirmed' : 'disqualified';
            $run = set_run_outcome($runId, $outcome, mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 255));
            if ($outcome === 'disqualified') {
                // Immediate, logged redraw from the remaining valid entries.
                $new = run_draw((int) $run['draw_id'], (string) ($run['witness'] ?? ''), (string) $run['outcome_note']);
                json_out(['ok' => true, 'redraw' => true, 'run' => audit_view($new), 'winner' => public_winner($new)]);
            }
            json_out(['ok' => true, 'run' => audit_view($run)]);

        case 'publish':
            $publish = !empty($_POST['publish']);
            $draw = q('SELECT * FROM draws WHERE id = ?', [$drawId])->fetch();
            if (!$draw || !in_array($draw['status'], ['confirmed', 'published'], true)) {
                throw new RuntimeException('Confirm the winner before publishing.');
            }
            q('UPDATE draws SET status = ? WHERE id = ?', [$publish ? 'published' : 'confirmed', $drawId]);
            audit($publish ? 'draw.publish' : 'draw.unpublish', ['draw_id' => $drawId]);
            json_out(['ok' => true]);
    }
    json_out(['ok' => false, 'error' => 'Unknown action'], 400);
} catch (Throwable $e) {
    json_out(['ok' => false, 'error' => $e->getMessage()], 422);
}

function audit_view(array $run): array
{
    return [
        'id'            => (int) $run['id'],
        'run_no'        => (int) $run['run_no'],
        'is_redraw'     => (bool) $run['is_redraw'],
        'cutoff_at'     => $run['cutoff_at'],
        'created_at'    => $run['created_at'],
        'pool_entrants' => (int) $run['pool_entrants'],
        'pool_entries'  => (int) $run['pool_entries'],
        'pool_hash'     => $run['pool_hash'],
        'seed'          => $run['random_proof'],
        'index'         => (int) $run['selected_index'],
        'outcome'       => $run['outcome'],
    ];
}
