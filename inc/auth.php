<?php

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function current_user(): ?array
{
    static $user = false;
    if ($user === false) {
        $user = null;
        if (!empty($_SESSION['uid'])) {
            $st = db()->prepare('SELECT id, username, created_at FROM users WHERE id = ?');
            $st->execute([(int)$_SESSION['uid']]);
            $row = $st->fetch();
            $user = $row ?: null;
            if (!$user) {
                unset($_SESSION['uid']);
            }
        }
    }
    return $user;
}

function require_login(): array
{
    $u = current_user();
    if (!$u) {
        redirect('login.php');
    }
    return $u;
}

function attempt_login(string $username, string $password): string
{
    if ($username === '' || $password === '') {
        return '请输入用户名和密码';
    }
    $st = db()->prepare('SELECT id, password_hash FROM users WHERE username = ?');
    $st->execute([$username]);
    $u = $st->fetch();
    if (!$u || !password_verify($password, $u['password_hash'])) {
        return '用户名或密码错误';
    }
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id'];
    return '';
}

/** 注册成功自动登录并返回空字符串，失败返回错误信息 */
function attempt_register(string $username, string $password): string
{
    if (!preg_match('/^[\x{4e00}-\x{9fa5}A-Za-z0-9_]{2,20}$/u', $username)) {
        return '用户名需为 2-20 位中文、字母、数字或下划线';
    }
    if (strlen($password) < 6 || strlen($password) > 64) {
        return '密码长度需为 6-64 位';
    }

    $pdo = db();
    $date = date('Y-m-d');
    $ip = client_ip();

    $pdo->beginTransaction();
    try {
        // 原子占位一个注册名额：并发时第二个请求会等待行锁，事务提交后再读到的 cnt 才可信
        $st = $pdo->prepare('INSERT INTO reg_log (ip, reg_date, cnt) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE cnt = cnt + 1');
        $st->execute([$ip, $date]);

        $st = $pdo->prepare('SELECT cnt FROM reg_log WHERE ip = ? AND reg_date = ?');
        $st->execute([$ip, $date]);
        $cnt = (int)$st->fetchColumn();

        if ($cnt > DAILY_REG_LIMIT) {
            $pdo->rollBack();
            return '每个IP每天最多注册 ' . DAILY_REG_LIMIT . ' 个账号，今日已达上限';
        }

        $st = $pdo->prepare('SELECT id FROM users WHERE username = ?');
        $st->execute([$username]);
        if ($st->fetch()) {
            $pdo->rollBack();
            return '该用户名已被注册';
        }

        $st = $pdo->prepare('INSERT INTO users (username, password_hash, reg_ip) VALUES (?, ?, ?)');
        $st->execute([$username, password_hash($password, PASSWORD_DEFAULT), $ip]);
        $uid = (int)$pdo->lastInsertId();

        $pdo->commit();

        session_regenerate_id(true);
        $_SESSION['uid'] = $uid;
        return '';
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
