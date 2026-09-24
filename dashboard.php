<?php
/** Entrant dashboard — entries, personal share link, referral tracking and leaderboard. */
require __DIR__ . '/includes/bootstrap.php';

$me = current_entrant();
if (!$me) {
    flash('info', 'Sign in with your email to open your dashboard.');
    redirect('flyfortwo/login');
}
$id = (int) $me['id'];

// POPIA: entrants can change their marketing preference at any time.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok() && ($_POST['action'] ?? '') === 'marketing') {
    q('UPDATE entrants SET consent_marketing = ?, consent_at = ? WHERE id = ?', [!empty($_POST['consent_marketing']) ? 1 : 0, now(), $id]);
    flash('success', 'Your communication preferences were updated.');
    redirect('flyfortwo/dashboard');
}

$count    = entry_count($id);
$bd       = entry_breakdown($id);
$stats    = referral_stats($id);
$link     = ref_link($me);
$shareCap = (int) setting('share_bonus_cap');
$refPer   = (int) setting('referral_entries_per_signup');
$refCap   = (int) setting('referral_entry_cap');
$leaders  = referral_leaderboard(10);
$rank     = null;
if ($stats['verified'] > 0) {
    $rank = 1 + (int) q("SELECT COUNT(*) FROM (
                SELECT referred_by, COUNT(*) c FROM entrants WHERE status = 'verified' AND referred_by IS NOT NULL GROUP BY referred_by
              ) t WHERE t.c > ?", [$stats['verified']])->fetchColumn();
}
$friends = q('SELECT full_name, town, status, created_at, verified_at FROM entrants WHERE referred_by = ? ORDER BY created_at DESC LIMIT 50', [$id])->fetchAll();
$first   = explode(' ', $me['full_name'])[0];
$prize   = (string) setting('prize_headline');

$scripts = [
    'WhatsApp status' => "✈️ I just entered FLY FOR TWO — you could win {$prize} with George Music Festival × ACSA. Free to enter, takes a minute 👉 {$link}",
    'Group chat'      => "Guys, GMF and Airports Company SA are giving away {$prize} — return flights, accommodation and transfers. Enter free here before " . fmt_date(setting('comp_close_at'), 'j F') . ": {$link}",
    'Short & sweet'   => "Win a weekend away for two ✈️ {$link}",
];

$page_title = 'My dashboard — FLY FOR TWO';
$nav_active = 'fly';
$extra_css  = ['assets/css/comp.css'];
require __DIR__ . '/includes/header.php';
?>

<section class="dash-hero grain">
  <div class="wrap dash-hero-grid">
    <div>
      <span class="dash-status"><i class="fa-solid fa-circle-check"></i> Verified entrant · <?= e($me['ref_code']) ?></span>
      <h1 class="display huge" style="margin-top:18px;">Hey <?= e($first) ?>, <span class="serif-i">you're in.</span></h1>
      <p class="lede muted" style="margin:16px 0 0;max-width:620px;">You have <strong style="color:var(--text-light)"><?= $count ?> entr<?= $count === 1 ? 'y' : 'ies' ?></strong> in the draw for <?= e($prize) ?>. Now spread the word — every friend you bring counts on the leaderboard.</p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
      <a class="btn btn-orange" href="https://wa.me/?text=<?= rawurlencode($scripts['WhatsApp status']) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> Share now</a>
      <a class="btn btn-ghost" href="<?= e(url('flyfortwo/logout')) ?>">Sign out</a>
    </div>
  </div>
</section>

<section class="dash-main">
  <div class="wrap">
    <div class="stat-row" style="margin-bottom:22px;">
      <div class="stat is-accent"><small>Entries in the draw</small><strong><?= $count ?></strong><span><?= $bd['base'] ?> entry<?= $bd['share_bonus'] ? ' + ' . $bd['share_bonus'] . ' share bonus' : '' ?><?= $bd['referral_bonus'] ? ' + ' . $bd['referral_bonus'] . ' referral' : '' ?></span></div>
      <div class="stat"><small>Link opens</small><strong><?= $stats['clicks'] ?></strong><span>people opened your link</span></div>
      <div class="stat"><small>Friends entered</small><strong><?= $stats['verified'] ?></strong><span><?= $stats['pending'] ? $stats['pending'] . ' still verifying' : 'verified entries' ?></span></div>
      <div class="stat"><small>Leaderboard</small><strong><?= $rank ? '#' . $rank : '—' ?></strong><span><?= $rank ? 'your current rank' : 'bring one friend to rank' ?></span></div>
    </div>

    <div class="dash-grid">
      <div class="dash-stack">
        <!-- Share link -->
        <div class="panel">
          <h2>Your personal link</h2>
          <p class="panel-sub">Everyone who opens this link is tracked to you. <?php if ($shareCap > 0): ?>The first friend to open it earns you a bonus entry.<?php endif; ?></p>
          <div class="ref-box">
            <input id="refLink" value="<?= e($link) ?>" readonly aria-label="Your personal link">
            <button class="btn btn-dark btn-sm" type="button" data-copy-target="refLink"><i class="fa-regular fa-copy"></i> Copy</button>
          </div>
          <div class="share-row">
            <a class="share-btn wa" href="https://wa.me/?text=<?= rawurlencode($scripts['WhatsApp status']) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> WhatsApp</a>
            <a class="share-btn" href="https://www.facebook.com/sharer/sharer.php?u=<?= rawurlencode($link) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-facebook-f"></i> Facebook</a>
            <a class="share-btn" href="https://twitter.com/intent/tweet?text=<?= rawurlencode($scripts['Short & sweet']) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-x-twitter"></i> X</a>
            <a class="share-btn" href="https://t.me/share/url?url=<?= rawurlencode($link) ?>&text=<?= rawurlencode('Win a weekend away for two ✈️') ?>" target="_blank" rel="noopener"><i class="fa-brands fa-telegram"></i> Telegram</a>
            <a class="share-btn" href="sms:?&body=<?= rawurlencode($scripts['Short & sweet']) ?>"><i class="fa-solid fa-comment-sms"></i> SMS</a>
            <a class="share-btn" href="mailto:?subject=<?= rawurlencode('Win a weekend away for two') ?>&body=<?= rawurlencode($scripts['Group chat']) ?>"><i class="fa-regular fa-envelope"></i> Email</a>
            <button class="share-btn dark" type="button" data-native-share data-url="<?= e($link) ?>" data-text="<?= e($scripts['Short & sweet']) ?>"><i class="fa-solid fa-share-nodes"></i> More</button>
          </div>
          <div class="qr-wrap">
            <div id="qr" data-url="<?= e($link) ?>"></div>
            <div>
              <strong>Your QR code</strong>
              <p>Show it on your phone at taxi ranks, campus or the office — anyone who scans it lands on the entry page with your link attached.</p>
              <button class="btn btn-outline-dark btn-sm" type="button" id="qrDownload" style="margin-top:10px;"><i class="fa-solid fa-download"></i> Save QR image</button>
            </div>
          </div>
        </div>

        <!-- Ready-made messages -->
        <div class="panel">
          <h2>Ready-made messages</h2>
          <p class="panel-sub">Copy, paste, post. Your link is already inside.</p>
          <?php foreach ($scripts as $label => $text): $sid = 'msg' . md5($label); ?>
            <div style="margin-bottom:14px;">
              <small class="kicker" style="color:var(--orange-deep);font-size:.66rem;"><?= e($label) ?></small>
              <div class="script-box"><span id="<?= $sid ?>"><?= e($text) ?></span>
                <button class="btn btn-outline-dark btn-sm" type="button" data-copy-target="<?= $sid ?>" aria-label="Copy message"><i class="fa-regular fa-copy"></i></button>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <!-- Bonus progress -->
        <div class="panel">
          <h2>Bonus entries</h2>
          <?php if ($shareCap > 0): ?>
            <p class="panel-sub" style="margin-bottom:4px;">Share bonus — earned when a friend opens your link</p>
            <div class="progress-bar"><span style="width:<?= min(100, $bd['share_bonus'] / $shareCap * 100) ?>%"></span></div>
            <small class="muted"><?= $bd['share_bonus'] ?> of <?= $shareCap ?> earned</small>
          <?php endif; ?>
          <?php if ($refPer > 0 && $refCap > 0): ?>
            <p class="panel-sub" style="margin:18px 0 4px;">Referral entries — one per friend who verifies their entry</p>
            <div class="progress-bar"><span style="width:<?= min(100, $bd['referral_bonus'] / $refCap * 100) ?>%"></span></div>
            <small class="muted"><?= $bd['referral_bonus'] ?> of <?= $refCap ?> earned</small>
          <?php endif; ?>
          <p class="muted" style="font-size:.86rem;margin:16px 0 0;"><?= e(setting('referral_reward_text')) ?></p>
        </div>

        <!-- Friends -->
        <div class="panel">
          <h2>Friends you brought in</h2>
          <p class="panel-sub">Only first names and initials are shown, to protect everyone's privacy.</p>
          <?php if ($friends): ?>
            <ul class="ref-list">
              <?php foreach ($friends as $f): ?>
                <li>
                  <span><strong><?= e(short_name($f['full_name'])) ?></strong><?= $f['town'] ? ' · <span class="muted">' . e($f['town']) . '</span>' : '' ?></span>
                  <span><?= $f['status'] === 'verified' ? '<span class="badge badge-ok">Entered</span>' : '<span class="badge badge-wait">Verifying</span>' ?> <small class="muted"><?= e(fmt_date($f['verified_at'] ?: $f['created_at'], 'j M')) ?></small></span>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <p class="muted" style="margin:0;">No one yet — send your link to three friends right now and check back.</p>
          <?php endif; ?>
        </div>
      </div>

      <div class="dash-stack">
        <div class="panel"><?php include __DIR__ . '/includes/boarding-pass.php'; ?>
          <p class="muted" style="font-size:.84rem;margin:16px 0 0;"><?= e(setting('draw_reassurance')) ?></p>
        </div>

        <div class="panel">
          <h2>Leaderboard</h2>
          <p class="panel-sub">Most friends entered</p>
          <?php if ($leaders): ?>
            <ol class="ff-leaders">
              <?php foreach ($leaders as $i => $l): ?>
                <li class="<?= (int) $l['id'] === $id ? 'is-me' : '' ?>"><span class="rank"><?= $i + 1 ?></span><span class="who"><?= (int) $l['id'] === $id ? 'You' : e(short_name($l['full_name'])) ?><?php if ($l['town']): ?><small><?= e($l['town']) ?></small><?php endif; ?></span><span class="score"><?= (int) $l['referrals'] ?></span></li>
              <?php endforeach; ?>
            </ol>
          <?php else: ?>
            <p class="muted" style="margin:0;">Nobody's on the board yet — your first referral puts you at #1.</p>
          <?php endif; ?>
        </div>

        <div class="panel">
          <h2>Your details</h2>
          <ul class="ref-list">
            <li><span class="muted">Name</span><strong><?= e($me['full_name']) ?></strong></li>
            <li><span class="muted">Email</span><strong><?= e(mask_email($me['email'])) ?></strong></li>
            <li><span class="muted">Cellphone</span><strong><?= e(mask_phone($me['phone'])) ?></strong></li>
            <li><span class="muted">Entered</span><strong><?= e(fmt_date($me['verified_at'], 'j M Y, H:i')) ?></strong></li>
          </ul>
          <form method="post" style="margin-top:14px;">
            <?= csrf_field() ?><input type="hidden" name="action" value="marketing">
            <label class="check"><input type="checkbox" name="consent_marketing" value="1" <?= $me['consent_marketing'] ? 'checked' : '' ?> onchange="this.form.submit()"> Keep me updated on future GMF × ACSA events and travel offers.</label>
          </form>
          <p class="muted" style="font-size:.8rem;margin:8px 0 0;">Need to change or delete your details? Email <a href="mailto:<?= e(setting('contact_email')) ?>" style="text-decoration:underline"><?= e(setting('contact_email')) ?></a>.</p>
        </div>

        <div class="panel panel-dark">
          <span class="kicker">The event</span>
          <h2 style="margin-top:10px;"><?= e(setting('event_name')) ?></h2>
          <p class="panel-sub" style="margin:0 0 14px;"><?= e(fmt_date(setting('event_date'), 'l, j F Y')) ?> · <?= e(setting('event_venue')) ?></p>
          <a class="btn btn-orange btn-sm" href="<?= e(url('index.php#event')) ?>">Event details</a>
        </div>
      </div>
    </div>
  </div>
</section>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
(function () {
  function toast(msg) {
    var t = document.querySelector('.toast');
    if (!t) { t = document.createElement('div'); t.className = 'toast'; document.body.appendChild(t); }
    t.textContent = msg; t.classList.add('is-on'); clearTimeout(t._h);
    t._h = setTimeout(function () { t.classList.remove('is-on'); }, 2200);
  }
  function copyText(text) {
    function legacy() {
      var ta = document.createElement('textarea'); ta.value = text; document.body.appendChild(ta); ta.select();
      try { document.execCommand('copy'); } catch (e) {} ta.remove();
    }
    if (navigator.clipboard && window.isSecureContext) return navigator.clipboard.writeText(text).catch(legacy);
    legacy(); return Promise.resolve();
  }
  document.querySelectorAll('[data-copy-target]').forEach(function (b) {
    b.addEventListener('click', function () {
      var el = document.getElementById(b.dataset.copyTarget);
      copyText(el.value !== undefined && el.tagName === 'INPUT' ? el.value : el.textContent).then(function () { toast('Copied'); });
    });
  });
  var ns = document.querySelector('[data-native-share]');
  if (ns) ns.addEventListener('click', function () {
    if (navigator.share) navigator.share({ title: 'FLY FOR TWO', text: ns.dataset.text, url: ns.dataset.url }).catch(function () {});
    else copyText(ns.dataset.text).then(function () { toast('Message copied — paste it anywhere'); });
  });
  var box = document.getElementById('qr');
  if (box && window.QRCode) {
    new QRCode(box, { text: box.dataset.url, width: 240, height: 240, colorDark: '#0b1d2a', colorLight: '#ffffff', correctLevel: QRCode.CorrectLevel.M });
    document.getElementById('qrDownload').addEventListener('click', function () {
      var c = box.querySelector('canvas'); if (!c) return;
      var a = document.createElement('a'); a.download = 'fly-for-two-qr.png'; a.href = c.toDataURL('image/png'); a.click();
    });
  }
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
