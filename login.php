<?php
/** Passwordless sign-in for entrants: email → 6-digit code → dashboard. */
require __DIR__ . '/includes/bootstrap.php';

if (current_entrant()) {
    redirect('flyfortwo/dashboard');
}

$step  = 'email';
$error = '';
$info  = '';
$dev   = null;
$pendingId = (int) ($_SESSION['login_entrant_id'] ?? 0);
$pending = $pendingId ? q("SELECT * FROM entrants WHERE id = ? AND status = 'verified'", [$pendingId])->fetch() : null;

if (($_GET['restart'] ?? '') === '1') {
    unset($_SESSION['login_entrant_id']);
    $pending = null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        $error = 'Your session expired. Please try again.';
    } elseif (($_POST['step'] ?? '') === 'email') {
        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (!rate_limit('login_email', client_ip(), 60, 3600)) {
            $error = 'Too many sign-in attempts. Please try again later.';
        } else {
            $found = q('SELECT * FROM entrants WHERE email = ?', [$email])->fetch();
            if (!$found) {
                $error = "We couldn't find an entry with that email. Enter the competition first — it takes a minute.";
            } elseif ($found['status'] === 'pending') {
                $error = "That entry was never verified. Please enter again on the competition page to receive a new code.";
            } elseif ($found['status'] !== 'verified') {
                $error = 'This entry cannot sign in. Please contact us if you think this is a mistake.';
            } else {
                $sent = otp_issue($found, 'login');
                $_SESSION['login_entrant_id'] = (int) $found['id'];
                $pending = $found;
                if ($sent['ok']) {
                    $info = 'We sent a 6-digit code to ' . mask_email($found['email']) . '.';
                    $dev = $sent['dev_code'];
                } else {
                    $error = $sent['error'];
                }
            }
        }
    } elseif (($_POST['step'] ?? '') === 'code' && $pending) {
        $check = otp_check((int) $pending['id'], 'login', (string) ($_POST['code'] ?? ''));
        if ($check === 'ok') {
            unset($_SESSION['login_entrant_id']);
            login_entrant((int) $pending['id']);
            mark_owner_device($pending);
            redirect('flyfortwo/dashboard');
        }
        $error = otp_error_message($check);
    } elseif (($_POST['step'] ?? '') === 'resend' && $pending) {
        $sent = otp_issue($pending, 'login');
        if ($sent['ok']) {
            $info = 'A new code is on its way to ' . mask_email($pending['email']) . '.';
            $dev = $sent['dev_code'];
        } else {
            $error = $sent['error'];
        }
    }
}
if ($pending) {
    $step = 'code';
}

$page_title = 'Sign in — FLY FOR TWO dashboard';
$nav_active = 'fly';
$extra_css  = ['assets/css/comp.css'];
require __DIR__ . '/includes/header.php';
?>
<section class="auth-wrap">
  <div class="auth-media">
    <img src="<?= e(url('assets/img/gallery/activation-23.jpg')) ?>" alt="GMF street team handing out FLY FOR TWO flyers">
    <div class="cap">
      <span class="kicker">Your dashboard</span>
      <p class="display big" style="margin-top:14px;">Share it. <span class="serif-i text-orange">Own it.</span></p>
      <p class="muted" style="max-width:420px">Your personal link, your entries and every friend you've brought to the festival — in one place.</p>
    </div>
  </div>
  <div class="auth-form">
    <div class="ff-card">
      <div class="ff-body">
        <?php if ($step === 'email'): ?>
          <div class="ff-center"><div class="ff-icon"><i class="fa-regular fa-user"></i></div>
            <h1 class="ff-h">Sign in</h1>
            <p class="ff-sub">Use the email address you entered FLY FOR TWO with. We'll send you a one-time code — no password needed.</p>
          </div>
          <form method="post" novalidate>
            <?= csrf_field() ?><input type="hidden" name="step" value="email">
            <div class="field"><label for="l-email">Email address</label><input id="l-email" type="email" name="email" required autocomplete="email" inputmode="email" value="<?= e($_POST['email'] ?? '') ?>" autofocus></div>
            <?php if ($error): ?><div class="ff-alert"><?= e($error) ?></div><?php endif; ?>
            <button class="ff-btn ff-btn-primary" type="submit">Send my code</button>
          </form>
          <p class="ff-fine">Not entered yet? <a href="<?= e(url('flyfortwo/')) ?>">Enter the competition</a></p>
        <?php else: ?>
          <div class="ff-center"><div class="ff-icon"><i class="fa-regular fa-envelope-open"></i></div>
            <h1 class="ff-h">Enter your code</h1>
            <p class="ff-sub">Sent to <strong><?= e(mask_email($pending['email'])) ?></strong>. It expires in <?= (int) setting('otp_ttl_minutes') ?> minutes.</p>
          </div>
          <form method="post" novalidate>
            <?= csrf_field() ?><input type="hidden" name="step" value="code">
            <div class="field"><label for="l-code" class="sr-only">6-digit code</label>
              <input id="l-code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" placeholder="••••••" style="text-align:center;font-size:1.8rem;letter-spacing:.5em;font-weight:800" autofocus>
            </div>
            <?php if ($dev): ?><div class="ff-dev">Test mode (email not configured): your code is <?= e($dev) ?></div><?php endif; ?>
            <?php if ($info && !$error): ?><div class="ff-alert is-ok"><?= e($info) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="ff-alert"><?= e($error) ?></div><?php endif; ?>
            <button class="ff-btn ff-btn-primary" type="submit">Sign in</button>
          </form>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="step" value="resend">
            <button class="ff-btn ff-btn-ghost" type="submit">Resend code</button>
          </form>
          <a class="ff-btn ff-btn-ghost ff-small" href="<?= e(url('flyfortwo/login?restart=1')) ?>">Use a different email</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
