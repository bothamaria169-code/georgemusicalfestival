<?php
/** Short share link: /r/CODE → records the click-through, then lands on the entry page. */
require __DIR__ . '/includes/bootstrap.php';

$referrer = entrant_by_ref((string) ($_GET['c'] ?? ''));
if ($referrer) {
    record_referral_click($referrer);
    $_SESSION['ref_by'] = $referrer['ref_code'];
}
redirect('flyfortwo/');
