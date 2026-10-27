<?php

declare(strict_types=1);

// 配置想定: <project>/public/pages/receive.php （src は ../../src）
require_once __DIR__ . '/../../src/Order.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// トークン入りURLが Referer 経由で外部へ漏れないようにする
header('Referrer-Policy: no-referrer');

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// ★ 前提: ログイン中の購入者IDは $_SESSION['user_id']、ログイン画面は login.php
$userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
$loginUrl = 'login.php';

$source = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
$orderIdRaw = $source[$_SERVER['REQUEST_METHOD'] === 'POST' ? 'order_id' : 'order'] ?? null;
$orderId = is_string($orderIdRaw)
    ? filter_var($orderIdRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;
$token = is_string($source['token'] ?? null) ? $source['token'] : '';
$tokenOk = preg_match('/^[0-9a-f]{64}$/D', $token) === 1;

$view = 'invalid'; // invalid | login | confirm | done | failed
$order = null;

if ($orderId === false || !$tokenOk) {
    $view = 'invalid';
} elseif ($userId < 1) {
    $view = 'login';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? null;
    if (!is_string($csrf) || empty($_SESSION['receipt_csrf']) || !hash_equals((string) $_SESSION['receipt_csrf'], $csrf)) {
        $view = 'failed';
    } else {
        unset($_SESSION['receipt_csrf']);
        try {
            $view = Order::confirmReceipt((int) $orderId, $userId, $token) ? 'done' : 'failed';
        } catch (Throwable $exception) {
            error_log('Receipt confirmation failed.');
            $view = 'failed';
        }
    }
} else {
    // GET では状態を変えない（メールのリンクプレビュー等による誤確定を防ぐ）
    try {
        $found = Order::findForUser((int) $orderId, $userId);
    } catch (Throwable $exception) {
        error_log('Receipt page load failed.');
        $found = null;
    }
    if ($found !== null
        && $found['status'] === Order::STATUS_SHIPPED
        && is_string($found['receipt_token_hash'] ?? null)
        && hash_equals($found['receipt_token_hash'], hash('sha256', $token))
    ) {
        $order = $found;
        $_SESSION['receipt_csrf'] = bin2hex(random_bytes(32));
        $view = 'confirm';
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>受取確認</title>
    <!-- TODO: サイト共通のヘッダー/フッター・CSSに差し替える -->
    <style>body{font-family:sans-serif;max-width:560px;margin:2rem auto;padding:0 1rem;line-height:1.7}button{padding:.6rem 1.2rem;font-size:1rem}</style>
</head>
<body>
<h1>受取確認</h1>
<?php if ($view === 'confirm' && $order !== null): ?>
    <p>注文 #<?= (int) $order['order_id'] ?> の商品を受け取りましたか？</p>
    <ul>
        <?php foreach ($order['items'] as $item): ?><li><?= h((string) ($item['name'] ?? '(商品情報なし)')) ?></li><?php endforeach; ?>
    </ul>
    <p>配送: <?= h((string) $order['shipping_carrier']) ?> / 追跡番号 <?= h((string) $order['tracking_number']) ?></p>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= h((string) $_SESSION['receipt_csrf']) ?>">
        <input type="hidden" name="order_id" value="<?= (int) $order['order_id'] ?>">
        <input type="hidden" name="token" value="<?= h($token) ?>">
        <button type="submit">受け取りました</button>
    </form>
<?php elseif ($view === 'done'): ?>
    <p>受取確認を受け付けました。ご利用ありがとうございました。</p>
<?php elseif ($view === 'login'): ?>
    <p>受取確認にはログインが必要です。ログイン後、メールのリンクをもう一度開いてください。</p>
    <p><a href="<?= h($loginUrl) ?>">ログインする</a></p>
<?php else: ?>
    <p>このリンクは無効か、すでに受取確認済みです。ご本人の注文であることもご確認ください。</p>
<?php endif; ?>
</body>
</html>
