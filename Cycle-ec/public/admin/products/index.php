<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../src/Product.php';
require_once __DIR__ . '/../../../src/Category.php';
require_once __DIR__ . '/../includes/auth.php';

$errorMessage = null;
$keyword = trim((string) ($_GET['keyword'] ?? ''));
$category = trim((string) ($_GET['category'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
try {
    $result = Product::search([
        'keyword' => $keyword,
        'category' => $category,
        'status' => $status,
        'page' => max(1, (int) ($_GET['page'] ?? 1)),
        'sort' => 'newest',
    ]);
    $categories = Category::all();
} catch (PDOException $exception) {
    $result = ['items' => [], 'total' => 0, 'page' => 1, 'total_pages' => 1];
    $categories = [];
    $errorMessage = '商品一覧の取得に失敗しました。';
}

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function pageUrl(int $page, string $keyword, string $category, string $status): string
{
    $query = ['page' => $page];
    if ($keyword !== '') {
        $query['keyword'] = $keyword;
    }
    if ($category !== '') {
        $query['category'] = $category;
    }
    if ($status !== '') {
        $query['status'] = $status;
    }
    return 'index.php?' . http_build_query($query);
}
?>
<?php
$adminTitle = '商品管理';
$activeMenu = 'products';
$breadcrumbs = [['label' => '商品管理']];
require __DIR__ . '/../includes/header.php';
?>
<div class="admin-page-header"><div><h1>商品管理</h1><p class="text-muted mb-0">商品の登録・検索・編集を行います。</p></div><a class="btn btn-primary" href="product-create.php">＋ 商品を追加</a></div>
    <?php if ($errorMessage !== null): ?><div class="alert alert-danger"><?= h($errorMessage) ?></div><?php endif; ?>
    <?php if (isset($_GET['success'])): ?><div class="alert alert-success">商品を更新しました。</div><?php endif; ?>
    <form class="row g-2 mb-3" method="get">
        <div class="col-md-5"><input class="form-control" name="keyword" value="<?= h($keyword) ?>" placeholder="商品名・カテゴリで検索"></div>
        <div class="col-md-3"><select class="form-select" name="category"><option value="">すべてのカテゴリ</option><?php foreach ($categories as $categoryItem): ?><option value="<?= (int) $categoryItem['category_id'] ?>" <?= $category === (string) $categoryItem['category_id'] ? 'selected' : '' ?>><?= h((string) $categoryItem['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-2"><select class="form-select" name="status"><option value="">すべての状態</option><option value="販売中" <?= $status === '販売中' ? 'selected' : '' ?>>販売中</option><option value="下書き" <?= $status === '下書き' ? 'selected' : '' ?>>下書き</option><option value="売切れ" <?= $status === '売切れ' ? 'selected' : '' ?>>売切れ</option></select></div>
        <div class="col-auto"><button class="btn btn-outline-secondary" type="submit">検索</button></div>
    </form>
    <div class="admin-card bg-white"><div class="table-responsive"><table class="table admin-table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>ID</th><th>商品名</th><th>メーカー</th><th>カテゴリ</th><th>価格</th><th>状態</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($result['items'] as $item): ?>
            <tr>
                <td><?= (int) $item['product_id'] ?></td>
                <td><?= h((string) $item['name']) ?></td>
                <td><?= h((string) ($item['manufacturer'] ?? '')) ?></td>
                <td><?= h((string) $item['category_name']) ?></td>
                <td><?= number_format((float) $item['price']) ?>円</td>
                <td><?= h((string) $item['status']) ?></td>
                <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="product-images.php?id=<?= (int) $item['product_id'] ?>">画像</a> <a class="btn btn-sm btn-outline-secondary" href="product-price-history.php?id=<?= (int) $item['product_id'] ?>">価格履歴</a> <a class="btn btn-sm btn-outline-primary" href="product-edit.php?id=<?= (int) $item['product_id'] ?>">編集</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($result['items'])): ?><tr><td colspan="7" class="text-center py-4">商品がありません。</td></tr><?php endif; ?>
        </tbody>
    </table></div></div>
    <?php if ($result['total_pages'] > 1): ?><nav class="mt-3"><ul class="pagination">
        <?php for ($page = 1; $page <= $result['total_pages']; $page++): ?><li class="page-item <?= $page === $result['page'] ? 'active' : '' ?>"><a class="page-link" href="<?= h(pageUrl($page, $keyword, $category, $status)) ?>"><?= $page ?></a></li><?php endfor; ?>
    </ul></nav><?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
