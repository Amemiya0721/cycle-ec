<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/Product.php';
require_once __DIR__ . '/../../src/Category.php';
require_once __DIR__ . '/../../src/Order.php';
require_once __DIR__ . '/includes/auth.php';

$summary = ['products' => 0, 'categories' => 0, 'orders' => 0];
$recentProducts = [];
try {
	$productResult = Product::search(['page' => 1, 'sort' => 'newest']);
	$summary['products'] = (int) ($productResult['total'] ?? 0);
	$recentProducts = array_slice($productResult['items'] ?? [], 0, 5);
	$summary['categories'] = count(Category::all());
	$summary['orders'] = count(Order::all());
} catch (PDOException $exception) {
	$errorMessage = 'ダッシュボード情報の取得に失敗しました。';
}

function h(?string $value): string
{
	return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

$adminTitle = 'ダッシュボード';
$activeMenu = 'dashboard';
$breadcrumbs = [['label' => 'ダッシュボード']];
require __DIR__ . '/includes/header.php';
?>
<div class="admin-page-header">
	<div><h1>ダッシュボード</h1><p class="text-muted mb-0">今日の運用状況を確認できます。</p></div>
	<a class="btn btn-primary" href="products/product-create.php">＋ 商品を追加</a>
</div>
<?php if (isset($errorMessage)): ?><div class="alert alert-danger"><?= h($errorMessage) ?></div><?php endif; ?>
<section class="row g-3 mb-4" aria-label="現在の状況">
	<div class="col-sm-4"><div class="admin-card bg-white p-3"><div class="text-muted small">登録商品</div><div class="fs-3 fw-bold"><?= $summary['products'] ?><span class="fs-6 fw-normal ms-1">件</span></div></div></div>
	<div class="col-sm-4"><div class="admin-card bg-white p-3"><div class="text-muted small">カテゴリ</div><div class="fs-3 fw-bold"><?= $summary['categories'] ?><span class="fs-6 fw-normal ms-1">件</span></div></div></div>
	<div class="col-sm-4"><div class="admin-card bg-white p-3"><div class="text-muted small">注文</div><div class="fs-3 fw-bold"><?= $summary['orders'] ?><span class="fs-6 fw-normal ms-1">件</span></div></div></div>
</section>
<section class="admin-card bg-white">
	<div class="p-3 border-bottom d-flex justify-content-between align-items-center"><h2 class="admin-section-title mb-0">最近追加した商品</h2><a href="products/index.php" class="small">商品一覧</a></div>
	<div class="table-responsive"><table class="table admin-table align-middle mb-0"><thead><tr><th>商品名</th><th>カテゴリ</th><th>価格</th><th></th></tr></thead><tbody>
	<?php foreach ($recentProducts as $product): ?><tr><td><?= h((string) $product['name']) ?></td><td><?= h((string) ($product['category_name'] ?? '')) ?></td><td><?= number_format((float) $product['price']) ?>円</td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="products/product-edit.php?id=<?= (int) $product['product_id'] ?>">編集</a></td></tr><?php endforeach; ?>
	<?php if (!$recentProducts): ?><tr><td colspan="4" class="text-center text-muted py-4">商品がありません。</td></tr><?php endif; ?>
	</tbody></table></div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>