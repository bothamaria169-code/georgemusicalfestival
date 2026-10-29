<?php
/**
 * "Restore my entry" — for entrants whose entry was lost with the old server in October 2026.
 * They give their details and the link from their "You're entered" email, confirm their email
 * with a code, and get their old share link back (automatically when this phone registered it,
 * otherwise after a GMF admin checks the claim).
 */
require __DIR__ . '/includes/bootstrap.php';

$me     = current_entrant();
$error  = '';
$errors = [];
$info   = '';
$dev    = null;
$towns  = lines((string) setting('towns'));
$flyOpts = lines((string) setting('travel_options'));
$deviceCodes = unclaimed_device_codes();
$pendingId = (int) ($_SESSION['restore_entrant_id'] ?? 0);
$pending = $pendingId ? q('SELECT * FROM entrants WHERE id = ?', [$pendingId])->fetch() : null;

if (($_GET['restart'] ?? '') === '1') {
    unset($_SESSION['restore_entrant_id']);
    $pending = null;
}

$prefill = normalize_ref_code((string) ($_POST['old_link'] ?? $_GET['code'] ?? ($deviceCodes[0] ?? '')));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $step = $_POST['step'] ?? '';
    if (!csrf_ok()) {
        $error = 'Your session expired. Please try again.';
    } elseif ($step === 'details') {
        $code = normalize_ref_code((string) ($_POST['old_link'] ?? ''));
        [$data, $errors] = validate_entry($_POST);
        $claimed = trim((string) ($_POST['claimed_opens'] ?? ''));
        $evidence = mb_substr(trim((string) ($_POST['evidence'] ?? '')), 0, 2000);
        if (!is_ref_code_shape($code)) {
            $errors['old_link'] = 'Paste the link from your "You\'re entered — FLY FOR TWO" email. It looks like georgemusicalfestival.co.za/r/ABC1234.';
        }
        if ($claimed !== '' && !ctype_digit($claimed)) {
            $errors['claimed_opens'] = 'Use a number, e.g. 120 — or leave it empty.';
        }
        if (!empty($_POST['website']) || !form_timer_ok($_POST['timer'] ?? null)) {
            $error = 'Please take a moment to check your details, then try again.';
        } elseif (!rate_limit('restore_start', client_ip(), 30, 3600)) {
            $error = 'Too many attempts from this connection. Please try again later.';
        } elseif ($errors) {
            $error = 'Please fix the highlighted fields.';
        } else {
            $owner = code_owner($code);
            $byEmail = q('SELECT * FROM entrants WHERE email = ?', [$data['email']])->fetch() ?: null;
            $byPhone = q('SELECT * FROM entrants WHERE phone = ?', [$data['phone']])->fetch() ?: null;
            $entrant = null;
            $isNew = false;

            if ($owner && $owner['status'] === 'verified' && $owner['email'] === $data['email']) {
                $error = 'That link is already restored to your entry. Sign in to see your dashboard.';
            } elseif ($owner && $owner['status'] === 'verified') {
                $error = 'That link has already been restored to another entry. If it is yours, email ' . setting('contact_email') . ' with your "You\'re entered" email and we will look into it.';
            } elseif (($byEmail && $byEmail['status'] === 'disqualified') || ($byPhone && $byPhone['status'] === 'disqualified')) {
                $error = 'This entry cannot be restored. Please contact us if you think this is a mistake.';
            } elseif ($byEmail && $byEmail['status'] === 'verified') {
                $entrant = $byEmail;                       // re-entered since the loss: add the old code to that entry
            } elseif ($byPhone && $byPhone['status'] === 'verified') {
                $error = 'This cellphone number is already entered with the email ' . mask_email($byPhone['email'])
                    . '. Use that email here, or contact ' . setting('contact_email') . ' if it is wrong.';
            } else {
                $result = upsert_pending_entrant($data, source_data(), null, null);
                if (isset($result['error'])) {
                    $error = 'We could not start your restore. Please contact ' . setting('contact_email') . '.';
                } else {
                    $entrant = $result['entrant'];
                    $isNew = true;
                    q("UPDATE entrants SET notes = 'Restored entry (server loss Oct 2026)' WHERE id = ? AND notes IS NULL", [$entrant['id']]);
                }
            }

            if ($entrant) {
                $open = q("SELECT id FROM restore_requests WHERE entrant_id = ? AND ref_code = ? AND status IN ('email','review')", [$entrant['id'], $code])->fetchColumn();
                $fields = [
                    'claimed_opens' => $claimed === '' ? null : (int) $claimed,
                    'evidence'      => $evidence ?: null,
                    'device_match'  => in_array($code, device_codes(), true) ? 1 : 0,
                ];
                if ($open) {
                    q('UPDATE restore_requests SET claimed_opens = ?, evidence = ?, device_match = GREATEST(device_match, ?) WHERE id = ?', [...array_values($fields), $open]);
                } else {
                    q('INSERT INTO restore_requests (entrant_id, ref_code, new_entrant, device_match, claimed_opens, evidence, status, ip_hash, created_at)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', [
                        $entrant['id'], $code, $isNew ? 1 : 0, $fields['device_match'], $fields['claimed_opens'], $fields['evidence'], 'email', ip_hash(), now(),
                    ]);
                }
                $sent = otp_issue($entrant, 'restore');
                $_SESSION['restore_entrant_id'] = (int) $entrant['id'];
                $pending = q('SELECT * FROM entrants WHERE id = ?', [$entrant['id']])->fetch();
                if ($sent['ok']) {
                    $info = 'We sent a 6-digit code to ' . mask_email($pending['email']) . '.';
                    $dev = $sent['dev_code'];
                } else {
                    $error = $sent['error'];
                }
            }
        }
    } elseif ($step === 'code' && $pending) {
        $check = otp_check((int) $pending['id'], 'restore', (string) ($_POST['code'] ?? ''));
        if ($check === 'ok') {
            $approved = restore_email_confirmed((int) $pending['id']);
            $entrant = q('SELECT * FROM entrants WHERE id = ?', [$pending['id']])->fetch();
            unset($_SESSION['restore_entrant_id']);
            login_entrant((int) $entrant['id']);
            mark_owner_device($entrant);
            flash('success', $approved
                ? 'Your entry is restored. Your old link ' . implode(', ', $approved) . ' is yours again, with every open since the site came back.'
                : "Your email is confirmed and your entry is active. We're checking your old link — keep sharing it, every open is being saved.");
            redirect('flyfortwo/dashboard');
        }
        $error = otp_error_message($check);
    } elseif ($step === 'resend' && $pending) {
        $sent = otp_issue($pending, 'restore');
        if ($sent['ok']) {
            $info = 'A new code is on its way to ' . mask_email($pending['email']) . '.';
            $dev = $sent['dev_code'];
        } else {
            $error = $sent['error'];
        }
    }
}

$stepView = $pending ? 'code' : 'details';
$old = fn ($k) => e($_POST[$k] ?? ($me[$k] ?? ''));
$fieldErr = fn ($k) => isset($errors[$k]) ? '<small class="ff-hint" style="color:#8f2a1a">' . e($errors[$k]) . '</small>' : '';

$page_title = 'Restore my entry — FLY FOR TWO';
$nav_active = 'fly';
$extra_css  = ['assets/css/comp.css'];
require __DIR__ . '/includes/header.php';
?>
<section class="auth-wrap">
  <div class="auth-media">
    <img src="<?= e(url('assets/img/gallery/activation-23.jpg')) ?>" alt="GMF street team handing out FLY FOR TWO flyers">
    <div class="cap">
      <span class="kicker">Restore my entry</span>
      <p class="display big" style="margin-top:14px;">Your link <span class="serif-i text-orange">still works.</span></p>
      <p class="muted" style="max-width:440px">Our hosting provider had a serious outage and entries were lost. Every open of your personal link since the site came back is being saved. Restore your entry to get it back.</p>
    </div>
  </div>
  <div class="auth-form">
    <div class="ff-card">
      <div class="ff-body">
        <?php if ($stepView === 'details'): ?>
          <div class="ff-center"><div class="ff-icon"><i class="fa-solid fa-rotate-left"></i></div>
            <h1 class="ff-h">Restore my entry</h1>
            <p class="ff-sub">Search your email for <strong>"You're entered — FLY FOR TWO"</strong> from George Musical Festival. Copy the link in it, then fill in your details as you entered them.</p>
          </div>
          <?php if ($deviceCodes): ?><div class="ff-alert is-ok"><i class="fa-solid fa-mobile-screen"></i> Welcome back — this phone entered with the link <strong>/r/<?= e($deviceCodes[0]) ?></strong>. We've filled it in for you.</div><?php endif; ?>
          <form method="post" novalidate>
            <?= csrf_field() ?><input type="hidden" name="step" value="details"><input type="hidden" name="timer" value="<?= e(form_timer()) ?>">
            <div class="hp" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
            <div class="field"><label for="r-link">Your personal link (from the email)</label><input id="r-link" name="old_link" type="text" autocapitalize="characters" spellcheck="false" placeholder="georgemusicalfestival.co.za/r/ABC1234" value="<?= e($prefill) ?>" required><?= $fieldErr('old_link') ?></div>
            <div class="field"><label for="r-name">Full name</label><input id="r-name" name="full_name" type="text" autocomplete="name" value="<?= $old('full_name') ?>" required><?= $fieldErr('full_name') ?></div>
            <div class="field"><label for="r-email">Email address you entered with</label><input id="r-email" name="email" type="email" inputmode="email" autocomplete="email" value="<?= $old('email') ?>" required><small class="ff-hint">Your code is sent here.</small><?= $fieldErr('email') ?></div>
            <div class="form-row">
              <div class="field"><label for="r-phone">Cellphone</label><input id="r-phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="082 123 4567" value="<?= e($_POST['phone'] ?? '') ?>" required><?= $fieldErr('phone') ?></div>
              <div class="field"><label for="r-town">Your town</label>
                <select id="r-town" name="town"><option value="">Choose…</option><?php foreach ($towns as $t): ?><option<?= ($_POST['town'] ?? '') === $t ? ' selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?></select><?= $fieldErr('town') ?>
              </div>
            </div>
            <div class="field"><label for="r-age">Age group</label>
              <select id="r-age" name="age_group" required><option value="">Choose…</option><?php foreach (lines((string) setting('age_groups')) as $a): ?><option<?= ($_POST['age_group'] ?? '') === $a ? ' selected' : '' ?>><?= e($a) ?></option><?php endforeach; ?></select><?= $fieldErr('age_group') ?>
            </div>
            <div class="field"><label for="r-fly"><?= e(setting('travel_question')) ?></label>
              <select id="r-fly" name="fly_frequency" required><option value="">Choose an answer…</option><?php foreach ($flyOpts as $o): ?><option<?= ($_POST['fly_frequency'] ?? '') === $o ? ' selected' : '' ?>><?= e($o) ?></option><?php endforeach; ?></select><?= $fieldErr('fly_frequency') ?>
            </div>
            <div class="field"><label for="r-opens">Your score before the outage <span class="muted">(optional)</span></label><input id="r-opens" name="claimed_opens" type="text" inputmode="numeric" placeholder="e.g. 120 link opens" value="<?= e($_POST['claimed_opens'] ?? '') ?>"><?= $fieldErr('claimed_opens') ?></div>
            <div class="field"><label for="r-evidence">Anything that helps us check it <span class="muted">(optional)</span></label><textarea id="r-evidence" name="evidence" rows="3" placeholder="e.g. I have a screenshot of my dashboard from 20 October — I'll email it to <?= e(setting('contact_email')) ?>"><?= e($_POST['evidence'] ?? '') ?></textarea></div>
            <div class="ff-consent is-required"><input type="checkbox" id="rc-terms" name="consent_terms" value="1"<?= !empty($_POST['consent_terms']) ? ' checked' : '' ?>><label for="rc-terms"><span class="ff-tag">Required</span> I agree to the <?= e(setting('comp_name')) ?> <a href="<?= e(url('flyfortwo/terms')) ?>" target="_blank">terms</a> and <a href="<?= e(url('flyfortwo/privacy')) ?>" target="_blank">privacy notice</a>.</label></div><?= $fieldErr('consent_terms') ?>
            <div class="ff-consent is-required"><input type="checkbox" id="rc-popia" name="consent_popia" value="1"<?= !empty($_POST['consent_popia']) ? ' checked' : '' ?>><label for="rc-popia"><span class="ff-tag">Required</span> <strong>POPIA consent:</strong> <?= e(setting('popia_consent_text')) ?></label></div><?= $fieldErr('consent_popia') ?>
            <div class="ff-consent is-optional"><input type="checkbox" id="rc-mkt" name="consent_marketing" value="1"<?= !empty($_POST['consent_marketing']) ? ' checked' : '' ?>><label for="rc-mkt"><span class="ff-tag ff-tag-soft">Optional</span> Keep me updated on future GMF × ACSA events and travel offers.</label></div>
            <?php if ($error): ?><div class="ff-alert"><?= e($error) ?><?php if (str_contains($error, 'Sign in')): ?> <a href="<?= e(url('flyfortwo/login')) ?>">Sign in</a><?php endif; ?></div><?php endif; ?>
            <button class="ff-btn ff-btn-primary" type="submit">Send my code <i class="fa-solid fa-arrow-right"></i></button>
          </form>
          <p class="ff-fine">Lost the email? Use the same email address and cellphone number you entered with, and tell us what you remember — we'll check it. New here? <a href="<?= e(url('flyfortwo/')) ?>">Enter the competition</a></p>
        <?php else: ?>
          <div class="ff-center"><div class="ff-icon"><i class="fa-regular fa-envelope-open"></i></div>
            <h1 class="ff-h">Enter your code</h1>
            <p class="ff-sub">Sent to <strong><?= e(mask_email($pending['email'])) ?></strong>. It expires in <?= (int) setting('otp_ttl_minutes') ?> minutes.</p>
          </div>
          <form method="post" novalidate>
            <?= csrf_field() ?><input type="hidden" name="step" value="code">
            <div class="field"><label for="r-code" class="sr-only">6-digit code</label>
              <input id="r-code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" placeholder="••••••" style="text-align:center;font-size:1.8rem;letter-spacing:.5em;font-weight:800" autofocus>
            </div>
            <?php if ($dev): ?><div class="ff-dev">Test mode (email not configured): your code is <?= e($dev) ?></div><?php endif; ?>
            <?php if ($info && !$error): ?><div class="ff-alert is-ok"><?= e($info) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="ff-alert"><?= e($error) ?></div><?php endif; ?>
            <button class="ff-btn ff-btn-primary" type="submit">Restore my entry</button>
          </form>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="step" value="resend">
            <button class="ff-btn ff-btn-ghost" type="submit">Resend code</button>
          </form>
          <a class="ff-btn ff-btn-ghost ff-small" href="<?= e(url('flyfortwo/restore?restart=1')) ?>">Start again</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
