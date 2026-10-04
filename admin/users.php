<?php
require __DIR__ . '/inc/admin-auth.php';
$admin = admin_require();

$q = post('q', $_GET['q'] ?? '');
$q = mb_substr($q, 0, 50);
$like = '%' . $q . '%';

// 总数（带搜索条件）
$st = db()->prepare("SELECT COUNT(*) FROM users WHERE ? = '' OR username LIKE ? OR reg_ip LIKE ?");
$st->execute([$q, $like, $like]);
$total = (int)$st->fetchColumn();
$pages = max(1, (int)ceil($total / 20));
$page = max(1, (int)($_GET['page'] ?? 1));
if ($page > $pages) {
    $page = $pages;
}

// 用户列表（含各用户的账号数、流水数、总收支）
$st = db()->prepare("SELECT u.id, u.username, u.reg_ip, u.created_at,
      (SELECT COUNT(*) FROM accounts a WHERE a.user_id = u.id) AS acc_cnt,
      (SELECT COUNT(*) FROM transactions t WHERE t.user_id = u.id) AS tx_cnt,
      (SELECT COALESCE(SUM(t.amount), 0) FROM transactions t WHERE t.user_id = u.id AND t.type = 1) AS income,
      (SELECT COALESCE(SUM(t.amount), 0) FROM transactions t WHERE t.user_id = u.id AND t.type = 2) AS expense
    FROM users u
    WHERE ? = '' OR u.username LIKE ? OR u.reg_ip LIKE ?
    ORDER BY u.id DESC
    LIMIT 20 OFFSET ?");
$st->execute([$q, $like, $like, ($page - 1) * 20]);
$users = $st->fetchAll();

$adminTitle = '用户管理';
$active = 'users';
require __DIR__ . '/inc/header.php';
?>

<section class="section-head" style="margin-top:0">
  <h2>用户管理 <span class="count-pill"><?= $total ?></span></h2>
</section>

<form class="search-bar" method="get" action="users.php">
  <input class="input" name="q" value="<?= e($q) ?>" placeholder="按用户名或注册 IP 搜索">
  <button class="btn btn-sm" type="submit">搜索</button>
  <?php if ($q !== ''): ?><a class="link" href="users.php">清除</a><?php endif; ?>
</form>

<section class="detail-card">
  <?php if (!$users): ?>
  <div class="empty">
    <div class="empty-ico"><?= icon('note') ?></div>
    <b><?= $q !== '' ? '没有匹配的用户' : '还没有用户注册' ?></b>
  </div>
  <?php else: ?>
  <table class="table">
    <thead>
      <tr>
        <th>ID</th><th>用户名</th><th>注册IP</th><th>注册时间</th>
        <th class="num">账号</th><th class="num">流水</th>
        <th class="num">总收入</th><th class="num">总支出</th><th class="num">操作</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($users as $u): ?>
      <tr>
        <td class="muted"><?= (int)$u['id'] ?></td>
        <td><b><?= e($u['username']) ?></b></td>
        <td><span class="tag"><?= e($u['reg_ip']) ?></span></td>
        <td class="muted"><?= e($u['created_at']) ?></td>
        <td class="num"><?= (int)$u['acc_cnt'] ?></td>
        <td class="num"><?= (int)$u['tx_cnt'] ?></td>
        <td class="num c-up">+<?= money($u['income']) ?></td>
        <td class="num c-down">-<?= money($u['expense']) ?></td>
        <td class="num" style="white-space:nowrap">
          <form method="post" action="action.php" class="inline-form"
                onsubmit="var p=prompt('为用户「<?= e($u['username']) ?>」设置新密码（至少 6 位）：');if(!p)return false;this.elements.pw.value=p;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="uid" value="<?= (int)$u['id'] ?>">
            <input type="hidden" name="pw" value="">
            <button class="btn btn-xs btn-ghost" type="submit">重置密码</button>
          </form>
          <form method="post" action="action.php" class="inline-form"
                data-confirm="确定删除用户「<?= e($u['username']) ?>」？\n将同时删除其全部账号与收支流水，且不可恢复！">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete_user">
            <input type="hidden" name="uid" value="<?= (int)$u['id'] ?>">
            <button class="btn btn-xs btn-danger-ghost" type="submit">删除</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php if ($pages > 1): ?>
  <div class="pager">
    <?php if ($page > 1): ?><a class="link" href="users.php?q=<?= urlencode($q) ?>&page=<?= $page - 1 ?>">← 上一页</a><?php endif; ?>
    <span class="muted" style="font-size:12.5px">第 <?= $page ?> / <?= $pages ?> 页 · 共 <?= $total ?> 位用户</span>
    <?php if ($page < $pages): ?><a class="link" href="users.php?q=<?= urlencode($q) ?>&page=<?= $page + 1 ?>">下一页 →</a><?php endif; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/inc/footer.php'; ?>
