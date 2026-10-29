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

// Winner photo (shown on the live reveal screen and winner announcement if this entrant wins).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok() && ($_POST['action'] ?? '') === 'photo') {
    if (!empty($_POST['remove'])) {
        if ($me['photo'] && str_starts_with($me['photo'], 'uploads/entrants/')) {
            @unlink(APP_ROOT . '/' . $me['photo']);
        }
        q('UPDATE entrants SET photo = NULL WHERE id = ?', [$id]);
        flash('success', 'Your photo was removed.');
    } elseif (empty($_POST['photo_consent'])) {
        flash('error', 'Please tick the box to agree to your photo being shown if you win.');
    } elseif (!rate_limit('photo_upload', (string) $id, 10, 3600)) {
        flash('error', 'Too many uploads — please try again later.');
    } else {
        $saved = save_entrant_photo($_FILES['photo'] ?? [], $id);
        flash($saved ? 'success' : 'error', $saved ? 'Your photo is saved.' : 'That file could not be used. Please upload a JPG, PNG or WebP photo.');
    }
    redirect('flyfortwo/dashboard');
}

$count    = entry_count($id);
$bd       = entry_breakdown($id);
$stats    = referral_stats($id);
$link     = ref_link($me);
$shareCap = (int) setting('share_bonus_cap');
$refPer   = (int) setting('referral_entries_per_signup');
$refCap   = (int) setting('referral_entry_cap');
$leaders  = public_leaderboard(10);
$rank     = leaderboard_rank($id);
// The leader never sees "#1" — only admins know who is winning until the reveal.
$rankLabel = $rank === null ? '—' : ($rank === 1 ? 'Top 10' : ($rank <= 10 ? '#' . $rank : '#' . $rank));
$friends = q('SELECT full_name, town, status, created_at, verified_at FROM entrants WHERE referred_by = ? ORDER BY created_at DESC LIMIT 50', [$id])->fetchAll();
$restores = open_restore_requests($id);
$first   = explode(' ', $me['full_name'])[0];
$prize   = (string) setting('prize_headline');

$scripts = [
    'WhatsApp status' => "✈️ I just entered FLY FOR TWO — you could win {$prize} with George Musical Festival × ACSA. Free to enter, takes a minute 👉 {$link}",
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
      <p class="lede" style="margin:16px 0 0;max-width:620px;color:var(--text-light-muted);">Your score: <strong style="color:var(--text-light)"><?= $stats['clicks'] ?> link open<?= $stats['clicks'] === 1 ? '' : 's' ?></strong>. The person whose link is opened by the most people wins <?= e($prize) ?>, and the runner-up wins the second prize. Keep sharing!</p>
      <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:24px;">
        <a class="btn btn-orange" href="https://wa.me/?text=<?= rawurlencode($scripts['WhatsApp status']) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> Share now</a>
        <a class="btn btn-ghost" href="<?= e(url('flyfortwo/logout')) ?>">Sign out</a>
      </div>
    </div>
    <figure class="dash-plane">
      <img src="<?= e(url('assets/img/acsa/plane-at-the-gate.jpg')) ?>" width="1800" height="1201" alt="A South African Airways plane at the gate, ready to board">
      <figcaption>
        <span class="dash-logos"><img src="<?= e(url('assets/img/brand/gmf-logo-orange.png')) ?>" alt="George Musical Festival"><span>×</span><img src="<?= e(url('assets/img/brand/acsa-white.png')) ?>" alt="Airports Company South Africa"></span>
        <strong>Your seat is waiting.</strong>
      </figcaption>
    </figure>
  </div>
</section>

<section class="dash-main">
  <div class="wrap">
    <?php foreach ($restores as $rr): ?>
      <div class="ff-alert is-ok" style="margin:0 0 18px;font-size:.95rem">
        <i class="fa-solid fa-rotate-left"></i> <strong>We're restoring your old link /r/<?= e($rr['ref_code']) ?>.</strong>
        Keep sharing that link — every open is being saved<?= ($n = orphan_open_count($rr['ref_code'])) ? ' (' . $n . ' so far)' : '' ?> and will be added to your score once GMF has checked your claim.
        Have screenshots of your old score? Email them to <?= e(setting('contact_email')) ?>.
      </div>
    <?php endforeach; ?>
    <div class="stat-row" style="margin-bottom:22px;">
      <div class="stat is-accent"><small>Your score</small><strong><?= $stats['clicks'] ?></strong><span>people opened your link</span></div>
      <div class="stat"><small>Leaderboard</small><strong><?= e($rankLabel) ?></strong><span><?= $rank === null ? 'share your link to get on the board' : ($rank === 1 ? 'the leader stays secret until draw day' : 'your current position') ?></span></div>
      <div class="stat"><small>Friends entered</small><strong><?= $stats['verified'] ?></strong><span><?= $stats['pending'] ? $stats['pending'] . ' still verifying' : 'through your link' ?></span></div>
      <div class="stat"><small>Winner photo</small><strong><?= $me['photo'] ? '<i class="fa-solid fa-circle-check" style="color:var(--success)"></i>' : '—' ?></strong><span><?= $me['photo'] ? 'ready for the reveal' : 'add one below' ?></span></div>
    </div>

    <div class="dash-grid">
      <div class="dash-stack">
        <!-- Share link -->
        <div class="panel">
          <h2>Your personal link</h2>
          <p class="panel-sub">Every different person who opens this link adds 1 to your score. Opening it yourself doesn't count. The top referrer wins <?= e($prize) ?>.</p>
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

        <!-- Winner photo -->
        <div class="panel" id="photo">
          <h2>Your winner photo</h2>
          <p class="panel-sub">If you win, this photo appears on the big screen when the winner is revealed and in the winner announcement. It's optional, and you can remove it at any time.</p>
          <div style="display:flex;gap:18px;align-items:center;flex-wrap:wrap;">
            <div class="photo-preview"><?php if ($me['photo']): ?><img src="<?= e(media_url($me['photo'])) ?>" alt="Your photo"><?php else: ?><i class="fa-regular fa-user"></i><?php endif; ?></div>
            <form method="post" enctype="multipart/form-data" style="flex:1;min-width:220px;" id="photoForm">
              <?= csrf_field() ?><input type="hidden" name="action" value="photo">
              <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required id="photoInput" class="input" style="padding:10px">
              <label class="check"><input type="checkbox" name="photo_consent" value="1" required> I agree that this photo, my first name, surname initial and town may be shown publicly if I win.</label>
              <button class="btn btn-dark btn-sm" type="submit"><i class="fa-solid fa-upload"></i> <?= $me['photo'] ? 'Replace photo' : 'Upload photo' ?></button>
            </form>
          </div>
          <?php if ($me['photo']): ?>
          <form method="post" style="margin-top:10px;"><?= csrf_field() ?><input type="hidden" name="action" value="photo"><input type="hidden" name="remove" value="1"><button class="btn btn-outline-dark btn-sm" type="submit">Remove my photo</button></form>
          <?php endif; ?>
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
          <p class="panel-sub">Top 10 by link opens. The leader is kept secret until draw day.</p>
          <?php if ($leaders): ?>
            <ol class="ff-leaders">
              <?php foreach ($leaders as $l): $isMe = !$l['hidden'] && $l['id'] === $id; ?>
                <li class="<?= $isMe ? 'is-me' : '' ?><?= $l['hidden'] ? ' is-hidden' : '' ?>"><span class="rank"><?= $l['hidden'] ? '<i class="fa-solid fa-lock"></i>' : $l['rank'] ?></span><span class="who"><?= $isMe ? 'You' : e($l['name']) ?><?php if ($l['town']): ?><small><?= e($l['town']) ?></small><?php endif; ?></span><span class="score"><?= $l['hidden'] ? '?' : (int) $l['clicks'] ?></span></li>
              <?php endforeach; ?>
            </ol>
          <?php else: ?>
            <p class="muted" style="margin:0;">Nobody's on the board yet — share your link and be the first.</p>
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
  // Shrink big phone photos in the browser before upload (max 1400px, JPEG).
  var pin = document.getElementById('photoInput');
  if (pin && window.DataTransfer) pin.addEventListener('change', function () {
    var f = pin.files[0]; if (!f || !/^image\//.test(f.type) || f.size < 1500000) return;
    var img = new Image(), url = URL.createObjectURL(f);
    img.onload = function () {
      var k = Math.min(1, 1400 / Math.max(img.width, img.height)), c = document.createElement('canvas');
      c.width = Math.round(img.width * k); c.height = Math.round(img.height * k);
      c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
      c.toBlob(function (b) { var dt = new DataTransfer(); dt.items.add(new File([b], 'photo.jpg', { type: 'image/jpeg' })); pin.files = dt.files; URL.revokeObjectURL(url); }, 'image/jpeg', 0.86);
    };
    img.src = url;
  });
  var box = document.getElementById('qr');
  if (box && window.QRCode) {
    new QRCode(box, { text: box.dataset.url, width: 240, height: 240, colorDark: '#00485a', colorLight: '#ffffff', correctLevel: QRCode.CorrectLevel.M });
    document.getElementById('qrDownload').addEventListener('click', function () {
      var c = box.querySelector('canvas'); if (!c) return;
      var a = document.createElement('a'); a.download = 'fly-for-two-qr.png'; a.href = c.toDataURL('image/png'); a.click();
    });
  }
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
