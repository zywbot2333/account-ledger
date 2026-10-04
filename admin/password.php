<?php
require __DIR__ . '/inc/admin-auth.php';
$admin = admin_require();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        $error = '页面已过期，请刷新后重试';
    } else {
        $cur = post('current');
        $new = post('new');
        $confirm = post('confirm');
        $st = db()->prepare('SELECT password_hash FROM admins WHERE id = ?');
        $st->execute([$admin['id']]);
        $row = $st->fetch();
        if (!$row || !password_verify($cur, $row['password_hash'])) {
            $error = '当前密码不正确';
        } elseif (strlen($new) < 8 || strlen($new) > 64) {
            $error = '新密码长度需为 8-64 位';
        } elseif ($new !== $confirm) {
            $error = '两次输入的新密码不一致';
        } else {
            db()->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($new, PASSWORD_DEFAULT), $admin['id']]);
            flash('ok', '管理员密码已修改');
            redirect('password.php');
        }
    }
}

$adminTitle = '修改密码';
$active = 'password';
require __DIR__ . '/inc/header.php';
?>

<section class="section-head" style="margin-top:0">
  <h2>修改管理员密码</h2>
</section>

<section class="card form-card" style="margin-top:0">
  <?php if ($error): ?><div class="alert alert-error" style="margin-bottom:12px"><?= e($error) ?></div><?php endif; ?>
  <form method="post" class="tx-form">
    <?= csrf_field() ?>
    <div class="tx-grid">
      <label class="field field-wide">当前密码
        <input class="input" type="password" name="current" maxlength="64" required>
      </label>
      <label class="field">新密码
        <input class="input" type="password" name="new" maxlength="64" required placeholder="至少 8 位">
      </label>
      <label class="field">确认新密码
        <input class="input" type="password" name="confirm" maxlength="64" required>
      </label>
    </div>
    <button class="btn" type="submit">保存新密码</button>
  </form>
</section>

<?php require __DIR__ . '/inc/footer.php'; ?>
