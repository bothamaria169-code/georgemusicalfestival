<?php
require __DIR__ . '/_layout.php';

require_admin('admin');

$groups = [
    'Homepage hero' => [
        'hero_kicker'   => ['Small line above the headline', 'text'],
        'hero_headline' => ['Headline (the last word shows in orange italics)', 'text'],
        'hero_text'     => ['Hero message', 'textarea'],
    ],
    'Competition' => [
        'comp_name'        => ['Competition name', 'text'],
        'comp_open_at'     => ['Entries open', 'datetime'],
        'comp_close_at'    => ['Entries close (configurable — the Final Draw pool cuts off here)', 'datetime'],
        'prize_headline'   => ['Prize headline', 'text'],
        'travel_question'  => ['Travel-profile question', 'text'],
        'travel_options'   => ['Answer options (one per line)', 'lines'],
        'towns'            => ['Town list (one per line)', 'lines'],
        'draw_reassurance' => ['Draw reassurance copy', 'textarea'],
    ],
    'Bonus entries & referrals' => [
        'share_bonus_cap'             => ['Share bonus entries per entrant (brief: 1)', 'number'],
        'referral_entries_per_signup' => ['Extra entry per verified referral (0 = off)', 'number'],
        'referral_entry_cap'          => ['Maximum referral entries per entrant', 'number'],
        'referral_reward_text'        => ['Referral reward copy (dashboard & leaderboard)', 'textarea'],
    ],
    'Verification codes' => [
        'otp_cooldown'     => ['Resend cooldown (seconds)', 'number'],
        'otp_ttl_minutes'  => ['Code valid for (minutes)', 'number'],
        'otp_max_attempts' => ['Wrong attempts before a code locks', 'number'],
    ],
    'Event & website' => [
        'event_name'      => ['Event name', 'text'],
        'event_tagline'   => ['Tagline', 'textarea'],
        'event_date'      => ['Event date', 'date'],
        'event_time'      => ['Time line (e.g. Gates open 12:00)', 'text'],
        'event_venue'     => ['Venue', 'text'],
        'ticket_url'      => ['Ticket link (leave empty until on sale)', 'text'],
        'ticket_note'     => ['Ticket note while there is no link', 'text'],
        'hero_video_url'  => ['Homepage background video (YouTube link or .mp4 URL)', 'text'],
        'announcement'    => ['Announcement bar text', 'text'],
        'upcoming_events' => ['Upcoming events — one per line: date|title|venue', 'lines'],
        'contact_email'   => ['Contact email', 'text'],
        'contact_phone'   => ['Contact phone', 'text'],
        'social_facebook' => ['Facebook URL', 'text'],
        'social_instagram'=> ['Instagram URL', 'text'],
        'social_tiktok'   => ['TikTok URL', 'text'],
    ],
    'Legal (editable after CPA s36 review)' => [
        'terms_html'   => ['Competition terms (HTML)', 'html'],
        'privacy_html' => ['Privacy notice (HTML)', 'html'],
    ],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $changed = [];
    foreach ($groups as $fields) {
        foreach ($fields as $key => [$label, $type]) {
            if (!array_key_exists($key, $_POST)) {
                continue;
            }
            $v = trim((string) $_POST[$key]);
            if ($type === 'datetime' && $v !== '') {
                $v = date('Y-m-d H:i:s', strtotime($v));
            } elseif ($type === 'number') {
                $v = (string) max(0, (int) $v);
            } elseif ($type === 'lines') {
                $v = implode("\n", lines($v));
            }
            if ($v !== (string) setting($key)) {
                $changed[] = $key;
            }
            save_setting($key, $v);
        }
    }
    audit('settings.update', ['keys' => $changed]);
    flash('success', $changed ? 'Settings saved (' . count($changed) . ' changed).' : 'No changes.');
    redirect('admin/settings.php');
}

admin_head('Settings', 'settings');
?>
<form method="post">
  <?= csrf_field() ?>
  <div class="grid g-2">
  <?php foreach ($groups as $title => $fields): ?>
    <div class="card" <?= str_starts_with($title, 'Legal') || str_starts_with($title, 'Event') ? 'style="grid-column:1/-1"' : '' ?>>
      <h2><?= e($title) ?></h2>
      <div class="grid <?= str_starts_with($title, 'Event') ? 'g-3' : '' ?>" style="gap:0 16px">
      <?php foreach ($fields as $key => [$label, $type]): $val = (string) setting($key); ?>
        <div class="fg" <?= in_array($type, ['html', 'lines', 'textarea'], true) && str_starts_with($title, 'Event') ? 'style="grid-column:1/-1"' : '' ?>>
          <label class="f" for="s-<?= e($key) ?>"><?= e($label) ?></label>
          <?php if ($type === 'html' || $type === 'lines' || $type === 'textarea'): ?>
            <textarea class="in" id="s-<?= e($key) ?>" name="<?= e($key) ?>" rows="<?= $type === 'html' ? 12 : 4 ?>" <?= $type === 'textarea' ? 'style="font-family:inherit"' : '' ?>><?= e($val) ?></textarea>
          <?php elseif ($type === 'datetime'): ?>
            <input class="in" type="datetime-local" id="s-<?= e($key) ?>" name="<?= e($key) ?>" value="<?= e($val ? date('Y-m-d\TH:i', strtotime($val)) : '') ?>">
          <?php else: ?>
            <input class="in" type="<?= $type === 'number' ? 'number' : ($type === 'date' ? 'date' : 'text') ?>" id="s-<?= e($key) ?>" name="<?= e($key) ?>" value="<?= e($val) ?>" <?= $type === 'number' ? 'min="0"' : '' ?>>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
  </div>
  <div style="position:sticky;bottom:0;padding:14px 0;background:linear-gradient(transparent,var(--bg) 40%);margin-top:10px">
    <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Save settings</button>
  </div>
</form>

<div class="grid g-2" style="margin-top:16px">
  <div class="card" id="qr-builder">
    <h2>QR link builder — tag every activation</h2>
    <p class="muted" style="margin-top:0">Each QR code carries its channel, activation point, town and time slot, so every entry is attributable in reports.</p>
    <div class="grid g-2" style="gap:0 12px">
      <div class="fg"><label class="f">Channel</label><select class="in" id="qb-src">
        <?php foreach (CHANNELS as $k => $v): if (in_array($k, ['ussd', 'referral', 'acsa_embed'], true)) continue; ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?>
      </select></div>
      <div class="fg"><label class="f">Town</label><select class="in" id="qb-town"><option value="">—</option><?php foreach (lines((string) setting('towns')) as $t): ?><option><?= e($t) ?></option><?php endforeach; ?></select></div>
      <div class="fg"><label class="f">Activation point</label><input class="in" id="qb-pt" placeholder="e.g. Garden Route Mall"></div>
      <div class="fg"><label class="f">Time slot (optional)</label><input class="in" id="qb-slot" placeholder="e.g. sat-am or 2026-09-12"></div>
    </div>
    <div class="code" id="qb-url"></div>
    <div style="display:flex;gap:16px;align-items:center;margin-top:14px;flex-wrap:wrap">
      <div id="qb-qr" style="background:#fff;padding:8px;border-radius:8px"></div>
      <div style="display:grid;gap:8px">
        <button class="btn" type="button" id="qb-copy"><i class="fa-regular fa-copy"></i> Copy link</button>
        <button class="btn btn-primary" type="button" id="qb-dl"><i class="fa-solid fa-download"></i> Download QR (PNG)</button>
      </div>
    </div>
  </div>
  <div class="card">
    <h2>Partner integration (ACSA)</h2>
    <p class="muted" style="margin-top:0">Embed the entry form on ACSA's website. Their domain must be listed in <code>cors_origins</code> in config.php.</p>
    <div class="code">&lt;iframe src="<?= e(url('flyfortwo/embed')) ?>" style="width:100%;max-width:480px;height:1000px;border:0" title="FLY FOR TWO"&gt;&lt;/iframe&gt;</div>
    <p class="muted">Read-only reporting API (live access, not a one-off export). Send the partner key as the <code>X-Api-Key</code> header:</p>
    <div class="code">GET <?= e(url('api/report.php')) ?><br>GET <?= e(url('api/report.php?view=entrants&page=1&per_page=500')) ?></div>
    <p class="muted">Partner key configured: <strong><?= config('partner_api_key') ? 'yes' : 'no — set partner_api_key in config.php' ?></strong>. Give ACSA staff a <em>Read-only</em> login under Team &amp; access for the dashboard.</p>
    <p class="muted">USSD gateway callback: <strong><?= config('ussd_secret') ? 'enabled' : 'disabled — set ussd_secret in config.php' ?></strong></p>
    <div class="code"><?= e(url('api/ussd.php?secret=…')) ?></div>
    <p class="muted">Email delivery: <strong><?= e(config('mail.driver')) ?></strong><?= config('mail.driver') === 'log' ? ' — codes are written to storage/logs/mail.log. Switch to smtp before launch.' : '' ?></p>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
(function () {
  var base = <?= json_encode(url('flyfortwo/')) ?>, qr;
  var ids = ['qb-src', 'qb-town', 'qb-pt', 'qb-slot'];
  function build() {
    var p = new URLSearchParams();
    p.set('src', document.getElementById('qb-src').value);
    var pt = document.getElementById('qb-pt').value.trim(), town = document.getElementById('qb-town').value, slot = document.getElementById('qb-slot').value.trim();
    if (pt) p.set('pt', pt); if (town) p.set('town', town); if (slot) p.set('slot', slot);
    var u = base + '?' + p.toString();
    document.getElementById('qb-url').textContent = u;
    var box = document.getElementById('qb-qr'); box.innerHTML = '';
    if (window.QRCode) qr = new QRCode(box, { text: u, width: 150, height: 150, correctLevel: QRCode.CorrectLevel.M });
    return u;
  }
  ids.forEach(function (id) { document.getElementById(id).addEventListener('input', build); });
  document.getElementById('qb-copy').addEventListener('click', function () { navigator.clipboard && navigator.clipboard.writeText(build()); this.textContent = 'Copied'; });
  document.getElementById('qb-dl').addEventListener('click', function () {
    var u = build(), tmp = document.createElement('div');
    new QRCode(tmp, { text: u, width: 1024, height: 1024, correctLevel: QRCode.CorrectLevel.H });
    setTimeout(function () {
      var c = tmp.querySelector('canvas'); if (!c) return;
      var a = document.createElement('a');
      a.download = 'qr-' + (document.getElementById('qb-pt').value || document.getElementById('qb-src').value).toLowerCase().replace(/[^a-z0-9]+/g, '-') + '.png';
      a.href = c.toDataURL('image/png'); a.click();
    }, 60);
  });
  build();
})();
</script>
<?php admin_foot(); ?>
