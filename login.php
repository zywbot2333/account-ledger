<?php
require __DIR__ . '/init.php';
if (current_user()) {
    redirect('index.php');
}

$error = '';
$username = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        $error = '页面已过期，请刷新后重试';
    } else {
        $username = post('username');
        $error = attempt_login($username, post('password'));
        if ($error === '') {
            redirect('index.php');
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
<title>登录 - <?= e(APP_NAME) ?></title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ccircle cx='50' cy='50' r='48' fill='%236366f1'/%3E%3Ctext x='50' y='70' font-size='58' text-anchor='middle' fill='white' font-family='Arial'%3E%E2%88%91%3C/text%3E%3C/svg%3E">
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="auth-body">
  <div class="auth-wrap">
    <div class="auth-card">
      <div class="auth-logo">¥</div>
      <h1>登录 <?= e(APP_NAME) ?></h1>
      <p class="auth-sub">记录每个账号的收入与支出</p>
      <?php foreach ($flashes as $f): ?>
        <div class="alert alert-<?= e($f['t']) ?>"><?= e($f['m']) ?></div>
      <?php endforeach; ?>
      <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
      <form method="post" novalidate>
        <?= csrf_field() ?>
        <label class="field">用户名
          <input class="input" name="username" value="<?= e($username) ?>" maxlength="20" autofocus placeholder="用户名">
        </label>
        <label class="field">密码
          <input class="input" type="password" name="password" maxlength="64" placeholder="密码">
        </label>
        <button class="btn btn-block" type="submit">登 录</button>
      </form>
      <p class="auth-alt">还没有账号？<a href="register.php">立即注册</a></p>
    </div>
  </div>
</body>
</html>
