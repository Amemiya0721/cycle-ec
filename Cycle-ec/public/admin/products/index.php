<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../src/Product.php';

$errorMessage = null;
try {
    $result = Product::search([
        'keyword' => trim((string) ($_GET['keyword'] ?? '')),
        'page' => max(1, (int) ($_GET['page'] ?? 1)),
        'sort' => 'newest',
    ]);
} catch (PDOException $exception) {
    $result = ['items' => [], 'total' => 0, 'page' => 1, 'total_pages' => 1];
    $errorMessage = '商品一覧の取得に失敗しました。';
}

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function pageUrl(int $page, string $keyword): string
{
    $query = ['page' => $page];
    if ($keyword !== '') {
        $query['keyword'] = $keyword;
    }
    return 'index.php?' . http_build_query($query);
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>商品管理 | OVERHAUL</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-dark bg-dark">
    <div class="container"><a class="navbar-brand" href="../index.php">OVERHAUL 管理画面</a><a class="btn btn-outline-light btn-sm" href="../../index.php">サイトを見る</a></div>
</nav>
<main class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">商品管理</h1>
        <a class="btn btn-primary" href="product-create.php">商品を登録</a>
    </div>
    <?php if ($errorMessage !== null): ?><div class="alert alert-danger"><?= h($errorMessage) ?></div><?php endif; ?>
    <?php if (isset($_GET['success'])): ?><div class="alert alert-success">商品を更新しました。</div><?php endif; ?>
    <form class="row g-2 mb-3" method="get">
        <div class="col-md-6"><input class="form-control" name="keyword" value="<?= h((string) ($_GET['keyword'] ?? '')) ?>" placeholder="商品名・カテゴリで検索"></div>
        <div class="col-auto"><button class="btn btn-outline-secondary" type="submit">検索</button></div>
    </form>
    <div class="card shadow-sm"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>ID</th><th>商品名</th><th>カテゴリ</th><th>価格</th><th>状態</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($result['items'] as $item): ?>
            <tr>
                <td><?= (int) $item['product_id'] ?></td>
                <td><?= h((string) $item['name']) ?></td>
                <td><?= h((string) $item['category_name']) ?></td>
                <td><?= number_format((float) $item['price']) ?>円</td>
                <td><?= h((string) $item['status']) ?></td>
                <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="product-images.php?id=<?= (int) $item['product_id'] ?>">画像</a> <a class="btn btn-sm btn-outline-secondary" href="product-price-history.php?id=<?= (int) $item['product_id'] ?>">価格履歴</a> <a class="btn btn-sm btn-outline-primary" href="product-edit.php?id=<?= (int) $item['product_id'] ?>">編集</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($result['items'])): ?><tr><td colspan="6" class="text-center py-4">商品がありません。</td></tr><?php endif; ?>
        </tbody>
    </table></div></div>
    <?php if ($result['total_pages'] > 1): ?><nav class="mt-3"><ul class="pagination">
        <?php for ($page = 1; $page <= $result['total_pages']; $page++): ?><li class="page-item <?= $page === $result['page'] ? 'active' : '' ?>"><a class="page-link" href="<?= h(pageUrl($page, (string) ($_GET['keyword'] ?? ''))) ?>"><?= $page ?></a></li><?php endfor; ?>
    </ul></nav><?php endif; ?>
</main>
</body>
</html>
