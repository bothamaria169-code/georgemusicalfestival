<?php
require __DIR__ . '/_layout.php';
require dirname(__DIR__) . '/includes/campaign-overview.php';

$me = require_admin('admin');
// Cell C managers are read-only accounts in the Cell C admin, so they never have a login here.
$cellc = cellc_pdo();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $action = $_POST['action'] ?? '';
    $forCellc = ($_POST['target'] ?? '') === 'cellc';
    if ($forCellc && !$cellc) {
        flash('error', 'The Cell C admin cannot be reached right now, so nothing was changed.');
    } elseif ($forCellc && $action === 'password') {
        $pw = (string) $_POST['password'];
        if (strlen($pw) < 10) {
            flash('error', 'Password must be at least 10 characters.');
        } else {
            $cellc->prepare("UPDATE admins SET password_hash = ? WHERE id = ? AND role = 'viewer'")->execute([password_hash($pw, PASSWORD_DEFAULT), (int) $_POST['id']]);
            audit('user.password', ['cellc_id' => (int) $_POST['id']]);
            flash('success', 'Password updated.');
        }
    } elseif ($forCellc && $action === 'delete') {
        $cellc->prepare("DELETE FROM admins WHERE id = ? AND role = 'viewer'")->execute([(int) $_POST['id']]);
        audit('user.delete', ['cellc_id' => (int) $_POST['id']]);
        flash('success', 'Access removed.');
    } elseif ($action === 'create') {
        $email = mb_strtolower(trim((string) $_POST['email']));
        $pw = (string) $_POST['password'];
        $asCellc = ($_POST['role'] ?? '') === 'cellc_manager';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pw) < 10) {
            flash('error', 'Enter a valid email and a password of at least 10 characters.');
        } elseif ($asCellc) {
            if (!$cellc) {
                flash('error', 'The Cell C admin cannot be reached right now, so nothing was changed.');
            } else {
                $dup = $cellc->prepare('SELECT 1 FROM admins WHERE email = ?');
                $dup->execute([$email]);
                if ($dup->fetchColumn()) {
                    flash('error', 'That email already has Cell C access.');
                } else {
                    $cellc->prepare("INSERT INTO admins (name, email, password_hash, role, created_at) VALUES (?, ?, ?, 'viewer', ?)")->execute([
                        mb_substr(trim((string) $_POST['name']), 0, 120), $email, password_hash($pw, PASSWORD_DEFAULT), now(),
                    ]);
                    audit('user.create', ['email' => $email, 'role' => 'cellc_manager']);
                    flash('success', 'Cell C manager created. They sign in at /cellc/admin. Share the password with them securely.');
                }
            }
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
$managers = $cellc ? $cellc->query("SELECT * FROM admins WHERE role = 'viewer' ORDER BY name")->fetchAll() : [];
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
          <td><span class="pill <?= $u['role'] === 'admin' ? 'pill-warn' : 'pill-info' ?>"><?= $u['role'] === 'admin' ? 'GMF admin' : 'ACSA manager' ?></span></td>
          <td class="muted"><?= e($u['last_login_at'] ? fmt_date($u['last_login_at'], 'j M H:i') : 'never') ?></td>
          <td style="display:flex;gap:6px">
            <form method="post" onsubmit="var p=prompt('New password (min 10 characters):');if(!p)return false;this.password.value=p;"><?= csrf_field() ?><input type="hidden" name="action" value="password"><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"><input type="hidden" name="password"><button class="btn btn-sm" type="submit" title="Reset password"><i class="fa-solid fa-key"></i></button></form>
            <?php if ((int) $u['id'] !== (int) $me['id']): ?>
            <form method="post" onsubmit="return confirm('Remove access for <?= e($u['email']) ?>?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"><button class="btn btn-sm btn-danger" type="submit"><i class="fa-solid fa-trash"></i></button></form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php foreach ($managers as $u): ?>
        <tr>
          <td><strong><?= e($u['name']) ?></strong></td>
          <td><?= e($u['email']) ?></td>
          <td><span class="pill pill-warn" style="color:#ffb27a">Cell C manager</span></td>
          <td class="muted"><?= e($u['last_login_at'] ? fmt_date($u['last_login_at'], 'j M H:i') : 'never') ?></td>
          <td style="display:flex;gap:6px">
            <form method="post" onsubmit="var p=prompt('New password (min 10 characters):');if(!p)return false;this.password.value=p;"><?= csrf_field() ?><input type="hidden" name="action" value="password"><input type="hidden" name="target" value="cellc"><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"><input type="hidden" name="password"><button class="btn btn-sm" type="submit" title="Reset password"><i class="fa-solid fa-key"></i></button></form>
            <form method="post" onsubmit="return confirm('Remove access for <?= e($u['email']) ?>?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="target" value="cellc"><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"><button class="btn btn-sm btn-danger" type="submit"><i class="fa-solid fa-trash"></i></button></form>
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
      <div class="fg"><label class="f">Role</label><select class="in" name="role"><option value="viewer">ACSA manager (ACSA reporting & audit access)</option><?php if ($cellc): ?><option value="cellc_manager">Cell C manager (Cell C reporting & audit access)</option><?php endif; ?><option value="admin">GMF admin (full control)</option></select></div>
      <button class="btn btn-primary" type="submit">Create access</button>
    </form>
    <p class="help">Managers are read-only. They see their own campaign's overview, entrant records with exports, and the draw logs, and cannot change anything. ACSA managers sign in here. Cell C managers sign in at /cellc/admin and never see ACSA data.</p>
  </div>
</div>
<?php admin_foot(); ?>
