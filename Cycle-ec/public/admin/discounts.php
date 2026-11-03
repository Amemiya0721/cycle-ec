<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../../src/Product.php';

if (empty($_SESSION['discount_csrf'])) {
    $_SESSION['discount_csrf'] = bin2hex(random_bytes(32));
}

$errors = [];
$keywordInput = $_GET['keyword'] ?? '';
$keyword = is_string($keywordInput) ? trim($keywordInput) : '';
$pageInput = $_GET['page'] ?? '1';
$page = is_string($pageInput) && ctype_digit($pageInput) ? max(1, (int) $pageInput) : 1;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? null;
    $productIdInput = $_POST['product_id'] ?? null;
    $productId = is_string($productIdInput)
        ? filter_var($productIdInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
        : false;
    $priceInput = $_POST['price'] ?? null;
    $price = is_string($priceInput) ? trim($priceInput) : '';
    if (!is_string($csrf) || !hash_equals((string) $_SESSION['discount_csrf'], $csrf)) {
        $errors[] = '不正なリクエストです。';
    } elseif ($productId === false || $productId === null || !preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', $price)) {
        $errors[] = '価格を正しく入力してください。';
    } else {
        try {
            Product::updatePrice((int) $productId, $price);
            header('Location: discounts.php?' . http_build_query([
                'keyword' => $keyword,
                'page' => $page > 1 ? $page : null,
                'success' => 'updated',
            ]), true, 303);
            exit;
        } catch (Throwable $exception) {
            error_log('Discount price update failed: ' . $exception->getMessage());
            $errors[] = '価格の更新に失敗しました。';
        }
    }
}

try {
    $result = Product::allForDiscountManagement($keyword, $page);
} catch (PDOException $exception) {
    error_log('Discount product list failed: ' . $exception->getMessage());
    $result = ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 10, 'total_pages' => 1];
    $errors[] = '商品一覧の取得に失敗しました。';
}
$products = $result['items'];

function discountPageUrl(int $page, string $keyword): string
{
    $params = ['page' => $page > 1 ? $page : null, 'keyword' => $keyword !== '' ? $keyword : null];
    return 'discounts.php' . (($query = http_build_query(array_filter($params, static fn($value): bool => $value !== null))) !== '' ? '?' . $query : '');
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
<form method="get" class="row g-2 mb-3">
    <div class="col-sm-6 col-md-5"><label class="visually-hidden" for="discount-keyword">商品を検索</label><input class="form-control" id="discount-keyword" type="search" name="keyword" value="<?= h($keyword) ?>" placeholder="商品名・メーカー・カテゴリ・ステータス"></div>
    <div class="col-auto"><button class="btn btn-outline-primary" type="submit">検索</button><?php if ($keyword !== ''): ?> <a class="btn btn-outline-secondary" href="discounts.php">クリア</a><?php endif; ?></div>
    <div class="col-auto align-self-center text-muted small"><?= number_format((int) $result['total']) ?> 件</div>
</form>
<div class="admin-card bg-white"><div class="table-responsive"><table class="table admin-table align-middle mb-0">
<thead><tr><th>商品名</th><th>カテゴリ</th><th>ステータス</th><th>現在価格</th><th>新しい価格</th><th></th></tr></thead>
<tbody>
<?php foreach ($products as $product): ?><tr><td><?= h((string) $product['name']) ?><?php if (!empty($product['manufacturer'])): ?><div class="small text-muted"><?= h((string) $product['manufacturer']) ?></div><?php endif; ?></td><td><?= h((string) $product['category_name']) ?></td><td><?= h(Product::statusLabel((string) $product['status'])) ?></td><td><?= number_format((float) $product['price']) ?>円</td><td><form method="post" action="<?= h(discountPageUrl((int) $result['page'], $keyword)) ?>" class="d-flex gap-2"><input type="hidden" name="csrf_token" value="<?= h((string) $_SESSION['discount_csrf']) ?>"><input type="hidden" name="product_id" value="<?= (int) $product['product_id'] ?>"><input class="form-control form-control-sm" type="number" name="price" min="0" max="99999999.99" step="0.01" value="<?= h((string) $product['price']) ?>" required><button class="btn btn-sm btn-primary" type="submit">保存</button></form></td><td><a class="btn btn-sm btn-outline-secondary" href="products/product-price-history.php?id=<?= (int) $product['product_id'] ?>">履歴</a></td></tr><?php endforeach; ?>
<?php if (!$products): ?><tr><td colspan="6" class="text-center text-muted py-4"><?= $keyword === '' ? '商品がありません。' : '検索条件に一致する商品がありません。' ?></td></tr><?php endif; ?>
</tbody></table></div></div>
<?php if ($result['total_pages'] > 1): ?>
    <nav class="mt-3" aria-label="割引管理の商品ページ">
        <ul class="pagination pagination-sm">
            <?php
            $startPage = max(1, (int) $result['page'] - 3);
            $endPage = min((int) $result['total_pages'], (int) $result['page'] + 3);
            ?>
            <?php if ((int) $result['page'] > 1): ?><li class="page-item"><a class="page-link" href="<?= h(discountPageUrl((int) $result['page'] - 1, $keyword)) ?>" aria-label="前のページ">前へ</a></li><?php endif; ?>
            <?php for ($pageNumber = $startPage; $pageNumber <= $endPage; $pageNumber++): ?>
                <li class="page-item <?= $pageNumber === (int) $result['page'] ? 'active' : '' ?>"><a class="page-link" href="<?= h(discountPageUrl($pageNumber, $keyword)) ?>" <?= $pageNumber === (int) $result['page'] ? 'aria-current="page"' : '' ?>><?= $pageNumber ?></a></li>
            <?php endfor; ?>
            <?php if ((int) $result['page'] < (int) $result['total_pages']): ?><li class="page-item"><a class="page-link" href="<?= h(discountPageUrl((int) $result['page'] + 1, $keyword)) ?>" aria-label="次のページ">次へ</a></li><?php endif; ?>
        </ul>
    </nav>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>