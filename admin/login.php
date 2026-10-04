<?php
require __DIR__ . '/inc/admin-auth.php';

if (admins_count() === 0) {
    redirect('setup.php');
}
if (admin_user()) {
    redirect('index.php');
}

$error = '';
$username = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        $error = '页面已过期，请刷新后重试';
    } else {
        $username = post('username');
        $password = post('password');
        if ($username === '' || $password === '') {
            $error = '请输入用户名和密码';
        } else {
            $st = db()->prepare('SELECT id, password_hash FROM admins WHERE username = ?');
            $st->execute([$username]);
            $a = $st->fetch();
            if (!$a || !password_verify($password, $a['password_hash'])) {
                $error = '管理员用户名或密码错误';
            } else {
                session_regenerate_id(true);
                $_SESSION['aid'] = (int)$a['id'];
                redirect('index.php');
            }
        }
    }
}
$flashes = take_flashes();
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>管理员登录</title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ccircle cx='50' cy='50' r='48' fill='%23101425'/%3E%3Ctext x='50' y='70' font-size='58' text-anchor='middle' fill='white' font-family='Arial'%3E%E2%88%91%3C/text%3E%3C/svg%3E">
<link rel="stylesheet" href="../assets/style.css">
</head>
<body class="auth-body">
  <div class="auth-wrap">
    <div class="auth-card">
      <div class="auth-logo" style="background:linear-gradient(135deg,#101425,#313858)">⚙</div>
      <h1>管理后台登录</h1>
      <p class="auth-sub"><?= e(APP_NAME) ?> · 管理员专用入口</p>
      <?php foreach ($flashes as $f): ?>
        <div class="alert alert-<?= e($f['t']) ?>"><?= e($f['m']) ?></div>
      <?php endforeach; ?>
      <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
      <form method="post" novalidate>
        <?= csrf_field() ?>
        <label class="field">管理员用户名
          <input class="input" name="username" value="<?= e($username) ?>" maxlength="50" autofocus>
        </label>
        <label class="field">密码
          <input class="input" type="password" name="password" maxlength="64">
        </label>
        <button class="btn btn-block" type="submit">登 录</button>
      </form>
      <p class="auth-alt"><a href="../index.php">← 返回前台</a></p>
    </div>
  </div>
</body>
</html>
