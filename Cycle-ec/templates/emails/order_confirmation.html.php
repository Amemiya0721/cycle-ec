<?php

declare(strict_types=1);

$e = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="ja">
<head><meta charset="UTF-8"><title>ご注文ありがとうございます</title></head>
<body>
    <h1>OVERHAUL</h1>
    <p><?= $e((string) $order['buyer_name']) ?> 様</p>
    <p>ご注文ありがとうございます。以下の内容でご注文を承りました。</p>
    <p>注文番号：<?= $e((string) $order['order_number']) ?></p>
    <h2>ご注文内容</h2>
    <ul>
        <?php foreach ($order['items'] as $item): ?>
            <li><?= $e((string) ($item['name'] ?? '（商品情報なし）')) ?> <?= number_format((float) ($item['price'] ?? 0), 2) ?>円</li>
        <?php endforeach; ?>
    </ul>
    <p>
        商品小計：<?= number_format((float) ($order['subtotal'] ?? $order['total_price']), 2) ?>円<br>
        送料<?= !empty($order['shipping_size']) ? '（' . $e((string) $order['shipping_size']) . '）' : '' ?>：<?= number_format((float) ($order['shipping_fee'] ?? 0), 2) ?>円<br>
        <strong>合計（税込）：<?= number_format((float) $order['total_price'], 2) ?>円</strong>
    </p>
    <h2>お振込先</h2>
    <p>
        銀行名：<?= $e((string) $bankTransfer['bank_name']) ?><br>
        支店名：<?= $e((string) $bankTransfer['branch_name']) ?><br>
        口座種別：<?= $e((string) $bankTransfer['account_type']) ?><br>
        口座番号：<?= $e((string) $bankTransfer['account_number']) ?><br>
        口座名義：<?= $e((string) $bankTransfer['account_holder']) ?><br>
        お支払い期限：<?= $e((string) $order['payment_due_at']) ?>
    </p>
    <p>入金確認後に発送いたします。注文番号を振込名義に添えて期限までにお振込みください。</p>
    <h2>お届け先</h2>
    <p>
        <?= $e((string) $order['shipping_recipient_name']) ?> 様<br>
        〒<?= $e((string) $order['shipping_postal_code']) ?><br>
        <?= $e((string) $order['shipping_prefecture'] . $order['shipping_city'] . $order['shipping_address_line'] . ' ' . ($order['shipping_building'] ?? '')) ?>
    </p>
    <p>このメールは送信専用です。</p>
</body>
</html>
