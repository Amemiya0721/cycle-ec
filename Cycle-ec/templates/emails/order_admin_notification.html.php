<?php

declare(strict_types=1);

$e = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="ja">
<head><meta charset="UTF-8"><title>新しい注文</title></head>
<body>
    <h1>新しい注文を受け付けました</h1>
    <p>注文番号：<?= $e((string) $order['order_number']) ?></p>
    <p>購入者：<?= $e((string) $order['buyer_name']) ?>（<?= $e((string) $order['buyer_email']) ?>）</p>
    <ul>
        <?php foreach ($order['items'] as $item): ?>
            <li><?= $e((string) ($item['name'] ?? '（商品情報なし）')) ?> <?= number_format((float) ($item['price'] ?? 0), 2) ?>円</li>
        <?php endforeach; ?>
    </ul>
    <p>合計（税込）：<?= number_format((float) $order['total_price'], 2) ?>円</p>
    <p>送料<?= !empty($order['shipping_size']) ? '（' . $e((string) $order['shipping_size']) . '）' : '' ?>：<?= number_format((float) ($order['shipping_fee'] ?? 0), 2) ?>円</p>
    <p>入金状態：未入金（期限 <?= $e((string) $order['payment_due_at']) ?>）</p>
</body>
</html>
