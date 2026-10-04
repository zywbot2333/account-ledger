<?php
require __DIR__ . '/inc/admin-auth.php';
$admin = admin_require();

$today = date('Y-m-d');

// 总量统计
$userTotal = (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
$accTotal = (int)db()->query('SELECT COUNT(*) FROM accounts')->fetchColumn();
$txTotal = (int)db()->query('SELECT COUNT(*) FROM transactions')->fetchColumn();

// 今日注册
$st = db()->prepare("SELECT COUNT(*) FROM users WHERE created_at >= ?");
$st->execute([$today . ' 00:00:00']);
$userToday = (int)$st->fetchColumn();

$st = db()->prepare("SELECT COALESCE(SUM(cnt),0), COUNT(*) FROM reg_log WHERE reg_date = ?");
$st->execute([$today]);
[$regToday, $ipToday] = $st->fetch(PDO::FETCH_NUM);
$regToday = (int)$regToday;
$ipToday = (int)$ipToday;

// 最近注册的用户
$recentUsers = db()->query('SELECT id, username, reg_ip, created_at FROM users ORDER BY id DESC LIMIT 10')->fetchAll();

// 注册限制记录
$regLogs = db()->query('SELECT ip, reg_date, cnt FROM reg_log ORDER BY reg_date DESC, id DESC LIMIT 10')->fetchAll();

$adminTitle = '数据概览';
$active = 'index';
require __DIR__ . '/inc/header.php';
?>

<section class="admin-stats">
  <div class="stat-card">
    <span class="stat-ico ico-bal"><?= icon('grid') ?></span>
    <div><div class="stat-label">用户总数</div><div class="stat-value"><?= $userTotal ?></div></div>
  </div>
  <div class="stat-card">
    <span class="stat-ico ico-up"><?= icon('plus') ?></span>
    <div>
      <div class="stat-label">今日新增用户</div>
      <div class="stat-value"><?= $userToday ?></div>
      <span class="mini-note">今日 <?= $ipToday ?> 个 IP 注册，共 <?= $regToday ?> 次</span>
    </div>
  </div>
  <div class="stat-card">
    <span class="stat-ico ico-cyan"><?= icon('wallet') ?></span>
    <div><div class="stat-label">收支账号总数</div><div class="stat-value"><?= $accTotal ?></div></div>
  </div>
  <div class="stat-card">
    <span class="stat-ico ico-amber"><?= icon('note') ?></span>
    <div><div class="stat-label">收支流水总数</div><div class="stat-value"><?= $txTotal ?></div></div>
  </div>
</section>

<div class="admin-grid2">
  <section class="detail-card">
    <div class="chart-head" style="padding:14px 18px 2px">
      <span class="chart-title"><?= icon('grid') ?>最近注册用户</span>
      <a class="link" href="users.php">全部用户 →</a>
    </div>
    <?php if (!$recentUsers): ?>
      <div class="empty"><div class="empty-ico"><?= icon('grid') ?></div><b>还没有用户注册</b></div>
    <?php else: ?>
    <table class="table">
      <thead><tr><th>ID</th><th>用户名</th><th>注册IP</th><th>注册时间</th></tr></thead>
      <tbody>
      <?php foreach ($recentUsers as $u): ?>
        <tr>
          <td class="muted"><?= (int)$u['id'] ?></td>
          <td><b><?= e($u['username']) ?></b></td>
          <td><span class="tag"><?= e($u['reg_ip']) ?></span></td>
          <td class="muted"><?= e($u['created_at']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </section>

  <section class="detail-card">
    <div class="chart-head" style="padding:14px 18px 2px">
      <span class="chart-title"><?= icon('balance') ?>注册限制记录（每 IP 每日 <?= (int)DAILY_REG_LIMIT ?> 个）</span>
    </div>
    <?php if (!$regLogs): ?>
      <div class="empty"><div class="empty-ico"><?= icon('balance') ?></div><b>暂无注册记录</b><i>用户注册后会按 IP + 日期统计</i></div>
    <?php else: ?>
    <table class="table">
      <thead><tr><th>IP</th><th>日期</th><th class="num">已注册</th><th class="num">状态</th></tr></thead>
      <tbody>
      <?php foreach ($regLogs as $r): $full = (int)$r['cnt'] >= DAILY_REG_LIMIT; ?>
        <tr>
          <td><span class="tag"><?= e($r['ip']) ?></span></td>
          <td class="muted"><?= e($r['reg_date']) ?></td>
          <td class="num"><?= (int)$r['cnt'] ?></td>
          <td class="num"><span class="tag <?= $full ? 'tag-danger' : '' ?>"><?= $full ? '已达上限' : '正常' ?></span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </section>
</div>

<?php require __DIR__ . '/inc/footer.php'; ?>
