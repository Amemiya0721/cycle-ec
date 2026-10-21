<?php

declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../src/Product.php';
require_once __DIR__ . '/../../src/Auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? null;
    if (!is_string($csrfToken) || !Auth::verifyCsrf($csrfToken)) {
        http_response_code(403);
        exit('不正なリクエストです。');
    }

    $productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    $action = $_POST['action'] ?? 'add';

    if ($productId !== false && $productId !== null && $productId > 0) {
        if ($action === 'remove') {
            $cart = $_SESSION['cart'] ?? [];
            $_SESSION['cart'] = array_values(array_filter(
                $cart,
                static fn ($id): bool => !is_numeric($id) || (int) $id !== $productId
            ));
        } elseif ($action === 'add') {
            $_SESSION['cart'] = array_values(array_unique(array_merge(
                $_SESSION['cart'] ?? [],
                [$productId]
            )));
        }
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
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/nav.php';
?>
<main class="container py-5">
    <h1 class="h3 mb-4">カート</h1>
    <?php if (!$items): ?>
        <p>カートに商品がありません。</p>
    <?php else: ?>
        <div class="list-group mb-4">
            <?php foreach ($items as $item): ?>
                <div class="list-group-item d-flex align-items-center gap-3 flex-wrap">
                    <div class="flex-shrink-0 bg-light d-flex align-items-center justify-content-center" style="width:96px;height:96px">
                        <?php $imageUrl = Product::publicImageUrl($item['images'][0]['image_url'] ?? null); ?>
                        <?php if ($imageUrl !== null): ?>
                            <img src="<?= h($imageUrl) ?>" alt="<?= h((string) $item['name']) ?>" class="img-fluid" style="max-height:96px">
                        <?php else: ?>
                            <span aria-hidden="true">&#128690;</span>
                        <?php endif; ?>
                    </div>
                    <span class="flex-grow-1"><?= h((string) $item['name']) ?></span>
                    <strong><?= number_format((float) $item['price']) ?>円</strong>
                    <form method="post" action="cart.php" class="ms-auto">
                        <input type="hidden" name="csrf_token" value="<?= h(Auth::csrfToken()) ?>">
                        <input type="hidden" name="action" value="remove">
                        <input type="hidden" name="product_id" value="<?= (int) $item['product_id'] ?>">
                        <button class="btn btn-sm btn-outline-danger" type="submit" aria-label="<?= h((string) $item['name']) ?>をカートから削除">削除</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
        <a class="btn btn-primary" href="checkout.php">注文手続きへ</a>
    <?php endif; ?>
    <a class="btn btn-outline-secondary" href="products.php">商品一覧へ</a>
</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
