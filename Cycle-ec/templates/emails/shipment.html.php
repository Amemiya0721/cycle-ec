<?php

declare(strict_types=1);

$e = static fn(string $value): string =>
    htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

$shipmentAddress = trim(
    (string) $order['shipping_prefecture']
    . (string) $order['shipping_city']
    . (string) $order['shipping_address_line']
    . ' '
    . (string) ($order['shipping_building'] ?? '')
);
?>
<!doctype html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>発送のお知らせ</title>
</head>
<body>
    <h1>OVERHAUL</h1>

    <p><?= $e((string) $order['buyer_name']) ?> 様</p>
    <p>ご注文いただいた商品を発送いたしました。</p>

    <table cellpadding="4" style="border-collapse:collapse">
        <tr>
            <th align="left">注文番号</th>
            <td>#<?= $e((string) $order['order_number']) ?></td>
        </tr>
        <tr>
            <th align="left">配送会社</th>
            <td><?= $e((string) $order['shipping_carrier']) ?></td>
        </tr>
        <tr>
            <th align="left">追跡番号</th>
            <td><?= $e((string) $order['tracking_number']) ?></td>
        </tr>
        <?php if ($trackingUrl !== null): ?>
            <tr>
                <th align="left">配送状況</th>
                <td>
                    <a href="<?= $e($trackingUrl) ?>">配送状況を確認する</a>
                </td>
            </tr>
        <?php endif; ?>
    </table>

    <h2>お届け先</h2>
    <p>
        <?= $e((string) $order['shipping_recipient_name']) ?> 様<br>
        〒<?= $e((string) $order['shipping_postal_code']) ?><br>
        <?= $e($shipmentAddress) ?>
    </p>

    <h2>ご注文内容</h2>
    <ul>
        <?php foreach ($order['items'] as $item): ?>
            <li>
                <?= $e((string) ($item['name'] ?? '（商品情報なし）')) ?>
                <?= number_format((float) $item['price'], 2) ?>円
            </li>
        <?php endforeach; ?>
    </ul>

    <p>
        商品小計：<?= number_format((float) ($order['subtotal'] ?? $order['total_price']), 2) ?>円<br>
        送料<?= !empty($order['shipping_size']) ? '（' . $e((string) $order['shipping_size']) . '）' : '' ?>：<?= number_format((float) ($order['shipping_fee'] ?? 0), 2) ?>円<br>
        <strong>合計：<?= number_format((float) $order['total_price'], 2) ?>円</strong>
    </p>

    <p>
        商品がお手元に届きましたら、下記のリンクから受取確認をお願いいたします。<br>
        <small>（ログインが必要です。ご注文されたご本人のみ操作できます）</small>
    </p>

    <p>
        <a
            href="<?= $e($receiptUrl) ?>"
            style="display:inline-block;padding:10px 20px;background:#198754;color:#fff;text-decoration:none;border-radius:4px"
        >受取確認をする</a>
    </p>

    <p><small>このメールは送信専用です。</small></p>
</body>
</html>
