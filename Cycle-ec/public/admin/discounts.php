<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../../src/Product.php';

if (empty($_SESSION['discount_csrf'])) {
    $_SESSION['discount_csrf'] = bin2hex(random_bytes(32));
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = (string) ($_POST['csrf_token'] ?? '');
    $productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    $price = trim((string) ($_POST['price'] ?? ''));
    if (!hash_equals((string) $_SESSION['discount_csrf'], $csrf)) {
        $errors[] = '不正なリクエストです。';
    } elseif (!$productId || !is_numeric($price) || (float) $price < 0) {
        $errors[] = '価格を正しく入力してください。';
    } else {
        try {
            Product::updatePrice($productId, $price);
            header('Location: discounts.php?success=updated');
            exit;
        } catch (Throwable $exception) {
            error_log('Discount price update failed: ' . $exception->getMessage());
            $errors[] = '価格の更新に失敗しました。';
        }
    }
}

try {
    $products = Product::allForDiscountManagement();
} catch (PDOException $exception) {
    $products = [];
    $errors[] = '商品一覧の取得に失敗しました。';
}

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

$adminTitle = '割引管理';
$activeMenu = 'discounts';
$breadcrumbs = [['label' => '割引管理']];
require __DIR__ . '/includes/header.php';
?>
<div class="admin-page-header"><div><h1>割引管理</h1><p class="text-muted mb-0">商品の販売価格を変更します。変更前価格は履歴に保存されます。</p></div></div>
<?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endforeach; ?>
<?php if (($_GET['success'] ?? '') === 'updated'): ?><div class="alert alert-success">価格を更新しました。</div><?php endif; ?>
<div class="admin-card bg-white"><div class="table-responsive"><table class="table admin-table align-middle mb-0">
<thead><tr><th>商品名</th><th>カテゴリ</th><th>ステータス</th><th>現在価格</th><th>新しい価格</th><th></th></tr></thead>
<tbody>
<?php foreach ($products as $product): ?><tr><td><?= h((string) $product['name']) ?></td><td><?= h((string) $product['category_name']) ?></td><td><?= h((string) $product['status']) ?></td><td><?= number_format((float) $product['price']) ?>円</td><td><form method="post" class="d-flex gap-2"><input type="hidden" name="csrf_token" value="<?= h((string) $_SESSION['discount_csrf']) ?>"><input type="hidden" name="product_id" value="<?= (int) $product['product_id'] ?>"><input class="form-control form-control-sm" type="number" name="price" min="0" step="0.01" value="<?= h((string) $product['price']) ?>" required><button class="btn btn-sm btn-primary" type="submit">保存</button></form></td><td><a class="btn btn-sm btn-outline-secondary" href="products/product-price-history.php?id=<?= (int) $product['product_id'] ?>">履歴</a></td></tr><?php endforeach; ?>
<?php if (!$products): ?><tr><td colspan="6" class="text-center text-muted py-4">商品がありません。</td></tr><?php endif; ?>
</tbody></table></div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>