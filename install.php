<?php
/**
 * 账号收支 · 安装程序
 * 流程：环境检查 → 填写数据库与管理员信息 → 建库/导表/创建管理员 → 生成 config.php
 * 安装成功后建议删除本文件。
 */
declare(strict_types=1);

date_default_timezone_set('Asia/Shanghai');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'path' => '/']);
session_start();

require __DIR__ . '/inc/helpers.php'; // 仅用通用函数，不依赖已安装的配置

$ROOT = __DIR__;
$error = '';
$done = false;
$installedInfo = null;

/** 当前是否已安装：config.php 存在且能连上数据库 */
function detect_installed(string $root): bool
{
    if (!is_file($root . '/config.php')) {
        return false;
    }
    require $root . '/config.php';
    try {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        new PDO($dsn, DB_USER, DB_PASS);
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

$installed = detect_installed($ROOT);

// 环境检查项
$checks = [
    'PHP 版本 ≥ 8.0（当前 ' . PHP_VERSION . '）' => version_compare(PHP_VERSION, '8.0.0', '>='),
    'PDO MySQL 扩展（pdo_mysql）'           => extension_loaded('pdo_mysql'),
    '多字节字符串扩展（mbstring）'          => extension_loaded('mbstring'),
    'schema.sql 建表脚本存在'               => is_file($ROOT . '/schema.sql'),
    '站点目录可写（用于生成 config.php）'   => is_writable($ROOT),
];
$envOk = !in_array(false, $checks, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('do') === 'install') {
    if (!csrf_ok()) {
        $error = '页面已过期，请刷新后重试';
    } elseif (!$envOk) {
        $error = '环境检查未通过，请先解决标红的问题';
    } else {
        $force = post('force') === '1';
        if ($installed && !$force) {
            $error = '系统已安装。如确定要重新安装（将清空所选数据库中的全部数据），请勾选下方危险选项。';
        } else {
            $host = post('host', '127.0.0.1');
            $port = (int)post('port', '3306');
            $name = post('name');
            $dbUser = post('dbuser');
            $dbPass = post('dbpass');
            $appName = post('appname', '') ?: '账号收支';
            $admUser = post('adminuser');
            $admPass = post('adminpass');
            $admConfirm = post('adminconfirm');

            if ($host === '') {
                $error = '请填写数据库地址';
            } elseif ($port < 1 || $port > 65535) {
                $error = '数据库端口不正确';
            } elseif (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $name)) {
                $error = '数据库名只能包含字母、数字和下划线';
            } elseif ($dbUser === '') {
                $error = '请填写数据库用户名';
            } elseif (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $admUser)) {
                $error = '管理员用户名需为 3-30 位字母、数字或下划线';
            } elseif (strlen($admPass) < 8 || strlen($admPass) > 64) {
                $error = '管理员密码长度需为 8-64 位';
            } elseif ($admPass !== $admConfirm) {
                $error = '两次输入的管理员密码不一致';
            } else {
                try {
                    // 1. 连接数据库服务器
                    $pdo = new PDO(
                        "mysql:host={$host};port={$port};charset=utf8mb4",
                        $dbUser,
                        $dbPass,
                        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                    );

                    // 2. 数据库不存在则尝试创建
                    $exists = $pdo->query("SHOW DATABASES LIKE " . $pdo->quote($name))->fetch();
                    if (!$exists) {
                        $pdo->exec("CREATE DATABASE `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    }
                    $pdo->exec("USE `{$name}`");

                    // 3. 覆盖安装时先清空旧表
                    if ($force) {
                        foreach (['transactions', 'accounts', 'reg_log', 'users', 'admins'] as $t) {
                            $pdo->exec("DROP TABLE IF EXISTS `{$t}`");
                        }
                    }

                    // 4. 导入建表脚本
                    $sql = (string)file_get_contents($ROOT . '/schema.sql');
                    foreach (preg_split('/;\s*\n/', $sql) as $raw) {
                        $stmt = trim(preg_replace('/^\s*--.*$/m', '', (string)$raw));
                        if ($stmt !== '') {
                            $pdo->exec($stmt);
                        }
                    }

                    // 5. 创建管理员（已存在则跳过）
                    $adminCreated = false;
                    $adminCount = (int)$pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
                    if ($adminCount === 0) {
                        $pdo->prepare('INSERT INTO admins (username, password_hash) VALUES (?, ?)')
                            ->execute([$admUser, password_hash($admPass, PASSWORD_DEFAULT)]);
                        $adminCreated = true;
                    }

                    // 6. 生成配置文件
                    $line = static function (mixed $v): string {
                        return var_export($v, true);
                    };
                    $configPhp = "<?php\n"
                        . '// 站点配置 —— 由安装程序生成于 ' . date('Y-m-d H:i') . "\n\n"
                        . '// 数据库连接' . "\n"
                        . 'define(\'DB_HOST\', ' . $line($host) . ');' . "\n"
                        . 'define(\'DB_PORT\', ' . $port . ');' . "\n"
                        . 'define(\'DB_NAME\', ' . $line($name) . ');' . "\n"
                        . 'define(\'DB_USER\', ' . $line($dbUser) . ');' . "\n"
                        . 'define(\'DB_PASS\', ' . $line($dbPass) . ');' . "\n\n"
                        . '// 应用' . "\n"
                        . 'define(\'APP_NAME\', ' . $line($appName) . ');' . "\n"
                        . 'define(\'APP_DEBUG\', false);      // 调试模式（true 时数据库异常会直接抛出）' . "\n"
                        . 'define(\'DAILY_REG_LIMIT\', 2);   // 每个IP每天最多注册账号数' . "\n"
                        . 'define(\'TX_PER_PAGE\', 20);      // 流水分页每页条数' . "\n";

                    if (@file_put_contents($ROOT . '/config.php', $configPhp) === false) {
                        throw new RuntimeException('无法写入 config.php，请检查站点目录写入权限');
                    }

                    $done = true;
                    $installedInfo = [
                        'app' => $appName,
                        'admin_created' => $adminCreated,
                        'admin_user' => $admUser,
                    ];
                } catch (Throwable $e) {
                    $error = '安装失败：' . $e->getMessage();
                }
            }
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
<title>安装程序 - 账号收支</title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ccircle cx='50' cy='50' r='48' fill='%236366f1'/%3E%3Ctext x='50' y='70' font-size='58' text-anchor='middle' fill='white' font-family='Arial'%3E%E2%88%91%3C/text%3E%3C/svg%3E">
<link rel="stylesheet" href="assets/style.css">
<style>
  .chk { display: flex; align-items: center; gap: 9px; padding: 8px 12px; border-radius: 10px; font-size: 13.5px; }
  .chk.ok { background: var(--up-bg); color: #166534; }
  .chk.bad { background: var(--down-bg); color: #b91c1c; }
  .step-tip { font-size: 12.5px; color: var(--muted); margin: 4px 0 16px; }
  .danger-box { border: 1.5px dashed #f3b4c0; background: #fff5f7; border-radius: 12px; padding: 12px 14px; font-size: 13px; color: #9f3140; }
</style>
</head>
<body class="auth-body">
  <div class="auth-wrap" style="max-width:460px">
    <div class="auth-card">
      <div class="auth-logo">¥</div>

      <?php if ($done): ?>
        <h1>安装完成 🎉</h1>
        <p class="auth-sub"><?= e($installedInfo['app']) ?> 已就绪</p>
        <div class="alert alert-ok">
          <?php if ($installedInfo['admin_created']): ?>
            管理员 <b><?= e($installedInfo['admin_user']) ?></b> 已创建，可用它登录管理后台。
          <?php else: ?>
            数据库中已存在管理员，未重复创建，请使用原管理员账号登录后台。
          <?php endif; ?>
        </div>
        <div style="display:flex;flex-direction:column;gap:10px;margin-top:14px">
          <a class="btn btn-block" href="index.php">进入前台首页</a>
          <a class="btn btn-ghost btn-block" href="admin/login.php">登录管理后台</a>
        </div>
        <p class="auth-alt" style="color:#b91c1c">⚠ 强烈建议删除 install.php，避免被他人重复安装</p>

      <?php elseif ($installed && $_SERVER['REQUEST_METHOD'] !== 'POST'): ?>
        <h1>已经安装过了</h1>
        <p class="auth-sub">检测到 config.php 存在且数据库连接正常，无需重复安装。</p>
        <div style="display:flex;flex-direction:column;gap:10px;margin-top:14px">
          <a class="btn btn-block" href="index.php">进入前台首页</a>
          <a class="btn btn-ghost btn-block" href="admin/login.php">登录管理后台</a>
        </div>
        <details style="margin-top:16px">
          <summary style="cursor:pointer;font-size:13px;color:var(--muted)">需要强制重新安装？（清空数据）</summary>
          <form method="post" style="margin-top:12px">
            <?= csrf_field() ?>
            <input type="hidden" name="do" value="install">
            <div class="danger-box">
              勾选表示你了解：<b>下方表单所填数据库中的同名表数据将被全部清空</b>，并重新写入配置文件。
            </div>
            <label class="field" style="margin-top:10px;flex-direction:row;align-items:center;gap:8px">
              <input type="checkbox" name="force" value="1"> 我确认要强制重新安装
            </label>
            <button class="btn btn-danger-ghost btn-block" type="submit" style="margin-top:12px">继续（仍需填写完整表单）</button>
          </form>
        </details>

      <?php else: ?>
        <h1>安装 账号收支</h1>
        <p class="step-tip">第 1 步：确认环境 → 第 2 步：填写数据库与管理员信息 → 自动完成建库、建表、生成配置</p>

        <?php if ($error): ?><div class="alert alert-error" style="margin-bottom:12px"><?= e($error) ?></div><?php endif; ?>

        <div style="display:flex;flex-direction:column;gap:6px;margin-bottom:16px">
          <?php foreach ($checks as $label => $ok): ?>
            <div class="chk <?= $ok ? 'ok' : 'bad' ?>"><?= $ok ? '✓' : '✗' ?> <?= e($label) ?></div>
          <?php endforeach; ?>
        </div>

        <?php if ($installed): ?>
          <div class="danger-box" style="margin-bottom:14px">
            系统已安装过。重新安装将<b>清空下方所填数据库中的全部数据</b>并覆盖配置文件。
          </div>
          <form method="post" style="margin-bottom:14px">
            <?= csrf_field() ?>
            <label class="field" style="flex-direction:row;align-items:center;gap:8px">
              <input type="checkbox" name="force" value="1"> 我确认强制重新安装（清空数据）
            </label>
            <button class="btn btn-danger-ghost btn-block" type="submit" style="margin-top:10px">同意并继续填写安装信息</button>
          </form>
        <?php endif; ?>

        <form method="post" novalidate>
          <?= csrf_field() ?>
          <input type="hidden" name="do" value="install">
          <?php if ($installed): ?><input type="hidden" name="force" value="1"><?php endif; ?>

          <label class="field">站点名称
            <input class="input" name="appname" value="账号收支" maxlength="30">
          </label>

          <p class="step-tip" style="margin-top:14px;font-weight:700;color:var(--text)">数据库（MySQL）</p>
          <label class="field">数据库地址
            <input class="input" name="host" value="127.0.0.1" required>
          </label>
          <label class="field">端口
            <input class="input" name="port" value="3306" required>
          </label>
          <label class="field">数据库名（不存在会自动创建）
            <input class="input" name="name" value="" required placeholder="如：zhanghu">
          </label>
          <label class="field">数据库用户名
            <input class="input" name="dbuser" value="" required autocomplete="off">
          </label>
          <label class="field">数据库密码
            <input class="input" type="password" name="dbpass" autocomplete="new-password">
          </label>

          <p class="step-tip" style="margin-top:14px;font-weight:700;color:var(--text)">管理后台</p>
          <label class="field">管理员用户名
            <input class="input" name="adminuser" value="" required placeholder="3-30 位字母/数字/下划线" autocomplete="off">
          </label>
          <label class="field">管理员密码
            <input class="input" type="password" name="adminpass" maxlength="64" required placeholder="至少 8 位" autocomplete="new-password">
          </label>
          <label class="field">确认管理员密码
            <input class="input" type="password" name="adminconfirm" maxlength="64" autocomplete="new-password">
          </label>

          <button class="btn btn-block" type="submit" style="margin-top:16px" <?= $envOk ? '' : 'disabled' ?>>开始安装</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
