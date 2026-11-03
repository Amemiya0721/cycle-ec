<?php

declare(strict_types=1);

$shipmentAddress = trim(
    (string) $order['shipping_prefecture']
    . (string) $order['shipping_city']
    . (string) $order['shipping_address_line']
    . ' '
    . (string) ($order['shipping_building'] ?? '')
);

$lines = [
    'OVERHAUL',
    '',
    (string) $order['buyer_name'] . ' 様',
    '',
    'ご注文いただいた商品を発送いたしました。',
    '',
    '■ 注文番号：#' . (string) $order['order_number'],
    '■ 配送会社：' . (string) $order['shipping_carrier'],
    '■ 追跡番号：' . (string) $order['tracking_number'],
];

if ($trackingUrl !== null) {
    $lines[] = '■ 配送状況の確認：' . $trackingUrl;
}

$lines[] = '';
$lines[] = '■ お届け先';
$lines[] = (string) $order['shipping_recipient_name'] . ' 様';
$lines[] = '〒' . (string) $order['shipping_postal_code'];
$lines[] = $shipmentAddress;

$lines[] = '';
$lines[] = '■ ご注文内容';

foreach ($order['items'] as $item) {
    $lines[] = '・'
        . (string) ($item['name'] ?? '（商品情報なし）')
        . '　'
        . number_format((float) $item['price'], 2)
        . '円';
}

$lines[] = '商品小計：' . number_format((float) ($order['subtotal'] ?? $order['total_price']), 2) . '円';
$shippingSize = trim((string) ($order['shipping_size'] ?? ''));
$lines[] = '送料' . ($shippingSize !== '' ? '（' . $shippingSize . '）' : '')
    . '：' . number_format((float) ($order['shipping_fee'] ?? 0), 2) . '円';
$lines[] = '合計：' . number_format((float) $order['total_price'], 2) . '円';
$lines[] = '';
$lines[] = '商品がお手元に届きましたら、下記のリンクから受取確認をお願いいたします。';
$lines[] = '（ログインが必要です。ご注文されたご本人のみ操作できます）';
$lines[] = $receiptUrl;
$lines[] = '';
$lines[] = 'このメールは送信専用です。';

echo implode("\n", $lines);
