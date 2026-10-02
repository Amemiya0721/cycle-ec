<?php

declare(strict_types=1);
require_once __DIR__ . '/../../../src/Product.php';
require_once __DIR__ . '/../includes/auth.php';
$productId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$productId || $productId < 1) { http_response_code(400); exit('商品IDが正しくありません。'); }

// 個別画像の削除（product-edit.php の削除リンクからも呼ばれる）
$deleteImageId = filter_input(INPUT_GET, 'delete', FILTER_VALIDATE_INT);
if ($deleteImageId) {
    try {
        Product::deleteImage($deleteImageId, $productId);
    } catch (PDOException $exception) {
        error_log('Product image delete failed: ' . $exception->getMessage());
    }
    header('Location: product-images.php?id=' . $productId);
    exit;
}

try { $product = Product::findById($productId); } catch (PDOException $exception) { http_response_code(500); exit('商品情報の取得に失敗しました。'); }
if ($product === null) { http_response_code(404); exit('商品が見つかりません。'); }
function h(?string $value): string { return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8'); }
?><!DOCTYPE html><html lang="ja"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>商品画像 | OVERHAUL</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light"><main class="container py-4">

<div class="d-flex justify-content-between mb-4">
    <h1 class="h3">商品画像: <?= h((string) $product['name']) ?></h1>
    <a class="btn btn-outline-secondary" href="product-edit.php?id=<?= (int) $productId ?>">商品編集へ</a>
</div>

<h2 class="h5 mb-3">メイン画像</h2>
<div class="row g-3 mb-5">
    <?php foreach ($product['images'] as $image): ?>
        <?php $imageUrl = Product::publicImageUrl($image['image_url'] ?? null); ?>
        <div class="col-6 col-md-3">
            <div class="card">
                <?php if ($imageUrl !== null): ?><img class="card-img-top" src="<?= h($imageUrl) ?>" alt="<?= h((string) $product['name']) ?>"><?php endif; ?>
                <div class="card-body d-flex justify-content-between align-items-center">
                    <small>表示順: <?= (int) $image['sort_order'] ?></small>
                    <a class="btn btn-sm btn-outline-danger" href="?id=<?= (int) $productId ?>&delete=<?= (int) $image['image_id'] ?>" onclick="return confirm('この画像を削除しますか？')">削除</a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if (empty($product['images'])): ?><p>登録画像はありません。</p><?php endif; ?>
</div>

<h2 class="h5 mb-3">傷・使用感の写真（商品状態タブに表示）</h2>
<div class="row g-3">
    <?php foreach ($product['condition_images'] as $image): ?>
        <?php $imageUrl = Product::publicImageUrl($image['image_url'] ?? null); ?>
        <div class="col-6 col-md-3">
            <div class="card">
                <?php if ($imageUrl !== null): ?><img class="card-img-top" src="<?= h($imageUrl) ?>" alt="傷・使用感の写真"><?php endif; ?>
                <div class="card-body d-flex justify-content-between align-items-center">
                    <small>表示順: <?= (int) $image['sort_order'] ?></small>
                    <a class="btn btn-sm btn-outline-danger" href="?id=<?= (int) $productId ?>&delete=<?= (int) $image['image_id'] ?>" onclick="return confirm('この画像を削除しますか？')">削除</a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if (empty($product['condition_images'])): ?><p>登録画像はありません。</p><?php endif; ?>
</div>

</main></body></html>
