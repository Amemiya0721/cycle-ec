<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../src/UserAddress.php';
require_once __DIR__ . '/../../../src/Order.php';
require_once __DIR__ . '/../includes/auth.php';

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

$orderId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$orderId || $orderId < 1) {
    http_response_code(400);
    exit('注文IDが正しくありません。');
}

try {
    $order = Order::findForAdmin($orderId);
} catch (PDOException $exception) {
    error_log('Admin order fetch failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('注文の取得に失敗しました。');
}
if ($order === null) {
    http_response_code(404);
    exit('注文が見つかりません。');
}

$adminTitle = '注文詳細';
$activeMenu = 'orders';
$breadcrumbs = [
    ['label' => '注文管理', 'url' => 'index.php'],
    ['label' => '注文 #' . $orderId],
];
require __DIR__ . '/../includes/header.php';
?>
<div class="admin-page-header"><div><h1>注文 #<?= (int) $order['order_id'] ?></h1><p class="text-muted mb-0">注文日時: <?= h((string) $order['ordered_at']) ?> ／ ステータス: <?= h((string) $order['status']) ?></p></div></div>

<div class="row g-3 mb-4">
    <div class="col-md-6"><div class="admin-card bg-white p-3 h-100">
        <h2 class="admin-section-title">購入者</h2>
        <p class="mb-1 fw-semibold"><?= h((string) $order['buyer_name']) ?> <small class="text-muted fw-normal">（ユーザーID: <?= (int) $order['user_id'] ?>）</small></p>
        <p class="mb-0"><?= h((string) $order['buyer_email']) ?></p>
    </div></div>
    <div class="col-md-6"><div class="admin-card bg-white p-3 h-100">
        <h2 class="admin-section-title">送付先</h2>
        <p class="mb-1 fw-semibold"><?= h((string) $order['shipping_recipient_name']) ?> 様</p>
        <p class="mb-1">〒<?= h(UserAddress::formatPostalCode((string) $order['shipping_postal_code'])) ?><br>
            <?= h((string) $order['shipping_prefecture'] . $order['shipping_city'] . $order['shipping_address_line']) ?><br>
            <?= h((string) ($order['shipping_building'] ?? '')) ?></p>
        <p class="mb-0">TEL: <?= h((string) $order['shipping_phone_number']) ?></p>
    </div></div>
</div>

<section class="admin-card bg-white">
    <div class="p-3 border-bottom"><h2 class="admin-section-title mb-0">注文商品</h2></div>
    <div class="table-responsive"><table class="table admin-table align-middle mb-0">
        <thead><tr><th>商品ID</th><th>商品名</th><th class="text-end">注文時価格</th></tr></thead>
        <tbody>
        <?php foreach ($order['items'] as $item): ?>
            <tr><td><?= (int) $item['product_id'] ?></td><td><?= h((string) ($item['name'] ?? '（商品情報なし）')) ?></td><td class="text-end"><?= number_format((float) $item['price']) ?>円</td></tr>
        <?php endforeach; ?>
        <tr class="table-light"><td colspan="2" class="fw-semibold">合計（税込）</td><td class="text-end fw-semibold"><?= number_format((float) $order['total_price']) ?>円</td></tr>
        </tbody>
    </table></div>
</section>
<div class="mt-3"><a class="btn btn-outline-secondary" href="index.php">注文一覧へ戻る</a></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
