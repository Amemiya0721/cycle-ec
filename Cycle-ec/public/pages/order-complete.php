<?php

declare(strict_types=1);

$config = require __DIR__ . '/../../config/config.php';
$baseUrl = $config['app']['base_url'];

require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/UserAddress.php';
require_once __DIR__ . '/../../src/Order.php';

Auth::start();
$userId = Auth::requireLogin($baseUrl . 'pages/login.php');

if (!function_exists('h')) {
    function h(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}

$orderId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$order = null;

if ($orderId !== false && $orderId !== null && $orderId > 0) {
    try {
        // 本人の注文だけ表示する（他人の注文番号を指定しても見えない）
        $order = Order::findForUser($orderId, $userId);
    } catch (PDOException $exception) {
        error_log('Order fetch failed: ' . $exception->getMessage());
        http_response_code(500);
    }
}
if ($order === null && http_response_code() === 200) {
    http_response_code(404);
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/nav.php';
?>

<main>
    <div class="container py-5" style="max-width: 860px;">
        <?php if ($order === null): ?>
            <h1 class="h3">注文が見つかりません。</h1>
            <p><a href="products.php">商品一覧へ戻る</a></p>
        <?php else: ?>
            <div class="alert alert-success">
                <h1 class="h4">ご注文ありがとうございます</h1>
                <p class="mb-0">注文番号: <?= (int) $order['order_id'] ?></p>
            </div>

            <h2 class="h5 mt-4 mb-3">ご注文内容</h2>
            <ul class="list-group mb-4">
                <?php foreach ($order['items'] as $item): ?>
                    <li class="list-group-item d-flex justify-content-between">
                        <span><?= h((string) ($item['name'] ?? '（商品情報なし）')) ?></span>
                        <strong><?= number_format((float) $item['price']) ?>円</strong>
                    </li>
                <?php endforeach; ?>
                <li class="list-group-item d-flex justify-content-between bg-light">
                    <span>合計（税込）</span>
                    <strong><?= number_format((float) $order['total_price']) ?>円</strong>
                </li>
            </ul>

            <h2 class="h5 mb-3">お届け先</h2>
            <p>
                <?= h((string) $order['shipping_recipient_name']) ?> 様<br>
                〒<?= h(UserAddress::formatPostalCode((string) $order['shipping_postal_code'])) ?><br>
                <?= h((string) $order['shipping_prefecture'] . $order['shipping_city'] . $order['shipping_address_line']) ?>
                <?= h((string) ($order['shipping_building'] ?? '')) ?><br>
                TEL: <?= h((string) $order['shipping_phone_number']) ?>
            </p>

            <a class="btn btn-primary" href="products.php">商品一覧へ</a>
        <?php endif; ?>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
