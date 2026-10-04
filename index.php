<?php
require __DIR__ . '/init.php';
$user = require_login();

// 分类筛选（0/空 = 全部）
$catFilter = (int)($_GET['cat'] ?? 0);

// 全部账号的总收支（不受筛选影响）
$st = db()->prepare("SELECT
    COALESCE(SUM(CASE WHEN type = 1 THEN amount END), 0) AS income,
    COALESCE(SUM(CASE WHEN type = 2 THEN amount END), 0) AS expense
  FROM transactions WHERE user_id = ?");
$st->execute([$user['id']]);
$total = $st->fetch();
$income = (float)$total['income'];
$expense = (float)$total['expense'];
$balance = $income - $expense;

// 我的分类（含各分类账号数）
$st = db()->prepare("SELECT c.id, c.name,
      (SELECT COUNT(*) FROM accounts a WHERE a.category_id = c.id) AS acc_cnt
    FROM categories c
    WHERE c.user_id = ?
    ORDER BY c.id ASC");
$st->execute([$user['id']]);
$categories = $st->fetchAll();

// 账号列表（含小计与分类/标签）
$st = db()->prepare("SELECT a.id, a.name, a.category_id, a.tags,
      c.name AS category_name,
      COALESCE(SUM(CASE WHEN t.type = 1 THEN t.amount END), 0) AS income,
      COALESCE(SUM(CASE WHEN t.type = 2 THEN t.amount END), 0) AS expense,
      COUNT(t.id) AS tx_count
    FROM accounts a
    LEFT JOIN categories c ON c.id = a.category_id
    LEFT JOIN transactions t ON t.account_id = a.id
    WHERE a.user_id = ?" . ($catFilter > 0 ? " AND a.category_id = ?" : "") . "
    GROUP BY a.id
    ORDER BY a.id DESC");
if ($catFilter > 0) {
    $st->execute([$user['id'], $catFilter]);
} else {
    $st->execute([$user['id']]);
}
$accounts = $st->fetchAll();

// 最近流水
$st = db()->prepare("SELECT t.id, t.type, t.amount, t.occurred_at,
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
    <div class="hero-label"><?= icon('wallet') ?>总结余</div>
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

<?php if ($categories): ?>
<section class="filter-chips">
  <a class="f-chip <?= $catFilter === 0 ? 'active' : '' ?>" href="index.php">全部</a>
  <?php foreach ($categories as $c): ?>
    <a class="f-chip <?= $catFilter === (int)$c['id'] ? 'active' : '' ?>" href="index.php?cat=<?= (int)$c['id'] ?>"><?= e($c['name']) ?> <i><?= (int)$c['acc_cnt'] ?></i></a>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<section class="section-head">
  <h2>我的账号 <span class="count-pill"><?= count($accounts) ?></span></h2>
  <details class="cat-manager">
    <summary><?= icon('balance') ?>管理分类<?= $categories ? '' : '（还没有分类，点这里添加）' ?></summary>
    <div class="cat-panel">
      <?php foreach ($categories as $c): ?>
      <div class="cat-row">
        <span class="cat-name"><?= e($c['name']) ?></span>
        <span class="mini-note"><?= (int)$c['acc_cnt'] ?> 个账号</span>
        <form method="post" action="action.php" class="inline-form"
              onsubmit="var p=prompt('将分类「<?= e($c['name']) ?>」重命名为：','<?= e($c['name']) ?>');if(!p||p==='<?= e($c['name']) ?>')return false;this.elements.newname.value=p;">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="rename_category">
          <input type="hidden" name="cid" value="<?= (int)$c['id'] ?>">
          <input type="hidden" name="newname" value="">
          <button class="btn btn-xs btn-ghost" type="submit">重命名</button>
        </form>
        <form method="post" action="action.php" data-confirm="删除分类「<?= e($c['name']) ?>」？该分类下的账号将变为未分类（账号不受影响）。">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete_category">
          <input type="hidden" name="cid" value="<?= (int)$c['id'] ?>">
          <button class="btn btn-xs btn-danger-ghost" type="submit">删除</button>
        </form>
      </div>
      <?php endforeach; ?>
      <form method="post" action="action.php" class="cat-add">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_category">
        <input class="input" name="name" maxlength="20" required placeholder="新分类名称（如：常用、长期）">
        <button class="btn btn-sm" type="submit"><?= icon('plus') ?>添加分类</button>
      </form>
    </div>
  </details>
</section>

<section class="grid">
  <?php foreach ($accounts as $a):
      $bal = (float)$a['income'] - (float)$a['expense'];
      $h = avatar_hue($a['name']);
      $tags = parse_tags($a['tags']); ?>
  <div class="acc-card">
    <a class="card-link" href="account.php?id=<?= (int)$a['id'] ?>" aria-label="<?= e($a['name']) ?>"></a>
    <div class="acc-top">
      <span class="acc-ava" style="--h: <?= $h ?>"><?= e(mb_substr($a['name'], 0, 1)) ?></span>
      <div class="acc-id">
        <b><?= e($a['name']) ?></b>
        <i><?= $a['category_name'] !== null ? e($a['category_name']) : '未分类' ?></i>
      </div>
      <form method="post" action="action.php" data-confirm="删除账号「<?= e($a['name']) ?>」将同时删除其全部收支记录，确定删除？">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete_account">
        <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
        <button class="row-del" type="submit" title="删除账号"><?= icon('trash') ?></button>
      </form>
    </div>
    <?php if ($tags): ?>
    <div class="ac-chips">
      <?php foreach ($tags as $tg): ?>
        <span class="chip-tag <?= tag_class($tg) ?>"><?= e($tg) ?></span>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
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
      <?php if ($categories): ?>
      <label class="field">分类（可选）
        <select class="input" name="category_id">
          <option value="">未分类</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <?php endif; ?>
      <button class="btn" type="submit"><?= icon('plus') ?>保存</button>
    </form>
  </details>
</section>

<?php if (!$accounts && $catFilter > 0): ?>
<section class="rows" style="margin-top:16px">
  <div class="empty"><div class="empty-ico"><?= icon('wallet') ?></div><b>该分类下还没有账号</b><i><a class="link" href="index.php">查看全部账号</a></i></div>
</section>
<?php endif; ?>

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
      <b class="<?= $isIn ? 'tag-in' : 'tag-out' ?>"><?= $isIn ? '收入' : '支出' ?></b>
      <i><?= e($t['account_name']) ?></i>
    </span>
    <span class="tx-amt <?= $isIn ? 'c-up' : 'c-down' ?>"><?= $isIn ? '+' : '−' ?><?= money($t['amount']) ?></span>
    <span></span>
  </a>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<?php require __DIR__ . '/inc/footer.php'; ?>
