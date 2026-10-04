<?php
require __DIR__ . '/init.php';
$user = require_login();

// 全部账号的总收支
$st = db()->prepare("SELECT
    COALESCE(SUM(CASE WHEN type = 1 THEN amount END), 0) AS income,
    COALESCE(SUM(CASE WHEN type = 2 THEN amount END), 0) AS expense
  FROM transactions WHERE user_id = ?");
$st->execute([$user['id']]);
$total = $st->fetch();
$income = (float)$total['income'];
$expense = (float)$total['expense'];
$balance = $income - $expense;

// 账号列表（含每个账号的小计）
$st = db()->prepare("SELECT a.id, a.name, a.remark,
      COALESCE(SUM(CASE WHEN t.type = 1 THEN t.amount END), 0) AS income,
      COALESCE(SUM(CASE WHEN t.type = 2 THEN t.amount END), 0) AS expense,
      COUNT(t.id) AS tx_count
    FROM accounts a
    LEFT JOIN transactions t ON t.account_id = a.id
    WHERE a.user_id = ?
    GROUP BY a.id
    ORDER BY a.id DESC");
$st->execute([$user['id']]);
$accounts = $st->fetchAll();

// 最近流水
$st = db()->prepare("SELECT t.id, t.type, t.amount, t.note, t.occurred_at,
      a.name AS account_name, a.id AS account_id
    FROM transactions t
    JOIN accounts a ON a.id = t.account_id
    WHERE t.user_id = ?
    ORDER BY t.occurred_at DESC, t.id DESC
    LIMIT 8");
$st->execute([$user['id']]);
$recent = $st->fetchAll();

$pageTitle = '总览';
$active = 'index';
require __DIR__ . '/inc/header.php';
?>

<section class="hero">
  <div class="hero-main">
    <div class="hero-label"><?= icon('wallet') ?>总结余 · 全部 <?= count($accounts) ?> 个账号</div>
    <div class="hero-value" style="color: <?= $balance >= 0 ? '#6ee7b7' : '#fda4af' ?>">¥<?= money($balance) ?></div>
    <div class="hero-chips">
      <span class="chip chip-in"><?= icon('in') ?>收入 ¥<?= money($income) ?></span>
      <span class="chip chip-out"><?= icon('out') ?>支出 ¥<?= money($expense) ?></span>
    </div>
  </div>
  <div class="hero-side">
    <a class="btn btn-light" href="reports.php">查看收支报表 <?= icon('chev-r') ?></a>
    <span class="hero-note"><?= e(today()) ?></span>
  </div>
</section>

<section class="section-head">
  <h2>我的账号 <span class="count-pill"><?= count($accounts) ?></span></h2>
</section>

<section class="grid">
  <?php foreach ($accounts as $a):
      $bal = (float)$a['income'] - (float)$a['expense'];
      $h = avatar_hue($a['name']); ?>
  <div class="acc-card">
    <a class="card-link" href="account.php?id=<?= (int)$a['id'] ?>" aria-label="<?= e($a['name']) ?>"></a>
    <div class="acc-top">
      <span class="acc-ava" style="--h: <?= $h ?>"><?= e(mb_substr($a['name'], 0, 1)) ?></span>
      <div class="acc-id">
        <b><?= e($a['name']) ?></b>
        <i><?= $a['remark'] !== '' ? e($a['remark']) : '暂无备注' ?></i>
      </div>
      <form method="post" action="action.php" data-confirm="删除账号「<?= e($a['name']) ?>」将同时删除其全部收支记录，确定删除？">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete_account">
        <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
        <button class="row-del" type="submit" title="删除账号"><?= icon('trash') ?></button>
      </form>
    </div>
    <div class="acc-balance <?= $bal >= 0 ? 'c-up' : 'c-down' ?>">¥<?= money($bal) ?></div>
    <div class="acc-mini">
      <span><span class="dot dot-in"></span>收入<b class="c-up">+<?= money($a['income']) ?></b></span>
      <span><span class="dot dot-out"></span>支出<b class="c-down">-<?= money($a['expense']) ?></b></span>
    </div>
    <div class="acc-foot">
      <span><?= (int)$a['tx_count'] ?> 笔记录</span>
      <?= icon('chev-r') ?>
    </div>
  </div>
  <?php endforeach; ?>

  <details class="acc-add">
    <summary><?= icon('plus') ?>添加账号</summary>
    <form method="post" action="action.php">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add_account">
      <label class="field">账号名称
        <input class="input" name="name" maxlength="50" required placeholder="如：微信钱包">
      </label>
      <label class="field">备注（可选）
        <input class="input" name="remark" maxlength="100" placeholder="用途说明">
      </label>
      <button class="btn" type="submit"><?= icon('plus') ?>保存</button>
    </form>
  </details>
</section>

<section class="section-head">
  <h2>最近流水</h2>
  <a class="link" href="reports.php">全部统计 →</a>
</section>

<?php if (!$recent): ?>
<section class="rows">
  <div class="empty">
    <div class="empty-ico"><?= icon('note') ?></div>
    <b>还没有收支记录</b>
    <i>点击上面的账号卡片，记下第一笔吧</i>
  </div>
</section>
<?php else: ?>
<section class="rows">
  <?php foreach ($recent as $t):
      $isIn = (int)$t['type'] === 1;
      $ts = strtotime($t['occurred_at']); ?>
  <a class="tx-row" href="account.php?id=<?= (int)$t['account_id'] ?>">
    <span class="tx-date"><b><?= date('j', $ts) ?></b><i><?= date('n月', $ts) ?></i></span>
    <span class="tx-info">
      <b><?= $t['note'] !== '' ? e($t['note']) : '无备注' ?></b>
      <i><?= e($t['account_name']) ?></i>
    </span>
    <span class="tx-amt <?= $isIn ? 'c-up' : 'c-down' ?>"><?= $isIn ? '+' : '−' ?><?= money($t['amount']) ?></span>
    <span></span>
  </a>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<?php require __DIR__ . '/inc/footer.php'; ?>
