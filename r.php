<?php
/** Short share link: /r/CODE → records the click-through, then lands on the entry page. */
require __DIR__ . '/includes/bootstrap.php';

$code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) ($_GET['c'] ?? '')) ?? '');
$referrer = entrant_by_ref($code);
if ($referrer) {
    record_referral_click($referrer);
    $_SESSION['ref_by'] = $referrer['ref_code'];
} elseif (!code_owner($code)) {
    // A link shared before the server loss whose owner hasn't restored their entry yet:
    // keep the open so it can be added to their score once they do.
    record_orphan_click($code);
}
redirect('flyfortwo/');
