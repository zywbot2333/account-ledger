<?php
require __DIR__ . '/init.php';
$user = require_login();

$id = (int)($_GET['id'] ?? 0);
$st = db()->prepare("SELECT a.id, a.name, a.category_id, a.tags, a.created_at, c.name AS category_name
  FROM accounts a
  LEFT JOIN categories c ON c.id = a.category_id
  WHERE a.id = ? AND a.user_id = ?");
$st->execute([$id, $user['id']]);
$acc = $st->fetch();
if (!$acc) {
    flash('error', '账号不存在或已被删除');
    redirect('index.php');
}

// 我的分类（供设置卡下拉）
$st = db()->prepare('SELECT id, name FROM categories WHERE user_id = ? ORDER BY id ASC');
$st->execute([$user['id']]);
$categories = $st->fetchAll();

// 该账号小计
$st = db()->prepare("SELECT COALESCE(SUM(CASE WHEN type = 1 THEN amount END), 0) AS income,
    COALESCE(SUM(CASE WHEN type = 2 THEN amount END), 0) AS expense
  FROM transactions WHERE account_id = ? AND user_id = ?");
$st->execute([$id, $user['id']]);
$sum = $st->fetch();
$income = (float)$sum['income'];
$expense = (float)$sum['expense'];
$balance = $income - $expense;

// 流水分页
$st = db()->prepare('SELECT COUNT(*) FROM transactions WHERE account_id = ? AND user_id = ?');
$st->execute([$id, $user['id']]);
$total = (int)$st->fetchColumn();
$pages = max(1, (int)ceil($total / TX_PER_PAGE));
$page = max(1, (int)($_GET['page'] ?? 1));
if ($page > $pages) {
    $page = $pages;
}

$st = db()->prepare('SELECT id, type, amount, occurred_at
  FROM transactions WHERE account_id = ? AND user_id = ?
  ORDER BY occurred_at DESC, id DESC LIMIT ? OFFSET ?');
$st->bindValue(1, $id, PDO::PARAM_INT);
$st->bindValue(2, $user['id'], PDO::PARAM_INT);
$st->bindValue(3, TX_PER_PAGE, PDO::PARAM_INT);
$st->bindValue(4, ($page - 1) * TX_PER_PAGE, PDO::PARAM_INT);
$st->execute();
$txs = $st->fetchAll();

$h = avatar_hue($acc['name']);
$accTags = parse_tags($acc['tags']);
$pageTitle = $acc['name'];
$active = 'index';
require __DIR__ . '/inc/header.php';
?>

<p class="breadcrumb"><a class="link" href="index.php"><?= icon('arr-l') ?>返回总览</a></p>

<section class="hero">
  <div class="hero-main">
    <div class="hero-acc-head">
      <span class="hero-ava" style="--h: <?= $h ?>"><?= e(mb_substr($acc['name'], 0, 1)) ?></span>
      <div class="hero-acc-title">
        <b><?= e($acc['name']) ?></b>
        <i><?= $acc['category_name'] !== null ? e($acc['category_name']) : '未分类' ?></i>
      </div>
    </div>
    <?php if ($accTags): ?>
    <div class="hero-chips" style="margin-top:12px">
      <?php foreach ($accTags as $tg): ?>
        <span class="chip chip-plain"><?= e($tg) ?></span>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div class="hero-label" style="margin-top:14px">当前结余</div>
    <div class="hero-value" style="color: <?= $balance >= 0 ? '#6ee7b7' : '#fda4af' ?>">¥<?= money($balance) ?></div>
    <div class="hero-chips">
      <span class="chip chip-in"><?= icon('in') ?>收入 ¥<?= money($income) ?></span>
      <span class="chip chip-out"><?= icon('out') ?>支出 ¥<?= money($expense) ?></span>
      <span class="chip chip-plain">共 <?= $total ?> 笔</span>
    </div>
  </div>
  <div class="hero-side">
    <form method="post" action="action.php" data-confirm="删除账号「<?= e($acc['name']) ?>」将同时删除其全部收支记录，确定删除？">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="delete_account">
      <input type="hidden" name="id" value="<?= (int)$acc['id'] ?>">
      <button class="btn btn-danger-ghost" type="submit"><?= icon('trash') ?>删除账号</button>
    </form>
  </div>
</section>

<section class="card form-card">
  <h2><?= icon('plus') ?>记一笔</h2>
  <form method="post" action="action.php" class="tx-form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add_transaction">
    <input type="hidden" name="account_id" value="<?= (int)$acc['id'] ?>">
    <div class="seg">
      <label><input type="radio" name="type" value="out" checked><span class="seg-out"><?= icon('out') ?>支出</span></label>
      <label><input type="radio" name="type" value="in"><span class="seg-in"><?= icon('in') ?>收入</span></label>
    </div>
    <div class="tx-grid">
      <label class="field">金额（元）
        <input class="input" type="number" name="amount" step="0.01" min="0.01" max="99999999.99" required placeholder="0.00">
      </label>
      <label class="field">日期
        <input class="input" type="date" name="date" value="<?= e(today()) ?>" required>
      </label>
    </div>
    <button class="btn" type="submit">保存记录</button>
  </form>
</section>

<section class="card form-card">
  <h2><?= icon('balance') ?>账号设置</h2>
  <form method="post" action="action.php" class="props-form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_account_props">
    <input type="hidden" name="account_id" value="<?= (int)$acc['id'] ?>">
    <div class="props-grid">
      <label class="field">分类
        <select class="input" name="category_id" onchange="this.form.submit()">
          <option value="">未分类</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= (int)$acc['category_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <div class="field">标签（点选即保存，卡片上会显示）
        <div class="tag-checks">
          <?php foreach (account_tags() as $tg): $on = in_array($tg, $accTags, true); ?>
          <label class="tag-check <?= $on ? 'on ' . tag_class($tg) : '' ?>">
            <input type="checkbox" name="tags[]" value="<?= e($tg) ?>" <?= $on ? 'checked' : '' ?> onchange="this.form.submit()">
            <span><?= e($tg) ?></span>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <noscript><button class="btn btn-sm" type="submit">保存设置</button></noscript>
  </form>
</section>

<section class="section-head">
  <h2>收支流水 <span class="count-pill"><?= $total ?></span></h2>
</section>

<section class="rows">
  <?php if (!$txs): ?>
  <div class="empty">
    <div class="empty-ico"><?= icon('note') ?></div>
    <b>该账号还没有收支记录</b>
    <i>在上面记第一笔吧</i>
  </div>
  <?php else: ?>
  <?php foreach ($txs as $t):
      $isIn = (int)$t['type'] === 1;
      $ts = strtotime($t['occurred_at']); ?>
  <div class="tx-row">
    <span class="tx-date"><b><?= date('j', $ts) ?></b><i><?= date('n月', $ts) ?></i></span>
    <span class="tx-info">
      <b class="<?= $isIn ? 'tag-in' : 'tag-out' ?>"><?= $isIn ? '收入' : '支出' ?></b>
    </span>
    <span class="tx-amt <?= $isIn ? 'c-up' : 'c-down' ?>"><?= $isIn ? '+' : '−' ?><?= money($t['amount']) ?></span>
    <form method="post" action="action.php" data-confirm="确定删除这笔记录？" class="tx-del">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="delete_transaction">
      <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
      <button class="row-del" type="submit" title="删除记录"><?= icon('trash') ?></button>
    </form>
  </div>
  <?php endforeach; ?>

  <?php if ($pages > 1): ?>
  <div class="pager">
    <?php if ($page > 1): ?><a class="link" href="account.php?id=<?= $id ?>&page=<?= $page - 1 ?>">← 上一页</a><?php endif; ?>
    <span class="muted" style="font-size:12.5px">第 <?= $page ?> / <?= $pages ?> 页 · 共 <?= $total ?> 笔</span>
    <?php if ($page < $pages): ?><a class="link" href="account.php?id=<?= $id ?>&page=<?= $page + 1 ?>">下一页 →</a><?php endif; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/inc/footer.php'; ?>
