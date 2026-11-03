<?php

declare(strict_types=1);

$lines = [
    '新しい注文を受け付けました。',
    '注文番号：' . (string) $order['order_number'],
    '購入者：' . (string) $order['buyer_name'] . '（' . (string) $order['buyer_email'] . '）',
    '',
    '■ 注文商品',
];
foreach ($order['items'] as $item) {
    $lines[] = '・' . (string) ($item['name'] ?? '（商品情報なし）')
        . '　' . number_format((float) ($item['price'] ?? 0), 2) . '円';
}
$lines[] = '商品小計：' . number_format((float) ($order['subtotal'] ?? $order['total_price']), 2) . '円';
$lines[] = '送料：' . number_format((float) ($order['shipping_fee'] ?? 0), 2) . '円';
$lines[] = '合計（税込）：' . number_format((float) $order['total_price'], 2) . '円';
$lines[] = '入金状態：未入金（期限 ' . (string) $order['payment_due_at'] . '）';

echo implode("\n", $lines);
