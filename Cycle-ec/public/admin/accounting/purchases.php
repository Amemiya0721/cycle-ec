<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../src/InventoryPurchase.php';
require_once __DIR__ . '/../includes/auth.php';

if (empty($_SESSION['accounting_csrf'])) {
    $_SESSION['accounting_csrf'] = bin2hex(random_bytes(32));
}
function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? null;
    if (!is_string($csrf) || !hash_equals((string) $_SESSION['accounting_csrf'], $csrf)) {
        http_response_code(403);
        exit('不正なリクエストです。');
    }
    $productIdValue = $_POST['product_id'] ?? null;
    $productId = is_string($productIdValue) ? filter_var($productIdValue, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
    $date = is_string($_POST['purchase_date'] ?? null) ? trim($_POST['purchase_date']) : '';
    $unitCost = is_string($_POST['unit_cost'] ?? null) ? trim($_POST['unit_cost']) : '';
    $quantityValue = $_POST['quantity'] ?? null;
    $quantity = is_string($quantityValue) && ctype_digit($quantityValue) ? (int) $quantityValue : 0;
    $notes = is_string($_POST['notes'] ?? null) ? trim($_POST['notes']) : '';
    try {
        if ($productId === false || $productId === null) {
            throw new InvalidArgumentException('商品を選択してください。');
        }
        if (($_POST['action'] ?? '') === 'opening') {
            InventoryPurchase::recordOpeningBalance((int) $productId, $unitCost, $date, $notes !== '' ? $notes : null);
            $_SESSION['accounting_flash'] = ['type' => 'success', 'text' => '初期在庫の仕入原価を登録しました。在庫数は変更していません。'];
        } elseif (($_POST['action'] ?? '') === 'purchase') {
            InventoryPurchase::recordPurchase((int) $productId, $quantity, $unitCost, $date, $notes !== '' ? $notes : null);
            $_SESSION['accounting_flash'] = ['type' => 'success', 'text' => '仕入を記録し、在庫数と移動平均原価を更新しました。'];
        } else {
            throw new InvalidArgumentException('操作内容が正しくありません。');
        }
        header('Location: purchases.php', true, 303);
        exit;
    } catch (InvalidArgumentException $exception) {
        $errors[] = $exception->getMessage();
    } catch (Throwable $exception) {
        error_log('Inventory purchase registration failed: ' . $exception->getMessage());
        $errors[] = '仕入記録を保存できませんでした。DBマイグレーションの適用状況を確認してください。';
    }
}

$flash = $_SESSION['accounting_flash'] ?? null;
unset($_SESSION['accounting_flash']);
try {
    $data = InventoryPurchase::adminData();
} catch (Throwable $exception) {
    error_log('Inventory purchases load failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('仕入情報を取得できません。先に会計機能のDBマイグレーションを適用してください。');
}

$adminTitle = '仕入管理';
$activeMenu = 'accounting';
$breadcrumbs = [['label' => '売上・帳簿管理', 'url' => 'index.php'], ['label' => '仕入管理']];
require __DIR__ . '/../includes/header.php';
?>
<div class="admin-page-header"><div><h1>仕入管理</h1><p class="text-muted mb-0">仕入記録は在庫数と移動平均原価に反映します。</p></div></div>
<nav class="nav nav-pills mb-4"><a class="nav-link" href="index.php">売上集計</a><a class="nav-link active" href="purchases.php">仕入管理</a><a class="nav-link" href="expenses.php">経費管理</a></nav>
<?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endforeach; ?>
<?php if (is_array($flash) && ($flash['type'] ?? null) === 'success'): ?><div class="alert alert-success"><?= h((string) $flash['text']) ?></div><?php endif; ?>

<div class="alert alert-info">現在の在庫には初期原価が未設定の商品が含まれる場合があります。通常仕入を登録する前に、在庫がある商品は「初期在庫原価」を一度登録してください。初期原価の登録は在庫数を増やしません。</div>
<section class="row g-3 mb-4">
    <?php foreach (['purchase' => '仕入を追加（在庫を増やす）', 'opening' => '初期在庫原価を設定（在庫数はそのまま）'] as $action => $title): ?>
        <div class="col-lg-6">
            <div class="admin-card bg-white p-3 h-100">
                <h2 class="admin-section-title"><?= h($title) ?></h2>
                <form method="post" class="row g-3">
                    <input type="hidden" name="csrf_token" value="<?= h((string) $_SESSION['accounting_csrf']) ?>">
                    <input type="hidden" name="action" value="<?= h($action) ?>">
                    <div class="col-12"><label class="form-label" for="product-<?= h($action) ?>">商品</label>
                        <select class="form-select" id="product-<?= h($action) ?>" name="product_id" required><option value="">選択してください</option>
                            <?php foreach ($data['products'] as $product): ?>
                                <option value="<?= (int) $product['product_id'] ?>"><?= h((string) $product['name']) ?>（在庫 <?= (int) $product['stock_quantity'] ?> 点<?= $product['average_cost'] === null ? '・原価未設定' : '・平均原価 ' . number_format((float) $product['average_cost'], 2) . '円' ?>）</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if ($action === 'purchase'): ?>
                        <div class="col-sm-4"><label class="form-label" for="quantity-<?= h($action) ?>">仕入数量</label><input class="form-control" type="number" min="1" max="4294967295" id="quantity-<?= h($action) ?>" name="quantity" required></div>
                    <?php endif; ?>
                    <div class="<?= $action === 'purchase' ? 'col-sm-4' : 'col-sm-6' ?>"><label class="form-label" for="cost-<?= h($action) ?>">1点あたりの仕入原価</label><div class="input-group"><input class="form-control" type="number" min="0" max="99999999.99" step="0.01" id="cost-<?= h($action) ?>" name="unit_cost" required><span class="input-group-text">円</span></div></div>
                    <div class="<?= $action === 'purchase' ? 'col-sm-4' : 'col-sm-6' ?>"><label class="form-label" for="date-<?= h($action) ?>">仕入日</label><input class="form-control" type="date" id="date-<?= h($action) ?>" name="purchase_date" value="<?= h(date('Y-m-d')) ?>" required></div>
                    <div class="col-12"><label class="form-label" for="notes-<?= h($action) ?>">備考</label><input class="form-control" id="notes-<?= h($action) ?>" name="notes" maxlength="500"></div>
                    <div class="col-12"><button class="btn btn-primary" type="submit"><?= $action === 'purchase' ? '仕入を登録' : '初期原価を登録' ?></button></div>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
</section>

<section class="admin-card bg-white">
    <div class="p-3 border-bottom"><h2 class="admin-section-title mb-0">仕入記録（直近100件）</h2></div>
    <div class="table-responsive"><table class="table admin-table align-middle mb-0">
        <thead><tr><th>仕入日</th><th>商品</th><th>種別</th><th class="text-end">数量</th><th class="text-end">単価</th><th class="text-end">合計</th><th>備考</th></tr></thead>
        <tbody>
        <?php foreach ($data['items'] as $item): ?>
            <tr><td><?= h((string) $item['purchase_date']) ?></td><td><?= h((string) $item['product_name']) ?></td><td><?= $item['record_type'] === 'opening_balance' ? '初期在庫原価' : '仕入' ?></td><td class="text-end"><?= number_format((int) $item['quantity']) ?></td><td class="text-end"><?= number_format((float) $item['unit_cost'], 2) ?> 円</td><td class="text-end"><?= number_format((float) $item['total_cost'], 2) ?> 円</td><td><?= h((string) ($item['notes'] ?? '')) ?></td></tr>
        <?php endforeach; ?>
        <?php if ($data['items'] === []): ?><tr><td colspan="7" class="text-center text-muted py-4">仕入記録はありません。</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
