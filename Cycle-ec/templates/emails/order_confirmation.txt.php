<?php

declare(strict_types=1);

$lines = [
    'OVERHAUL',
    '',
    (string) $order['buyer_name'] . ' 様',
    '',
    'ご注文ありがとうございます。以下の内容でご注文を承りました。',
    '注文番号：' . (string) $order['order_number'],
    '',
    '■ ご注文内容',
];
foreach ($order['items'] as $item) {
    $lines[] = '・' . (string) ($item['name'] ?? '（商品情報なし）')
        . '　' . number_format((float) ($item['price'] ?? 0), 2) . '円';
}
$lines[] = '商品小計：' . number_format((float) ($order['subtotal'] ?? $order['total_price']), 2) . '円';
$lines[] = '送料' . (!empty($order['shipping_size']) ? '（' . (string) $order['shipping_size'] . '）' : '')
    . '：' . number_format((float) ($order['shipping_fee'] ?? 0), 2) . '円';
$lines[] = '合計（税込）：' . number_format((float) $order['total_price'], 2) . '円';
$lines[] = '';
$lines[] = '■ お振込先';
$lines[] = '銀行名：' . (string) $bankTransfer['bank_name'];
$lines[] = '支店名：' . (string) $bankTransfer['branch_name'];
$lines[] = '口座種別：' . (string) $bankTransfer['account_type'];
$lines[] = '口座番号：' . (string) $bankTransfer['account_number'];
$lines[] = '口座名義：' . (string) $bankTransfer['account_holder'];
$lines[] = 'お支払い期限：' . (string) $order['payment_due_at'];
$lines[] = '入金確認後に発送いたします。注文番号を振込名義に添えて期限までにお振込みください。';
$lines[] = '';
$lines[] = '■ お届け先';
$lines[] = (string) $order['shipping_recipient_name'] . ' 様';
$lines[] = '〒' . (string) $order['shipping_postal_code'];
$lines[] = (string) $order['shipping_prefecture']
    . (string) $order['shipping_city']
    . (string) $order['shipping_address_line']
    . ' ' . (string) ($order['shipping_building'] ?? '');
$lines[] = '';
$lines[] = 'このメールは送信専用です。';

echo implode("\n", $lines);
