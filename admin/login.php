<?php
require __DIR__ . '/_layout.php';

if (current_admin()) {
    redirect('admin/index.php');
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
    if (!csrf_ok()) {
        $error = 'Session expired — please try again.';
    } elseif (!rate_limit('admin_login', client_ip(), 8, 900)) {
        $error = 'Too many attempts. Wait 15 minutes and try again.';
    } else {
        $a = q('SELECT * FROM admins WHERE email = ?', [$email])->fetch();
        if ($a && password_verify((string) ($_POST['password'] ?? ''), $a['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int) $a['id'];
            q('UPDATE admins SET last_login_at = ? WHERE id = ?', [now(), $a['id']]);
            if (password_needs_rehash($a['password_hash'], PASSWORD_DEFAULT)) {
                q('UPDATE admins SET password_hash = ? WHERE id = ?', [password_hash((string) $_POST['password'], PASSWORD_DEFAULT), $a['id']]);
            }
            audit('admin.login', $a['email']);
            redirect('admin/index.php');
        }
        $error = 'Email or password is incorrect.';
    }
}
?><!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>Sign in — FLY FOR TWO admin</title>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Oswald:wght@600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('assets/css/admin.css')) ?>"></head>
<body>
<div class="login-page">
  <form class="login-card" method="post">
    <img src="<?= e(url('assets/img/brand/gmf-logo-orange.png')) ?>" alt="George Musical Festival">
    <h1>Control room</h1>
    <p class="muted" style="margin:0 0 20px">FLY FOR TWO · GMF × ACSA</p>
    <?= csrf_field() ?>
    <div class="fg"><label class="f" for="email">Email</label><input class="in" id="email" type="email" name="email" required autofocus value="<?= e($_POST['email'] ?? '') ?>"></div>
    <div class="fg"><label class="f" for="pw">Password</label><input class="in" id="pw" type="password" name="password" required></div>
    <?php if ($error): ?><div class="a-flash a-flash-error" style="margin:0 0 14px"><?= e($error) ?></div><?php endif; ?>
    <button class="btn btn-primary" style="width:100%;justify-content:center;padding:13px" type="submit">Sign in</button>
  </form>
</div>
</body></html>
