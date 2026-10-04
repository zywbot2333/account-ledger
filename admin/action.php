<?php
require __DIR__ . '/inc/admin-auth.php';
$admin = admin_require();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_ok()) {
    flash('error', '请求无效或已过期，请重试');
    redirect('users.php');
}

$action = post('action');
$pdo = db();

try {
    switch ($action) {
        case 'delete_user': {
            $uid = (int)post('uid');
            $st = $pdo->prepare('SELECT username FROM users WHERE id = ?');
            $st->execute([$uid]);
            $u = $st->fetch();
            if (!$u) {
                flash('error', '用户不存在');
                redirect('users.php');
            }
            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM transactions WHERE user_id = ?')->execute([$uid]);
            $pdo->prepare('DELETE FROM accounts WHERE user_id = ?')->execute([$uid]);
            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$uid]);
            $pdo->commit();
            flash('ok', '用户「' . $u['username'] . '」及其全部数据已删除');
            redirect('users.php');
        }

        case 'reset_password': {
            $uid = (int)post('uid');
            $pw = post('pw');
            if (strlen($pw) < 6 || strlen($pw) > 64) {
                flash('error', '新密码长度需为 6-64 位');
                redirect('users.php');
            }
            $st = $pdo->prepare('SELECT id FROM users WHERE id = ?');
            $st->execute([$uid]);
            if (!$st->fetch()) {
                flash('error', '用户不存在');
                redirect('users.php');
            }
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($pw, PASSWORD_DEFAULT), $uid]);
            flash('ok', '该用户的密码已重置');
            redirect('users.php');
        }

        default:
            redirect('index.php');
    }
} catch (PDOException $e) {
    if (APP_DEBUG) {
        throw $e;
    }
    flash('error', '操作失败，请重试');
    redirect('users.php');
}
