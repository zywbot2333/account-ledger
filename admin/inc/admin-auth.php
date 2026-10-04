<?php
// 管理后台公共鉴权（引入 init.php 后使用）
require __DIR__ . '/../../init.php';

function admin_user(): ?array
{
    static $admin = false;
    if ($admin === false) {
        $admin = null;
        if (!empty($_SESSION['aid'])) {
            $st = db()->prepare('SELECT id, username FROM admins WHERE id = ?');
            $st->execute([(int)$_SESSION['aid']]);
            $row = $st->fetch();
            $admin = $row ?: null;
            if (!$admin) {
                unset($_SESSION['aid']);
            }
        }
    }
    return $admin;
}

function admin_require(): array
{
    $a = admin_user();
    if (!$a) {
        redirect('login.php');
    }
    return $a;
}

function admins_count(): int
{
    return (int)db()->query('SELECT COUNT(*) FROM admins')->fetchColumn();
}
