<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../src/Accounting.php';
require_once __DIR__ . '/../includes/auth.php';

if (empty($_SESSION['accounting_csrf'])) {
    $_SESSION['accounting_csrf'] = bin2hex(random_bytes(32));
}
function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

$errors = [];
$returnDate = is_string($_GET['date'] ?? null) ? $_GET['date'] : date('Y-m-d');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? null;
    if (!is_string($csrf) || !hash_equals((string) $_SESSION['accounting_csrf'], $csrf)) {
        http_response_code(403);
        exit('不正なリクエストです。');
    }
    try {
        if (($_POST['action'] ?? '') === 'delete') {
            $idValue = $_POST['expense_id'] ?? null;
            $id = is_string($idValue) ? filter_var($idValue, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
            if ($id === false || $id === null || !Accounting::deleteExpense((int) $id)) {
                throw new InvalidArgumentException('削除対象の経費が見つかりません。');
            }
            $_SESSION['accounting_flash'] = ['type' => 'success', 'text' => '経費を削除しました。'];
        } elseif (($_POST['action'] ?? '') === 'add') {
            Accounting::addExpense(
                is_string($_POST['expense_date'] ?? null) ? $_POST['expense_date'] : '',
                is_string($_POST['category'] ?? null) ? trim($_POST['category']) : '',
                is_string($_POST['description'] ?? null) ? trim($_POST['description']) : '',
                is_string($_POST['amount'] ?? null) ? trim($_POST['amount']) : ''
            );
            $returnDate = is_string($_POST['expense_date'] ?? null) ? $_POST['expense_date'] : $returnDate;
            $_SESSION['accounting_flash'] = ['type' => 'success', 'text' => '経費を登録しました。'];
        } else {
            throw new InvalidArgumentException('操作内容が正しくありません。');
        }
        header('Location: expenses.php?date=' . rawurlencode($returnDate), true, 303);
        exit;
    } catch (InvalidArgumentException $exception) {
        $errors[] = $exception->getMessage();
    } catch (Throwable $exception) {
        error_log('Operating expense save failed: ' . $exception->getMessage());
        $errors[] = '経費を保存できませんでした。DBマイグレーションの適用状況を確認してください。';
    }
}

$flash = $_SESSION['accounting_flash'] ?? null;
unset($_SESSION['accounting_flash']);
$date = $returnDate;
try {
    $range = Accounting::dateRange('month', $date);
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    exit('対象日が正しくありません。');
}
try {
    $report = Accounting::report($range['start'], $range['end'], 'month');
} catch (Throwable $exception) {
    error_log('Operating expenses load failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('経費情報を取得できません。先に会計機能のDBマイグレーションを適用してください。');
}

$adminTitle = '経費管理';
$activeMenu = 'accounting';
$breadcrumbs = [['label' => '売上・帳簿管理', 'url' => 'index.php'], ['label' => '経費管理']];
require __DIR__ . '/../includes/header.php';
?>
<div class="admin-page-header"><div><h1>経費管理</h1><p class="text-muted mb-0">商品仕入とは区別して運営経費を登録します。</p></div></div>
<nav class="nav nav-pills mb-4"><a class="nav-link" href="index.php">売上集計</a><a class="nav-link" href="purchases.php">仕入管理</a><a class="nav-link active" href="expenses.php">経費管理</a></nav>
<?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endforeach; ?>
<?php if (is_array($flash) && ($flash['type'] ?? null) === 'success'): ?><div class="alert alert-success"><?= h((string) $flash['text']) ?></div><?php endif; ?>

<section class="admin-card bg-white p-3 mb-4">
    <h2 class="admin-section-title">経費を登録</h2>
    <form method="post" class="row g-3">
        <input type="hidden" name="csrf_token" value="<?= h((string) $_SESSION['accounting_csrf']) ?>">
        <input type="hidden" name="action" value="add">
        <div class="col-sm-4 col-md-3"><label class="form-label" for="expense-date">日付</label><input class="form-control" type="date" id="expense-date" name="expense_date" value="<?= h(date('Y-m-d')) ?>" required></div>
        <div class="col-sm-4 col-md-3"><label class="form-label" for="expense-category">科目</label><input class="form-control" id="expense-category" name="category" maxlength="80" placeholder="例：広告費、決済手数料" required></div>
        <div class="col-sm-4 col-md-3"><label class="form-label" for="expense-amount">金額</label><div class="input-group"><input class="form-control" type="number" min="0.01" max="99999999.99" step="0.01" id="expense-amount" name="amount" required><span class="input-group-text">円</span></div></div>
        <div class="col-md-3"><label class="form-label" for="expense-description">内容</label><input class="form-control" id="expense-description" name="description" maxlength="255" required></div>
        <div class="col-12"><button class="btn btn-primary" type="submit">経費を登録</button></div>
    </form>
</section>

<form method="get" class="row g-2 align-items-end mb-3">
    <div class="col-auto"><label class="form-label" for="month">対象月</label><input class="form-control" type="date" id="month" name="date" value="<?= h($date) ?>" required></div>
    <div class="col-auto"><button class="btn btn-outline-primary" type="submit">表示</button></div>
</form>
<section class="admin-card bg-white">
    <div class="p-3 border-bottom"><h2 class="admin-section-title mb-0">経費（<?= h(substr($range['start'], 0, 7)) ?>）</h2></div>
    <div class="table-responsive"><table class="table admin-table align-middle mb-0">
        <thead><tr><th>日付</th><th>科目</th><th>内容</th><th class="text-end">金額</th><th>操作</th></tr></thead>
        <tbody>
        <?php foreach ($report['expenses'] as $expense): ?>
            <tr><td><?= h((string) $expense['expense_date']) ?></td><td><?= h((string) $expense['category']) ?></td><td><?= h((string) $expense['description']) ?></td><td class="text-end"><?= number_format((float) $expense['amount'], 2) ?> 円</td><td><form method="post" onsubmit="return confirm('この経費を削除しますか？');"><input type="hidden" name="csrf_token" value="<?= h((string) $_SESSION['accounting_csrf']) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="expense_id" value="<?= (int) $expense['expense_id'] ?>"><button class="btn btn-sm btn-outline-danger" type="submit">削除</button></form></td></tr>
        <?php endforeach; ?>
        <?php if ($report['expenses'] === []): ?><tr><td colspan="5" class="text-center text-muted py-4">この月の経費はありません。</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
