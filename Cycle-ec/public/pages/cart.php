<?php

declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../src/Product.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    if ($productId && $productId > 0) {
        $_SESSION['cart'] = array_values(array_unique(array_merge($_SESSION['cart'] ?? [], [$productId])));
    }
    header('Location: cart.php');
    exit;
}

$cartIds = array_values(array_filter($_SESSION['cart'] ?? [], 'is_numeric'));
$items = [];
foreach ($cartIds as $productId) {
    $item = Product::findById((int) $productId);
    if ($item !== null) {
        $items[] = $item;
    }
}

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>カート | OVERHAUL</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<main class="container py-5">
    <h1 class="h3 mb-4">カート</h1>
    <?php if (!$items): ?>
        <p>カートに商品がありません。</p>
    <?php else: ?>
        <div class="list-group mb-4">
            <?php foreach ($items as $item): ?>
                <div class="list-group-item d-flex align-items-center gap-3">
                    <div class="flex-shrink-0 bg-light d-flex align-items-center justify-content-center" style="width:96px;height:96px">
                        <?php if (!empty($item['images'][0]['image_url'])): ?>
                            <img src="<?= h((string) $item['images'][0]['image_url']) ?>" alt="<?= h((string) $item['name']) ?>" class="img-fluid" style="max-height:96px">
                        <?php else: ?>
                            <span aria-hidden="true">&#128690;</span>
                        <?php endif; ?>
                    </div>
                    <span class="flex-grow-1"><?= h((string) $item['name']) ?></span>
                    <strong><?= number_format((float) $item['price']) ?>円</strong>
                </div>
            <?php endforeach; ?>
        </div>
        <a class="btn btn-primary" href="checkout.php">注文手続きへ</a>
    <?php endif; ?>
    <a class="btn btn-outline-secondary" href="products.php">商品一覧へ</a>
</main>
</body>
</html>
