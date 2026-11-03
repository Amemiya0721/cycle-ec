<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../src/Accounting.php';
require_once __DIR__ . '/../includes/auth.php';

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

$period = is_string($_GET['period'] ?? null) ? $_GET['period'] : 'month';
if (!in_array($period, ['day', 'month', 'year'], true)) {
    $period = 'month';
}
$date = is_string($_GET['date'] ?? null) ? $_GET['date'] : date('Y-m-d');
try {
    $range = Accounting::dateRange($period, $date);
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    exit('集計期間が正しくありません。');
}
try {
    $report = Accounting::report($range['start'], $range['end'], $period);
} catch (Throwable $exception) {
    error_log('Accounting report load failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('売上・帳簿情報を取得できません。先に会計機能のDBマイグレーションを適用してください。');
}

$total = $report['totals'];
$unknownCostItems = (int) $total['unknown_cost_items'];
$profit = (float) $total['sales'] - (float) $total['cost_of_goods'] - (float) $total['expenses'];
$periods = [];
foreach (['paid', 'orders', 'expenses_by_period'] as $group) {
    foreach ($report[$group] as $row) {
        $periods[(string) $row['period']] = true;
    }
}
$periodKeys = array_keys($periods);
sort($periodKeys);
$paidByPeriod = [];
foreach ($report['paid'] as $row) {
    $paidByPeriod[(string) $row['period']] = $row;
}
$orderByPeriod = [];
foreach ($report['orders'] as $row) {
    $orderByPeriod[(string) $row['period']] = $row;
}
$expenseByPeriod = [];
foreach ($report['expenses_by_period'] as $row) {
    $expenseByPeriod[(string) $row['period']] = (float) $row['amount'];
}

$periodLabels = ['day' => '日別', 'month' => '月別', 'year' => '年別'];
$adminTitle = '売上・帳簿管理';
$activeMenu = 'accounting';
$breadcrumbs = [['label' => '売上・帳簿管理']];
require __DIR__ . '/../includes/header.php';
?>
<div class="admin-page-header">
    <div><h1>売上・帳簿管理</h1><p class="text-muted mb-0">入金日・注文日基準の集計、仕入原価、経費を確認します。</p></div>
    <a class="btn btn-outline-primary" href="export.php?<?= h(http_build_query(['period' => $period, 'date' => $date])) ?>">帳簿CSVを出力</a>
</div>

<nav class="nav nav-pills mb-4">
    <a class="nav-link active" href="index.php">売上集計</a>
    <a class="nav-link" href="purchases.php">仕入管理</a>
    <a class="nav-link" href="expenses.php">経費管理</a>
</nav>

<form method="get" class="admin-card bg-white p-3 mb-4 row g-3 align-items-end">
    <div class="col-sm-4 col-md-3">
        <label class="form-label" for="period">集計単位</label>
        <select class="form-select" id="period" name="period">
            <?php foreach ($periodLabels as $key => $label): ?>
                <option value="<?= h($key) ?>" <?= $period === $key ? 'selected' : '' ?>><?= h($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-sm-5 col-md-4">
        <label class="form-label" for="date">対象日</label>
        <input class="form-control" type="date" id="date" name="date" value="<?= h($date) ?>" required>
        <div class="form-text">選択した日の属する期間を集計します。</div>
    </div>
    <div class="col-auto"><button class="btn btn-primary" type="submit">集計する</button></div>
</form>

<section class="row g-3 mb-4" aria-label="売上サマリー">
    <?php
    $cards = [
        ['期間内の入金済み売上', number_format((float) $total['sales'], 2) . ' 円'],
        ['未入金注文残高（' . number_format((int) $total['outstanding_orders']) . '件）', number_format((float) $total['outstanding'], 2) . ' 円'],
        ['期間内の売上原価', $unknownCostItems > 0 ? '一部未確定' : number_format((float) $total['cost_of_goods'], 2) . ' 円'],
        ['期間内の経費', number_format((float) $total['expenses'], 2) . ' 円'],
        ['売上総利益 − 経費', $unknownCostItems > 0 ? '原価未確定' : number_format($profit, 2) . ' 円'],
    ];
    ?>
    <?php foreach ($cards as [$label, $value]): ?>
        <div class="col-sm-6 col-xl">
            <div class="admin-card bg-white p-3 h-100">
                <div class="text-muted small"><?= h($label) ?></div>
                <div class="fs-5 fw-bold"><?= h($value) ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</section>
<?php if ($unknownCostItems > 0): ?>
    <div class="alert alert-warning">仕入原価が未設定の注文商品が <?= number_format($unknownCostItems) ?> 点あります。過去の売上原価と利益は、当時の原価記録がないため確定できません。今後の仕入は仕入管理へ記録してください。</div>
<?php endif; ?>

<section class="admin-card bg-white mb-4">
    <div class="p-3 border-bottom"><h2 class="admin-section-title mb-0">入金日基準の売上・利益（<?= h($periodLabels[$period]) ?>）</h2></div>
    <div class="table-responsive">
        <table class="table admin-table align-middle mb-0">
            <thead><tr><th>期間</th><th class="text-end">入金済注文</th><th class="text-end">商品売上</th><th class="text-end">送料売上</th><th class="text-end">売上合計</th><th class="text-end">売上原価</th><th class="text-end">経費</th><th class="text-end">利益</th></tr></thead>
            <tbody>
            <?php foreach ($periodKeys as $key): ?>
                <?php
                $paid = $paidByPeriod[$key] ?? [];
                $sales = (float) ($paid['sales'] ?? 0);
                $cogs = (float) ($paid['cost_of_goods'] ?? 0);
                $expenses = $expenseByPeriod[$key] ?? 0;
                $unknown = (int) ($paid['unknown_cost_count'] ?? 0);
                ?>
                <tr>
                    <td><?= h($key) ?></td>
                    <td class="text-end"><?= number_format((int) ($paid['order_count'] ?? 0)) ?></td>
                    <td class="text-end"><?= number_format((float) ($paid['merchandise_sales'] ?? 0), 2) ?> 円</td>
                    <td class="text-end"><?= number_format((float) ($paid['shipping_sales'] ?? 0), 2) ?> 円</td>
                    <td class="text-end"><?= number_format($sales, 2) ?> 円</td>
                    <td class="text-end"><?= $unknown > 0 ? '未確定' : number_format($cogs, 2) . ' 円' ?></td>
                    <td class="text-end"><?= number_format($expenses, 2) ?> 円</td>
                    <td class="text-end fw-semibold"><?= $unknown > 0 ? '未確定' : number_format($sales - $cogs - $expenses, 2) . ' 円' ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($periodKeys === []): ?><tr><td colspan="8" class="text-center text-muted py-4">対象期間の売上・注文・経費はありません。</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="admin-card bg-white mb-4">
    <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
        <div><h2 class="admin-section-title mb-0">注文日基準の注文・入金状況（<?= h($periodLabels[$period]) ?>）</h2><p class="text-muted small mb-0">入金日ではなく注文日に集計します。未入金額は売上に含めていません。</p></div>
        <a href="../order/index.php" class="btn btn-sm btn-outline-primary">注文管理</a>
    </div>
    <div class="table-responsive">
        <table class="table admin-table align-middle mb-0">
            <thead><tr><th>期間</th><th class="text-end">注文数</th><th class="text-end">注文総額</th><th class="text-end">入金済（件数／額）</th><th class="text-end">未入金（件数／額）</th><th class="text-end">キャンセル額</th></tr></thead>
            <tbody>
            <?php foreach ($periodKeys as $key): ?>
                <?php $order = $orderByPeriod[$key] ?? []; ?>
                <tr>
                    <td><?= h($key) ?></td>
                    <td class="text-end"><?= number_format((int) ($order['order_count'] ?? 0)) ?></td>
                    <td class="text-end"><?= number_format((float) ($order['order_amount'] ?? 0), 2) ?> 円</td>
                    <td class="text-end"><?= number_format((int) ($order['paid_order_count'] ?? 0)) ?> 件 / <?= number_format((float) ($order['paid_amount'] ?? 0), 2) ?> 円</td>
                    <td class="text-end"><?= number_format((int) ($order['unpaid_order_count'] ?? 0)) ?> 件 / <?= number_format((float) ($order['unpaid_amount'] ?? 0), 2) ?> 円</td>
                    <td class="text-end"><?= number_format((float) ($order['cancelled_amount'] ?? 0), 2) ?> 円</td>
                </tr>
            <?php endforeach; ?>
            <?php if ($periodKeys === []): ?><tr><td colspan="6" class="text-center text-muted py-4">対象期間の注文はありません。</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="admin-card bg-white">
    <div class="p-3 border-bottom d-flex justify-content-between align-items-center"><h2 class="admin-section-title mb-0">期間内の経費</h2><a href="expenses.php" class="btn btn-sm btn-outline-primary">経費を登録</a></div>
    <div class="table-responsive">
        <table class="table admin-table align-middle mb-0">
            <thead><tr><th>日付</th><th>科目</th><th>内容</th><th class="text-end">金額</th></tr></thead>
            <tbody>
            <?php foreach ($report['expenses'] as $expense): ?>
                <tr><td><?= h((string) $expense['expense_date']) ?></td><td><?= h((string) $expense['category']) ?></td><td><?= h((string) $expense['description']) ?></td><td class="text-end"><?= number_format((float) $expense['amount'], 2) ?> 円</td></tr>
            <?php endforeach; ?>
            <?php if ($report['expenses'] === []): ?><tr><td colspan="4" class="text-center text-muted py-4">この期間の経費はありません。</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
