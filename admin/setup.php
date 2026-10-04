<?php
require __DIR__ . '/inc/admin-auth.php';

// 仅当还没有任何管理员时可用（用于首次初始化）
if (admins_count() > 0) {
    redirect('login.php');
}

$error = '';
$username = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        $error = '页面已过期，请刷新后重试';
    } else {
        $username = post('username');
        $password = post('password');
        $confirm = post('confirm');
        if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $username)) {
            $error = '管理员用户名需为 3-30 位字母、数字或下划线';
        } elseif (strlen($password) < 8 || strlen($password) > 64) {
            $error = '管理员密码长度需为 8-64 位';
        } elseif ($password !== $confirm) {
            $error = '两次输入的密码不一致';
        } else {
            $st = db()->prepare('INSERT INTO admins (username, password_hash) VALUES (?, ?)');
            $st->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
            flash('ok', '管理员创建成功，请登录');
            redirect('login.php');
        }
    }
}
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>初始化管理员</title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ccircle cx='50' cy='50' r='48' fill='%23101425'/%3E%3Ctext x='50' y='70' font-size='58' text-anchor='middle' fill='white' font-family='Arial'%3E%E2%88%91%3C/text%3E%3C/svg%3E">
<link rel="stylesheet" href="../assets/style.css">
</head>
<body class="auth-body">
  <div class="auth-wrap">
    <div class="auth-card">
      <div class="auth-logo" style="background:linear-gradient(135deg,#101425,#313858)">⚙</div>
      <h1>初始化管理员</h1>
      <p class="auth-sub">系统检测到还没有管理员，请创建第一个管理员账号</p>
      <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
      <form method="post" novalidate>
        <?= csrf_field() ?>
        <label class="field">管理员用户名
          <input class="input" name="username" value="<?= e($username) ?>" maxlength="30" autofocus placeholder="3-30 位字母/数字/下划线">
        </label>
        <label class="field">密码
          <input class="input" type="password" name="password" maxlength="64" placeholder="至少 8 位">
        </label>
        <label class="field">确认密码
          <input class="input" type="password" name="confirm" maxlength="64">
        </label>
        <button class="btn btn-block" type="submit">创建管理员</button>
      </form>
    </div>
  </div>
</body>
</html>
