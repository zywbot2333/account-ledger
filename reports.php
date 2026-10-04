<?php
require __DIR__ . '/init.php';
$user = require_login();

/** 计算某类报表、第 $o 期（0=本期，-1=上期…）的起止日期、分桶键与标签 */
function period_range(string $p, int $o): array
{
    $base = new DateTimeImmutable('today');
    $weekMap = ['日', '一', '二', '三', '四', '五', '六'];
    if ($p === 'day') {
        $anchor = $base->modify("{$o} days");
        return [$anchor->format('Y-m-d'), $anchor->format('Y-m-d'), [], [],
            $anchor->format('Y年m月d日') . ' · 周' . $weekMap[(int)$anchor->format('w')]];
    }
    if ($p === 'week') {
        $anchor = $base->modify(($o * 7) . ' days');
        $dow = (int)$anchor->format('N'); // 1=周一 … 7=周日
        $start = $anchor->modify('-' . ($dow - 1) . ' days');
        $end = $start->modify('+6 days');
        $keys = [];
        $labels = [];
        for ($i = 0; $i < 7; $i++) {
            $d = $start->modify("+{$i} days");
            $keys[] = $d->format('Y-m-d');
            $labels[] = $d->format('m-d');
        }
        $name = $start->format('Y-m-d') . ' ~ ' . $end->format('Y-m-d');
    } elseif ($p === 'month') {
        $anchor = $base->modify("{$o} months");
        $start = $anchor->modify('first day of this month');
        $end = $anchor->modify('last day of this month');
        $keys = [];
        $labels = [];
        $cur = $start;
        while ($cur <= $end) {
            $keys[] = $cur->format('Y-m-d');
            $labels[] = $cur->format('m-d');
            $cur = $cur->modify('+1 day');
        }
        $name = $anchor->format('Y年m月');
    } else {
        $y = (int)$base->modify("{$o} years")->format('Y');
        $start = new DateTimeImmutable("{$y}-01-01");
        $end = new DateTimeImmutable("{$y}-12-31");
        $keys = [];
        $labels = [];
        for ($m = 1; $m <= 12; $m++) {
            $keys[] = sprintf('%04d-%02d', $y, $m);
            $labels[] = $m . '月';
        }
        $name = $y . '年';
    }
    return [$start->format('Y-m-d'), $end->format('Y-m-d'), $keys, $labels, $name];
}

function delta_badge(float $cur, float $prev): array
{
    $diff = round($cur - $prev, 2);
    if ($prev > 0 && $diff != 0.0) {
        $pct = round(abs($diff) / $prev * 100);
        $txt = ($diff > 0 ? '+' : '-') . $pct . '%';
    } elseif ($diff == 0.0) {
        $txt = '持平';
    } else {
        $txt = ($diff > 0 ? '+' : '-') . money(abs($diff));
    }
    return [$txt, $diff > 0];
}

$p = $_GET['p'] ?? 'month';
if (!in_array($p, ['day', 'week', 'month', 'year'], true)) {
    $p = 'month';
}
// 只允许回看历史期间（最多 120 期），不允许浏览未来
$o = (int)($_GET['o'] ?? 0);
if ($o > 0 || $o < -120) {
    $o = 0;
}

[$start, $end, $keys, $labels, $name] = period_range($p, $o);
[$pStart, $pEnd] = array_slice(period_range($p, $o - 1), 0, 2);

$pdo = db();
$uid = $user['id'];

/** 某个日期区间内的收入/支出总额 */
$totalsOf = function (string $s, string $e) use ($pdo, $uid): array {
    $st = $pdo->prepare("SELECT
        COALESCE(SUM(CASE WHEN type = 1 THEN amount END), 0) AS income,
        COALESCE(SUM(CASE WHEN type = 2 THEN amount END), 0) AS expense
      FROM transactions WHERE user_id = ? AND occurred_at BETWEEN ? AND ?");
    $st->execute([$uid, $s, $e]);
    return $st->fetch();
};

$cur = $totalsOf($start, $end);
$prev = $totalsOf($pStart, $pEnd);
$sumIn = round((float)$cur['income'], 2);
$sumExp = round((float)$cur['expense'], 2);
[$inBadge, $inUp] = delta_badge($sumIn, (float)$prev['income']);
[$expBadge, $expUp] = delta_badge($sumExp, (float)$prev['expense']);
$prevLabel = $p === 'day' ? '较前日' : '较上期';

// 本期各账号明细（日报用它画分布图，其他报表用它做明细表）
$st = $pdo->prepare("SELECT a.id, a.name,
    COALESCE(SUM(CASE WHEN t.type = 1 THEN t.amount END), 0) AS income,
    COALESCE(SUM(CASE WHEN t.type = 2 THEN t.amount END), 0) AS expense
  FROM transactions t
  JOIN accounts a ON a.id = t.account_id
  WHERE t.user_id = ? AND t.occurred_at BETWEEN ? AND ?
  GROUP BY a.id");
$st->execute([$uid, $start, $end]);
$byAccount = $st->fetchAll();
usort($byAccount, function ($x, $y) {
    return ((float)$y['income'] + (float)$y['expense']) <=> ((float)$x['income'] + (float)$x['expense']);
});

$chart = null;
$dayTxs = [];
if ($p === 'day') {
    // 日报：图表改为当日各账号收支分布
    if ($byAccount) {
        $chart = [
            'labels' => array_map(
                fn($n) => mb_strlen($n) > 5 ? mb_substr($n, 0, 5) . '…' : $n,
                array_column($byAccount, 'name')
            ),
            'series' => [
                ['name' => '收入', 'color' => '#10b981', 'data' => array_map(fn($r) => (float)$r['income'], $byAccount)],
                ['name' => '支出', 'color' => '#f43f5e', 'data' => array_map(fn($r) => (float)$r['expense'], $byAccount)],
            ],
        ];
    }
    // 当日全部流水
    $st = $pdo->prepare("SELECT t.id, t.type, t.amount, t.note, t.occurred_at,
          a.name AS account_name, a.id AS account_id
        FROM transactions t
        JOIN accounts a ON a.id = t.account_id
        WHERE t.user_id = ? AND t.occurred_at = ?
        ORDER BY t.id DESC");
    $st->execute([$uid, $start]);
    $dayTxs = $st->fetchAll();
} else {
    // 周/月/年：按时间分桶（周/月按天，年按月）
    $bucketExpr = $p === 'year' ? "DATE_FORMAT(occurred_at, '%Y-%m')" : 'occurred_at';
    $st = $pdo->prepare("SELECT {$bucketExpr} AS b, type, SUM(amount) AS amt
      FROM transactions
      WHERE user_id = ? AND occurred_at BETWEEN ? AND ?
      GROUP BY b, type");
    $st->execute([$uid, $start, $end]);
    $inc = array_fill_keys($keys, 0.0);
    $exp = array_fill_keys($keys, 0.0);
    foreach ($st->fetchAll() as $r) {
        if (!isset($inc[$r['b']])) {
            continue;
        }
        if ((int)$r['type'] === 1) {
            $inc[$r['b']] = (float)$r['amt'];
        } else {
            $exp[$r['b']] = (float)$r['amt'];
        }
    }
    $chart = [
        'labels' => $labels,
        'series' => [
            ['name' => '收入', 'color' => '#10b981', 'data' => array_values($inc)],
            ['name' => '支出', 'color' => '#f43f5e', 'data' => array_values($exp)],
        ],
    ];
}

$chartTitle = $p === 'day' ? '账号分布' : '收支走势';
$homeLabel = $p === 'day' ? '回到今天' : '回到本期';

$pageTitle = '收支报表';
$active = 'reports';
require __DIR__ . '/inc/header.php';
?>

<section class="rp-head">
  <div class="tabs">
    <a class="tab <?= $p === 'day' ? 'active' : '' ?>" href="?p=day&o=<?= $o ?>">日报</a>
    <a class="tab <?= $p === 'week' ? 'active' : '' ?>" href="?p=week&o=<?= $o ?>">周报</a>
    <a class="tab <?= $p === 'month' ? 'active' : '' ?>" href="?p=month&o=<?= $o ?>">月报</a>
    <a class="tab <?= $p === 'year' ? 'active' : '' ?>" href="?p=year&o=<?= $o ?>">年报</a>
  </div>
  <div class="period">
    <a class="p-btn" href="?p=<?= $p ?>&o=<?= $o - 1 ?>" title="上一期"><?= icon('arr-l') ?></a>
    <span class="p-name"><?= e($name) ?></span>
    <?php if ($o !== 0): ?><a class="p-home" href="?p=<?= $p ?>&o=0"><?= e($homeLabel) ?></a><?php endif; ?>
    <?php if ($o < 0): ?><a class="p-btn" href="?p=<?= $p ?>&o=<?= $o + 1 ?>" title="下一期"><?= icon('arr-r') ?></a><?php endif; ?>
  </div>
</section>

<section class="stats">
  <div class="stat-card">
    <span class="stat-ico ico-up"><?= icon('in') ?></span>
    <div>
      <div class="stat-label">本期收入</div>
      <div class="stat-value c-up">¥<?= money($sumIn) ?></div>
      <span class="delta <?= $inUp ? 'd-good' : 'd-bad' ?>"><?= e($prevLabel) ?> <?= $inBadge ?></span>
    </div>
  </div>
  <div class="stat-card">
    <span class="stat-ico ico-down"><?= icon('out') ?></span>
    <div>
      <div class="stat-label">本期支出</div>
      <div class="stat-value c-down">¥<?= money($sumExp) ?></div>
      <span class="delta <?= $expUp ? 'd-bad' : 'd-good' ?>"><?= e($prevLabel) ?> <?= $expBadge ?></span>
    </div>
  </div>
  <div class="stat-card">
    <span class="stat-ico ico-bal"><?= icon('balance') ?></span>
    <div>
      <div class="stat-label">本期结余</div>
      <div class="stat-value <?= $sumIn - $sumExp >= 0 ? 'c-up' : 'c-down' ?>">¥<?= money($sumIn - $sumExp) ?></div>
    </div>
  </div>
</section>

<section class="chart-card">
  <div class="chart-head">
    <span class="chart-title"><?= icon('chart') ?><?= e($chartTitle) ?> · <?= e($name) ?></span>
    <div class="legend">
      <span><i class="dot dot-in"></i>收入</span>
      <span><i class="dot dot-out"></i>支出</span>
    </div>
  </div>
  <?php if ($chart): ?>
  <div id="chart" class="chart-box"></div>
  <?php else: ?>
  <div class="empty">
    <div class="empty-ico"><?= icon('chart') ?></div>
    <b>该期间暂无收支记录</b>
    <i>换一个期间看看，或去记一笔</i>
  </div>
  <?php endif; ?>
</section>

<?php if ($p === 'day'): ?>
<section class="section-head">
  <h2>当日流水 <span class="count-pill"><?= count($dayTxs) ?></span></h2>
</section>
<section class="rows">
  <?php if (!$dayTxs): ?>
  <div class="empty">
    <div class="empty-ico"><?= icon('note') ?></div>
    <b>这一天没有收支记录</b>
    <i>← 换一天看看，或去账号里记一笔</i>
  </div>
  <?php else: ?>
  <?php foreach ($dayTxs as $t): $isIn = (int)$t['type'] === 1; ?>
  <a class="tx-row tx-row-plain" href="account.php?id=<?= (int)$t['account_id'] ?>">
    <span class="tx-info">
      <b><?= $t['note'] !== '' ? e($t['note']) : '无备注' ?></b>
      <i><?= e($t['account_name']) ?></i>
    </span>
    <span class="tx-amt <?= $isIn ? 'c-up' : 'c-down' ?>"><?= $isIn ? '+' : '−' ?><?= money($t['amount']) ?></span>
    <span></span>
  </a>
  <?php endforeach; ?>
  <?php endif; ?>
</section>
<?php else: ?>
<section class="section-head">
  <h2>账号明细 <span class="count-pill"><?= e($name) ?></span></h2>
</section>
<section class="detail-card">
  <?php if (!$byAccount): ?>
  <div class="empty">
    <div class="empty-ico"><?= icon('wallet') ?></div>
    <b>该期间没有账号产生收支</b>
    <i>去总览页记一笔吧</i>
  </div>
  <?php else: ?>
  <table class="table">
    <thead>
      <tr><th>账号</th><th class="num">收入</th><th class="num">支出</th><th class="num">结余</th></tr>
    </thead>
    <tbody>
    <?php foreach ($byAccount as $a): $bal = (float)$a['income'] - (float)$a['expense']; ?>
      <tr>
        <td><a class="link" href="account.php?id=<?= (int)$a['id'] ?>"><?= e($a['name']) ?></a></td>
        <td class="num c-up">+<?= money($a['income']) ?></td>
        <td class="num c-down">-<?= money($a['expense']) ?></td>
        <td class="num <?= $bal >= 0 ? 'c-up' : 'c-down' ?>"><?= money($bal) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr>
        <td>合计</td>
        <td class="num c-up">+<?= money($sumIn) ?></td>
        <td class="num c-down">-<?= money($sumExp) ?></td>
        <td class="num <?= $sumIn - $sumExp >= 0 ? 'c-up' : 'c-down' ?>"><?= money($sumIn - $sumExp) ?></td>
      </tr>
    </tfoot>
  </table>
  <?php endif; ?>
</section>
<?php endif; ?>

<?php require __DIR__ . '/inc/footer.php'; ?>
