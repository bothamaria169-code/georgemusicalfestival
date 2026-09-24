<?php
/** Reporting queries shared by the admin dashboard and the ACSA reporting API. */
declare(strict_types=1);

function report_summary(): array
{
    $t = q("SELECT
              SUM(status = 'verified')                         AS verified,
              SUM(status = 'pending')                          AS pending,
              SUM(status = 'disqualified')                     AS disqualified,
              SUM(status = 'verified' AND consent_marketing=1) AS marketing_optin,
              SUM(status = 'verified' AND referred_by IS NOT NULL) AS referred,
              SUM(status = 'verified' AND DATE(verified_at) = CURDATE()) AS today
            FROM entrants")->fetch();
    $entries = q("SELECT e.kind, COUNT(*) c FROM entries e JOIN entrants p ON p.id = e.entrant_id WHERE p.status = 'verified' GROUP BY e.kind")->fetchAll(PDO::FETCH_KEY_PAIR);

    return [
        'totals' => [
            'verified_entrants' => (int) $t['verified'],
            'pending_unverified' => (int) $t['pending'],
            'disqualified'      => (int) $t['disqualified'],
            'marketing_opt_in'  => (int) $t['marketing_optin'],
            'referred_entrants' => (int) $t['referred'],
            'verified_today'    => (int) $t['today'],
            'draw_entries'      => array_sum(array_map('intval', $entries)),
            'entries_by_kind'   => array_map('intval', $entries),
            'link_clicks'       => (int) q('SELECT COUNT(*) FROM referral_clicks')->fetchColumn(),
            'subscribers'       => (int) q('SELECT COUNT(*) FROM subscribers')->fetchColumn(),
        ],
        'by_channel' => q("SELECT channel, SUM(status='verified') AS verified, SUM(status='pending') AS pending
                           FROM entrants GROUP BY channel ORDER BY verified DESC")->fetchAll(),
        'by_day' => q("SELECT DATE(verified_at) AS day, COUNT(*) AS verified
                       FROM entrants WHERE status = 'verified' AND verified_at >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
                       GROUP BY DATE(verified_at) ORDER BY day")->fetchAll(),
        'by_town' => q("SELECT COALESCE(NULLIF(source_town,''), NULLIF(town,''), 'Unknown') AS town, COUNT(*) AS verified
                        FROM entrants WHERE status = 'verified' GROUP BY 1 ORDER BY verified DESC LIMIT 30")->fetchAll(),
        'by_activation_point' => q("SELECT activation_point, channel, COALESCE(source_town,'') AS town, COUNT(*) AS verified
                                    FROM entrants WHERE status = 'verified' AND activation_point IS NOT NULL AND activation_point <> ''
                                    GROUP BY activation_point, channel, source_town ORDER BY verified DESC LIMIT 50")->fetchAll(),
        'event_day_by_hour' => q("SELECT HOUR(verified_at) AS hour, channel, COUNT(*) AS verified
                                  FROM entrants WHERE status = 'verified' AND DATE(verified_at) = ?
                                  GROUP BY HOUR(verified_at), channel ORDER BY hour", [setting('event_date')])->fetchAll(),
        'travel_profile' => q("SELECT fly_frequency AS answer, COUNT(*) AS verified FROM entrants WHERE status = 'verified' GROUP BY fly_frequency ORDER BY verified DESC")->fetchAll(),
    ];
}
