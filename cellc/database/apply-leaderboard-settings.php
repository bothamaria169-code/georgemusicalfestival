<?php
/** One-off: write the leaderboard-rule texts into the settings table (run with php from the site root). */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/includes/bootstrap.php';
foreach (['terms_html', 'privacy_html', 'draw_reassurance', 'share_bonus_cap', 'referral_reward_text', 'referral_entries_per_signup', 'referral_entry_cap'] as $k) {
    save_setting($k, (string) setting_defaults()[$k]);
    echo "updated {$k}\n";
}
