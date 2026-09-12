<?php

declare(strict_types=1);

$config = require __DIR__ . '/../../config/config.php';
$baseUrl = $config['app']['base_url'];

require_once __DIR__ . '/../../src/Product.php';

$productId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$product = null;
$errorMessage = null;

if ($productId === false || $productId === null || $productId < 1) {
    http_response_code(400);
    $errorMessage = '商品IDが正しくありません。';
} else {
    try {
        $product = Product::findById($productId);
    } catch (PDOException $exception) {
        http_response_code(500);
        $errorMessage = '商品の取得に失敗しました。';
    }

    if ($product === null && $errorMessage === null) {
        http_response_code(404);
        $errorMessage = '商品が見つかりません。';
    }
}

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

$pageCss = 'products.css';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/nav.php';
?>

<main class="container py-5">
    <?php if ($errorMessage !== null): ?>
        <h1 class="h3"><?= h($errorMessage) ?></h1>
        <p><a href="<?= h($baseUrl) ?>pages/products.php">商品一覧へ戻る</a></p>
    <?php else: ?>
        <div class="mb-4">
            <a href="<?= h($baseUrl) ?>pages/products.php">商品一覧へ戻る</a>
        </div>

        <div class="row g-4">
            <div class="col-lg-7">
                <?php if (!empty($product['images'])): ?>
                    <div class="row g-3">
                        <?php foreach ($product['images'] as $image): ?>
                            <div class="col-6">
                                <img
                                    src="<?= h((string) $image['image_url']) ?>"
                                    alt="<?= h((string) $product['name']) ?>"
                                    class="img-fluid"
                                >
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="prod-thumb">
                        <span class="thumb-icon" aria-hidden="true">&#128690;</span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="col-lg-5">
                <p><?= h((string) $product['category_name']) ?></p>
                <h1 class="h2"><?= h((string) $product['name']) ?></h1>
                <p class="h4"><?= number_format((float) $product['price']) ?>円（税込）</p>
                <p><?= nl2br(h((string) ($product['description'] ?? ''))) ?></p>
                <dl>
                    <dt>商品状態</dt>
                    <dd><?= h((string) $product['product_condition']) ?></dd>
                </dl>
                <?php if (in_array((string) ($product['status'] ?? ''), ['売切れ', 'SOLD'], true)): ?>
                    <p class="status-sold">SOLD</p>
                <?php else: ?>
                    <form method="post" action="cart.php">
                        <input type="hidden" name="product_id" value="<?= (int) $product['product_id'] ?>">
                        <button class="btn btn-primary" type="submit">カートに入れる</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
