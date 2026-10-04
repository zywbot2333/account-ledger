<?php
/** @var array $user 当前登录用户（由调用方传入） */
$active = $active ?? '';
$pageTitle = $pageTitle ?? APP_NAME;
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> - <?= e(APP_NAME) ?></title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ccircle cx='50' cy='50' r='48' fill='%23101425'/%3E%3Ctext x='50' y='70' font-size='58' text-anchor='middle' fill='white' font-family='Arial'%3E%E2%88%91%3C/text%3E%3C/svg%3E">
<link rel="stylesheet" href="assets/style.css?v=<?= e(APP_VERSION) ?>">
</head>
<body class="app-body">
<div class="shell">

  <aside class="side">
    <a class="side-brand" href="index.php">
      <span class="brand-ico"><?= icon('wallet') ?></span>
      <span class="side-brand-txt"><?= e(APP_NAME) ?></span>
    </a>
    <nav class="side-nav">
      <span class="side-cap">菜单</span>
      <a href="index.php" class="side-link <?= $active === 'index' ? 'active' : '' ?>"><?= icon('grid') ?>总览</a>
      <a href="reports.php" class="side-link <?= $active === 'reports' ? 'active' : '' ?>"><?= icon('chart') ?>收支报表</a>
    </nav>
    <div class="side-foot">
      <span class="avatar"><?= e(mb_substr($user['username'], 0, 1)) ?></span>
      <div class="side-user">
        <b><?= e($user['username']) ?></b>
        <i>我的账本</i>
      </div>
      <form method="post" action="logout.php" class="inline-form">
        <?= csrf_field() ?>
        <button class="side-out" type="submit" title="退出登录"><?= icon('logout') ?></button>
      </form>
    </div>
  </aside>

  <div class="main-col">
    <header class="mobilebar">
      <a class="brand" href="index.php"><span class="brand-ico"><?= icon('wallet') ?></span><?= e(APP_NAME) ?></a>
      <nav class="m-nav">
        <a href="index.php" class="<?= $active === 'index' ? 'active' : '' ?>">总览</a>
        <a href="reports.php" class="<?= $active === 'reports' ? 'active' : '' ?>">报表</a>
      </nav>
      <form method="post" action="logout.php" class="inline-form">
        <?= csrf_field() ?>
        <button class="side-out" type="submit" title="退出登录"><?= icon('logout') ?></button>
      </form>
    </header>

    <main class="content">
    <?php foreach (take_flashes() as $f): ?>
      <div class="toast toast-<?= e($f['t']) ?>"><?= e($f['m']) ?></div>
    <?php endforeach; ?>
