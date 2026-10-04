<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Shanghai');

if (!defined('APP_VERSION')) {
    define('APP_VERSION', '2.0.1');
}

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'path' => '/']);
session_start();

// 未安装时引导到安装程序
if (!is_file(__DIR__ . '/config.php')) {
    header('Location: install.php');
    exit;
}

require __DIR__ . '/config.php';
require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/helpers.php';
require __DIR__ . '/inc/auth.php';

ensure_schema();
