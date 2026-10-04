<?php
require __DIR__ . '/init.php';
$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_ok()) {
    flash('error', '请求无效或已过期，请重试');
    redirect('index.php');
}

$action = post('action');
$pdo = db();

try {
    run_action($action, $pdo, $user);
} catch (PDOException $e) {
    // 常见于客户端提交了非 UTF-8 编码内容，给友好提示而不是错误页
    if (APP_DEBUG) {
        throw $e;
    }
    flash('error', '保存失败：内容可能包含无法识别的字符，请重试');
    redirect($_POST['account_id'] ? 'account.php?id=' . (int)$_POST['account_id'] : 'index.php');
}

function run_action(string $action, PDO $pdo, array $user): void
{
    switch ($action) {
        case 'add_account': {
            $name = post('name');
            if ($name === '' || mb_strlen($name) > 50) {
                flash('error', '请输入 50 字以内的账号名称');
                redirect('index.php');
            }
            // 分类可选，且必须属于当前用户
            $categoryId = post('category_id') !== '' ? (int)post('category_id') : null;
            if ($categoryId !== null) {
                $st = $pdo->prepare('SELECT id FROM categories WHERE id = ? AND user_id = ?');
                $st->execute([$categoryId, $user['id']]);
                if (!$st->fetch()) {
                    $categoryId = null;
                }
            }
            $st = $pdo->prepare('INSERT INTO accounts (user_id, name, category_id) VALUES (?, ?, ?)');
            $st->execute([$user['id'], $name, $categoryId]);
            flash('ok', '账号「' . $name . '」添加成功');
            redirect('index.php');
        }

        case 'delete_account': {
            $id = (int)post('id');
            $st = $pdo->prepare('SELECT id FROM accounts WHERE id = ? AND user_id = ?');
            $st->execute([$id, $user['id']]);
            if (!$st->fetch()) {
                flash('error', '账号不存在');
                redirect('index.php');
            }
            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM transactions WHERE account_id = ? AND user_id = ?')->execute([$id, $user['id']]);
            $pdo->prepare('DELETE FROM accounts WHERE id = ? AND user_id = ?')->execute([$id, $user['id']]);
            $pdo->commit();
            flash('ok', '账号及其全部流水已删除');
            redirect('index.php');
        }

        case 'add_transaction': {
            $aid = (int)post('account_id');
            $st = $pdo->prepare('SELECT id FROM accounts WHERE id = ? AND user_id = ?');
            $st->execute([$aid, $user['id']]);
            if (!$st->fetch()) {
                flash('error', '账号不存在');
                redirect('index.php');
            }
            $back = 'account.php?id=' . $aid;

            $type = post('type') === 'in' ? 1 : 2;
            $amount = post('amount');
            if (!preg_match('/^\d{1,8}(\.\d{1,2})?$/', $amount) || (float)$amount <= 0) {
                flash('error', '金额格式不正确（最多两位小数，且大于 0）');
                redirect($back);
            }
            $date = post('date');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
                || !checkdate((int)substr($date, 5, 2), (int)substr($date, 8, 2), (int)substr($date, 0, 4))) {
                flash('error', '日期格式不正确');
                redirect($back);
            }
            $st = $pdo->prepare('INSERT INTO transactions (user_id, account_id, type, amount, occurred_at) VALUES (?, ?, ?, ?, ?)');
            $st->execute([$user['id'], $aid, $type, $amount, $date]);
            flash('ok', ($type === 1 ? '收入 ¥' : '支出 ¥') . number_format((float)$amount, 2) . ' 已记录');
            redirect($back);
        }

        case 'delete_transaction': {
            $id = (int)post('id');
            $st = $pdo->prepare('SELECT account_id FROM transactions WHERE id = ? AND user_id = ?');
            $st->execute([$id, $user['id']]);
            $tx = $st->fetch();
            if (!$tx) {
                flash('error', '记录不存在');
                redirect('index.php');
            }
            $pdo->prepare('DELETE FROM transactions WHERE id = ? AND user_id = ?')->execute([$id, $user['id']]);
            flash('ok', '记录已删除');
            redirect('account.php?id=' . (int)$tx['account_id']);
        }

        // ===== v2.0 分类管理 =====

        case 'add_category': {
            $name = post('name');
            if ($name === '' || mb_strlen($name) > 20) {
                flash('error', '分类名称需为 1-20 个字');
                redirect('index.php');
            }
            $st = $pdo->prepare('SELECT id FROM categories WHERE user_id = ? AND name = ?');
            $st->execute([$user['id'], $name]);
            if ($st->fetch()) {
                flash('error', '分类「' . $name . '」已存在');
                redirect('index.php');
            }
            $pdo->prepare('INSERT INTO categories (user_id, name) VALUES (?, ?)')->execute([$user['id'], $name]);
            flash('ok', '分类「' . $name . '」已添加');
            redirect('index.php');
        }

        case 'rename_category': {
            $cid = (int)post('cid');
            $newname = post('newname');
            if ($newname === '' || mb_strlen($newname) > 20) {
                flash('error', '分类名称需为 1-20 个字');
                redirect('index.php');
            }
            $st = $pdo->prepare('SELECT id FROM categories WHERE id = ? AND user_id = ?');
            $st->execute([$cid, $user['id']]);
            if (!$st->fetch()) {
                flash('error', '分类不存在');
                redirect('index.php');
            }
            $st = $pdo->prepare('SELECT id FROM categories WHERE user_id = ? AND name = ? AND id != ?');
            $st->execute([$user['id'], $newname, $cid]);
            if ($st->fetch()) {
                flash('error', '分类「' . $newname . '」已存在');
                redirect('index.php');
            }
            $pdo->prepare('UPDATE categories SET name = ? WHERE id = ? AND user_id = ?')->execute([$newname, $cid, $user['id']]);
            flash('ok', '分类已重命名为「' . $newname . '」');
            redirect('index.php');
        }

        case 'delete_category': {
            $cid = (int)post('cid');
            $st = $pdo->prepare('SELECT name FROM categories WHERE id = ? AND user_id = ?');
            $st->execute([$cid, $user['id']]);
            $cat = $st->fetch();
            if (!$cat) {
                flash('error', '分类不存在');
                redirect('index.php');
            }
            $pdo->beginTransaction();
            // 该分类下的账号变为“未分类”，账号本身保留
            $pdo->prepare('UPDATE accounts SET category_id = NULL WHERE category_id = ? AND user_id = ?')->execute([$cid, $user['id']]);
            $pdo->prepare('DELETE FROM categories WHERE id = ? AND user_id = ?')->execute([$cid, $user['id']]);
            $pdo->commit();
            flash('ok', '分类「' . $cat['name'] . '」已删除，旗下账号已改为未分类');
            redirect('index.php');
        }

        // ===== v2.0 账号属性（分类 + 标签）=====

        case 'save_account_props': {
            $aid = (int)post('account_id');
            $st = $pdo->prepare('SELECT id FROM accounts WHERE id = ? AND user_id = ?');
            $st->execute([$aid, $user['id']]);
            if (!$st->fetch()) {
                flash('error', '账号不存在');
                redirect('index.php');
            }

            // 分类（可留空 = 未分类），必须属于当前用户
            $categoryId = post('category_id') !== '' ? (int)post('category_id') : null;
            if ($categoryId !== null) {
                $st = $pdo->prepare('SELECT id FROM categories WHERE id = ? AND user_id = ?');
                $st->execute([$categoryId, $user['id']]);
                if (!$st->fetch()) {
                    $categoryId = null;
                }
            }

            // 标签：仅接受固定集合内的值
            $posted = is_array($_POST['tags'] ?? null) ? $_POST['tags'] : [];
            $tags = array_values(array_intersect(account_tags(), array_map('strval', $posted)));

            $pdo->prepare('UPDATE accounts SET category_id = ?, tags = ? WHERE id = ? AND user_id = ?')
                ->execute([$categoryId, implode(',', $tags), $aid, $user['id']]);
            flash('ok', '账号属性已更新');
            redirect('account.php?id=' . $aid);
        }

        default:
            redirect('index.php');
    }
}
