<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../src/Order.php';
require_once __DIR__ . '/../../../src/OrderMailService.php';
require_once __DIR__ . '/../includes/auth.php';

if (!function_exists('h')) {
    function h(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}

function shipFlashAndRedirect(string $type, string $text, string $url = 'index.php'): void
{
    $_SESSION['order_status_flash'] = ['type' => $type, 'text' => $text];
    header('Location: ' . $url, true, 303);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$idValue = $method === 'POST' ? ($_POST['order_id'] ?? null) : ($_GET['id'] ?? null);
$orderId = is_string($idValue)
    ? filter_var($idValue, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;
if ($orderId === false) {
    shipFlashAndRedirect('danger', '注文IDが正しくありません。');
}
$orderId = (int) $orderId;
$selfUrl = 'ship.php?id=' . $orderId;

// 発送フロー専用のCSRFトークン（確認画面→確定の間は使い回し、確定・再送後に再生成）
if (empty($_SESSION['order_ship_csrf'])) {
    $_SESSION['order_ship_csrf'] = bin2hex(random_bytes(32));
}

$carrier = '';
$tracking = '';
$errors = [];
$step = 'input';

if ($method === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? null;
    if (!is_string($csrfToken) || !hash_equals((string) $_SESSION['order_ship_csrf'], $csrfToken)) {
        shipFlashAndRedirect('danger', 'リクエストを検証できませんでした。もう一度やり直してください。', $selfUrl);
    }

    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    $carrier = is_string($_POST['carrier'] ?? null) ? $_POST['carrier'] : '';
    $tracking = Order::normalizeTrackingNumber(is_string($_POST['tracking_number'] ?? null) ? $_POST['tracking_number'] : '');

    if ($action === 'confirm' || $action === 'commit') {
        if (!in_array($carrier, Order::CARRIERS, true)) {
            $errors[] = '配送会社を選択してください。';
        }
        if (!Order::isValidTrackingNumber($tracking)) {
            $errors[] = '追跡番号は半角英数字8〜20文字で入力してください（ハイフン・空白は自動で除去されます）。';
        }
    }

    if ($action === 'confirm' && $errors === []) {
        $step = 'confirm';
    } elseif ($action === 'commit' && $errors === []) {
        try {
            $token = Order::ship($orderId, $carrier, $tracking);
        } catch (Throwable $exception) {
            error_log('Admin order ship failed.');
            shipFlashAndRedirect('danger', '発送処理に失敗しました。注文は変更されていません。', $selfUrl);
        }
        $_SESSION['order_ship_csrf'] = bin2hex(random_bytes(32));

        if ($token === null) {
            shipFlashAndRedirect('danger', '注文が見つからないか、既に状態が変更されています。最新の一覧を確認してください。');
        }
        // DBは確定済み。メール失敗でも発送済みは巻き戻さず、一覧から再送できる。
        if (OrderMailService::sendShipmentNotice($orderId, $token)) {
            shipFlashAndRedirect('success', '注文 #' . $orderId . ' を発送済みにし、通知メールを送信しました。');
        }
        shipFlashAndRedirect('warning', '注文 #' . $orderId . ' を発送済みにしましたが、通知メールの送信に失敗しました。詳細画面から再送してください。');
    } elseif ($action === 'resend') {
        $token = null;
        try {
            $token = Order::reissueReceiptToken($orderId);
        } catch (Throwable $exception) {
            error_log('Admin order token reissue failed.');
        }
        $_SESSION['order_ship_csrf'] = bin2hex(random_bytes(32));

        if ($token === null) {
            shipFlashAndRedirect('danger', '再送できる状態の注文ではありません。', $selfUrl);
        }
        if (OrderMailService::sendShipmentNotice($orderId, $token)) {
            shipFlashAndRedirect('success', '注文 #' . $orderId . ' の通知メールを再送しました。');
        }
        shipFlashAndRedirect('warning', '通知メールの再送に失敗しました。メール設定を確認してください。', $selfUrl);
    }
    // action === 'back' または入力エラー → 入力画面を再表示
}

$order = null;
try {
    $order = Order::findForAdmin($orderId);
} catch (PDOException $exception) {
    error_log('Admin order ship load failed: ' . $exception->getMessage());
    shipFlashAndRedirect('danger', '注文の取得に失敗しました。');
}
if ($order === null) {
    shipFlashAndRedirect('danger', '注文が見つかりません。');
}

$actionMessage = $_SESSION['order_status_flash'] ?? null;
unset($_SESSION['order_status_flash']);
if (!is_array($actionMessage)) {
    $actionMessage = null;
}

$csrf = (string) $_SESSION['order_ship_csrf'];
$isOrdered = $order['status'] === Order::STATUS_ORDERED;
$isShippedWithTracking = $order['status'] === Order::STATUS_SHIPPED && !empty($order['tracking_number']);

$adminTitle = '発送処理';
$activeMenu = 'orders';
$breadcrumbs = [['label' => '注文管理'], ['label' => '発送処理']];
require __DIR__ . '/../includes/header.php';
?>
<div class="admin-page-header">
    <div>
        <h1>発送処理 <small class="text-muted">注文 #<?= (int) $order['order_id'] ?></small></h1>
        <p class="text-muted mb-0">配送情報を入力し、内容を確認してから発送を確定します。</p>
    </div>
    <a class="btn btn-sm btn-outline-secondary" href="index.php">一覧へ戻る</a>
</div>

<?php if ($actionMessage !== null): ?><div class="alert alert-<?= h((string) $actionMessage['type']) ?>" role="status"><?= h((string) $actionMessage['text']) ?></div><?php endif; ?>
<?php if ($errors !== []): ?>
    <div class="alert alert-danger" role="alert">
        <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="admin-card bg-white p-3 mb-3">
    <h2 class="h6">注文内容</h2>
    <dl class="row mb-0 small">
        <dt class="col-sm-2">ステータス</dt>
        <dd class="col-sm-10"><?= h((string) $order['status']) ?></dd>
        <dt class="col-sm-2">購入者</dt>
        <dd class="col-sm-10"><?= h((string) $order['buyer_name']) ?>（<?= h((string) $order['buyer_email']) ?>）</dd>
        <dt class="col-sm-2">送付先</dt>
        <dd class="col-sm-10">
            <?= h((string) $order['shipping_recipient_name']) ?> 様 / TEL <?= h((string) $order['shipping_phone_number']) ?><br>
            〒<?= h((string) $order['shipping_postal_code']) ?>
            <?= h((string) $order['shipping_prefecture'] . $order['shipping_city'] . $order['shipping_address_line']) ?>
            <?= h((string) $order['shipping_building']) ?>
        </dd>
        <dt class="col-sm-2">商品</dt>
        <dd class="col-sm-10">
            <?php foreach ($order['items'] as $item): ?>
                <?= h((string) ($item['name'] ?? '(商品情報なし)')) ?> — <?= number_format((float) $item['price']) ?>円<br>
            <?php endforeach; ?>
            <strong>合計 <?= number_format((float) $order['total_price']) ?>円</strong>
        </dd>
    </dl>
</div>

<?php if ($isOrdered && $step === 'input'): ?>
    <form method="post" class="admin-card bg-white p-3" novalidate>
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
        <input type="hidden" name="order_id" value="<?= (int) $order['order_id'] ?>">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label" for="carrier">配送会社</label>
                <select class="form-select" id="carrier" name="carrier" required>
                    <option value="">選択してください</option>
                    <?php foreach (Order::CARRIERS as $c): ?>
                        <option value="<?= h($c) ?>" <?= $carrier === $c ? 'selected' : '' ?>><?= h($c) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label" for="tracking_number">追跡番号</label>
                <input class="form-control" id="tracking_number" name="tracking_number" maxlength="30" inputmode="latin" autocomplete="off" value="<?= h($tracking) ?>" required>
            </div>
        </div>
        <div class="mt-3">
            <button class="btn btn-primary" type="submit" name="action" value="confirm">確認画面へ</button>
        </div>
    </form>

<?php elseif ($isOrdered && $step === 'confirm'): ?>
    <form method="post" class="admin-card bg-white p-3">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
        <input type="hidden" name="order_id" value="<?= (int) $order['order_id'] ?>">
        <input type="hidden" name="carrier" value="<?= h($carrier) ?>">
        <input type="hidden" name="tracking_number" value="<?= h($tracking) ?>">
        <h2 class="h6">以下の内容で発送を確定します</h2>
        <dl class="row">
            <dt class="col-sm-2">配送会社</dt>
            <dd class="col-sm-10"><?= h($carrier) ?></dd>
            <dt class="col-sm-2">追跡番号</dt>
            <dd class="col-sm-10"><?= h($tracking) ?></dd>
        </dl>
        <p class="small text-muted">
            確定すると、注文は「発送済み」になり、購入者（<?= h((string) $order['buyer_email']) ?>）へ追跡情報と受取確認リンクを記載したメールが送信されます。確定後は取り消せません。
        </p>
        <button class="btn btn-success" type="submit" name="action" value="commit">発送を確定する</button>
        <button class="btn btn-outline-secondary" type="submit" name="action" value="back" formnovalidate>修正する</button>
    </form>

<?php elseif ($isShippedWithTracking): ?>
    <div class="admin-card bg-white p-3">
        <h2 class="h6">発送情報</h2>
        <dl class="row small">
            <dt class="col-sm-2">配送会社</dt>
            <dd class="col-sm-10"><?= h((string) $order['shipping_carrier']) ?></dd>
            <dt class="col-sm-2">追跡番号</dt>
            <dd class="col-sm-10"><?= h((string) $order['tracking_number']) ?></dd>
            <dt class="col-sm-2">発送日時</dt>
            <dd class="col-sm-10"><?= h((string) $order['shipped_at']) ?></dd>
            <dt class="col-sm-2">通知メール</dt>
            <dd class="col-sm-10"><?= $order['shipment_mail_sent_at'] !== null ? '送信済み（' . h((string) $order['shipment_mail_sent_at']) . '）' : '<span class="text-danger">未送信（送信失敗）</span>' ?></dd>
        </dl>
        <form method="post" class="m-0" onsubmit="return confirm('通知メールを再送します。以前のメールの受取確認リンクは無効になります。よろしいですか？');">
            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
            <input type="hidden" name="order_id" value="<?= (int) $order['order_id'] ?>">
            <button class="btn btn-sm btn-outline-primary" type="submit" name="action" value="resend">通知メールを再送する</button>
        </form>
    </div>

<?php else: ?>
    <div class="alert alert-secondary">この注文（<?= h((string) $order['status']) ?>）は発送処理の対象ではありません。</div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>