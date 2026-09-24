<?php
require __DIR__ . '/_layout.php';

$me = require_admin('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $email = mb_strtolower(trim((string) $_POST['email']));
        $pw = (string) $_POST['password'];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pw) < 10) {
            flash('error', 'Enter a valid email and a password of at least 10 characters.');
        } elseif (q('SELECT 1 FROM admins WHERE email = ?', [$email])->fetchColumn()) {
            flash('error', 'That email already has access.');
        } else {
            q('INSERT INTO admins (name, email, password_hash, role, created_at) VALUES (?, ?, ?, ?, ?)', [
                mb_substr(trim((string) $_POST['name']), 0, 120), $email, password_hash($pw, PASSWORD_DEFAULT), $_POST['role'] === 'admin' ? 'admin' : 'viewer', now(),
            ]);
            audit('user.create', ['email' => $email, 'role' => $_POST['role']]);
            flash('success', 'Access created. Share the password with them securely.');
        }
    } elseif ($action === 'password') {
        $pw = (string) $_POST['password'];
        if (strlen($pw) < 10) {
            flash('error', 'Password must be at least 10 characters.');
        } else {
            q('UPDATE admins SET password_hash = ? WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), (int) $_POST['id']]);
            audit('user.password', ['id' => (int) $_POST['id']]);
            flash('success', 'Password updated.');
        }
    } elseif ($action === 'delete' && (int) $_POST['id'] !== (int) $me['id']) {
        q('DELETE FROM admins WHERE id = ?', [(int) $_POST['id']]);
        audit('user.delete', ['id' => (int) $_POST['id']]);
        flash('success', 'Access removed.');
    }
    redirect('admin/users.php');
}

$users = q('SELECT * FROM admins ORDER BY role, name')->fetchAll();
admin_head('Team & access', 'users');
?>
<div class="grid g-21">
  <div class="card">
    <h2>People with access</h2>
    <div class="t-wrap"><table class="t">
      <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Last sign-in</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td><strong><?= e($u['name']) ?></strong></td>
          <td><?= e($u['email']) ?></td>
          <td><span class="pill <?= $u['role'] === 'admin' ? 'pill-warn' : 'pill-info' ?>"><?= $u['role'] === 'admin' ? 'GMF admin' : 'Read-only' ?></span></td>
          <td class="muted"><?= e($u['last_login_at'] ? fmt_date($u['last_login_at'], 'j M H:i') : 'never') ?></td>
          <td style="display:flex;gap:6px">
            <form method="post" onsubmit="var p=prompt('New password (min 10 characters):');if(!p)return false;this.password.value=p;"><?= csrf_field() ?><input type="hidden" name="action" value="password"><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"><input type="hidden" name="password"><button class="btn btn-sm" type="submit" title="Reset password"><i class="fa-solid fa-key"></i></button></form>
            <?php if ((int) $u['id'] !== (int) $me['id']): ?>
            <form method="post" onsubmit="return confirm('Remove access for <?= e($u['email']) ?>?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"><button class="btn btn-sm btn-danger" type="submit"><i class="fa-solid fa-trash"></i></button></form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
  <div class="card">
    <h2>Give someone access</h2>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="create">
      <div class="fg"><label class="f">Name</label><input class="in" name="name" required></div>
      <div class="fg"><label class="f">Email</label><input class="in" type="email" name="email" required></div>
      <div class="fg"><label class="f">Password</label><input class="in" type="text" name="password" minlength="10" required></div>
      <div class="fg"><label class="f">Role</label><select class="in" name="role"><option value="viewer">Read-only (ACSA reporting & audit access)</option><option value="admin">GMF admin (full control)</option></select></div>
      <button class="btn btn-primary" type="submit">Create access</button>
    </form>
    <p class="help">Read-only users see the overview, all entrant records with exports, and the draw logs — the standing read access ACSA requires. They cannot change anything.</p>
  </div>
</div>
<?php admin_foot(); ?>
