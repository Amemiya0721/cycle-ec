<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../src/UserAddress.php';
require_once __DIR__ . '/../../../src/Order.php';
require_once __DIR__ . '/../../../src/OrderMailService.php';
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

$hasShipment = !empty($order['tracking_number']);
$trackingUrl = $hasShipment
    ? OrderMailService::trackingUrl((string) $order['shipping_carrier'], (string) $order['tracking_number'])
    : null;
$canShip = $order['status'] === Order::STATUS_ORDERED;
$canResend = $order['status'] === Order::STATUS_SHIPPED && $hasShipment;

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

<?php if ($hasShipment || $canShip): ?>
<div class="admin-card bg-white p-3 mb-4">
    <h2 class="admin-section-title">発送情報</h2>
    <?php if ($hasShipment): ?>
        <dl class="row mb-2">
            <dt class="col-sm-3 col-md-2">配送会社</dt><dd class="col-sm-9 col-md-10"><?= h((string) $order['shipping_carrier']) ?></dd>
            <dt class="col-sm-3 col-md-2">追跡番号</dt>
            <dd class="col-sm-9 col-md-10">
                <?= h((string) $order['tracking_number']) ?>
                <?php if ($trackingUrl !== null): ?><a class="ms-2 small" href="<?= h($trackingUrl) ?>" target="_blank" rel="noopener noreferrer">配送状況を確認</a><?php endif; ?>
            </dd>
            <dt class="col-sm-3 col-md-2">発送日時</dt><dd class="col-sm-9 col-md-10"><?= h((string) ($order['shipped_at'] ?? '')) ?></dd>
            <dt class="col-sm-3 col-md-2">通知メール</dt>
            <dd class="col-sm-9 col-md-10">
                <?php if ($order['shipment_mail_sent_at'] !== null): ?>
                    送信済み（<?= h((string) $order['shipment_mail_sent_at']) ?>）
                <?php else: ?>
                    <span class="text-danger">未送信</span>
                <?php endif; ?>
            </dd>
            <dt class="col-sm-3 col-md-2">受取確認</dt>
            <dd class="col-sm-9 col-md-10">
                <?= $order['received_at'] !== null ? h((string) $order['received_at']) . ' に確認済み' : '<span class="text-muted">未確認</span>' ?>
            </dd>
        </dl>
    <?php else: ?>
        <p class="text-muted">まだ発送されていません。</p>
    <?php endif; ?>
    <?php if ($canShip): ?><a class="btn btn-sm btn-success" href="ship.php?id=<?= (int) $order['order_id'] ?>">発送する</a><?php endif; ?>
    <?php if ($canResend): ?><a class="btn btn-sm btn-outline-primary" href="ship.php?id=<?= (int) $order['order_id'] ?>">通知メールを再送する</a><?php endif; ?>
</div>
<?php endif; ?>

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