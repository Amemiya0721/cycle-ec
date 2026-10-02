<?php

declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/Order.php';
require_once __DIR__ . '/../../src/OrderMailService.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
if (empty($_SESSION['checkout_token'])) {
    $_SESSION['checkout_token'] = bin2hex(random_bytes(32));
}
$errors = [];

$checkoutIntent = $_POST['checkout_intent'] ?? null;
$isCheckoutStart = $_SERVER['REQUEST_METHOD'] === 'POST'
    && is_string($checkoutIntent)
    && $checkoutIntent === 'buy_now';

if ($isCheckoutStart) {
    $csrfToken = $_POST['csrf_token'] ?? null;
    $productIdValue = $_POST['product_id'] ?? null;
    $productId = (is_string($productIdValue) || is_int($productIdValue))
        ? filter_var($productIdValue, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
        : false;

    if (!is_string($csrfToken) || !Auth::verifyCsrf($csrfToken)) {
        $errors[] = '不正なリクエストです。商品ページを再読み込みしてください。';
    } elseif ($productId === false) {
        $errors[] = '購入する商品の情報が正しくありません。';
    } else {
        $_SESSION['cart'] = array_values(array_unique(array_merge($_SESSION['cart'] ?? [], [(int) $productId])));
        $_SESSION['checkout_token'] = bin2hex(random_bytes(32));
        header('Location: checkout.php', true, 303);
        exit;
    }
}

$productIds = array_values(array_filter($_SESSION['cart'] ?? [], 'is_numeric'));
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isCheckoutStart) {
    $csrfToken = $_POST['csrf_token'] ?? null;
    if (!is_string($csrfToken) || !Auth::verifyCsrf($csrfToken)) {
        $errors[] = '不正なリクエストです。もう一度お試しください。';
    }
    $checkoutToken = $_POST['checkout_token'] ?? null;
    if (!is_string($checkoutToken) || !hash_equals((string) $_SESSION['checkout_token'], $checkoutToken)) {
        $errors[] = 'この注文フォームは送信済みです。画面を再読み込みしてください。';
    } else {
        $_SESSION['checkout_token'] = bin2hex(random_bytes(32));
    }
    $shipping = [
        'postal_code' => trim((string) ($_POST['postal_code'] ?? '')),
        'prefecture' => trim((string) ($_POST['prefecture'] ?? '')),
        'city' => trim((string) ($_POST['city'] ?? '')),
        'address_line' => trim((string) ($_POST['address_line'] ?? '')),
        'building' => trim((string) ($_POST['building'] ?? '')),
        'recipient_name' => trim((string) ($_POST['recipient_name'] ?? '')),
        'phone_number' => trim((string) ($_POST['phone_number'] ?? '')),
    ];
    if (in_array('', [$shipping['postal_code'], $shipping['prefecture'], $shipping['city'], $shipping['address_line'], $shipping['recipient_name'], $shipping['phone_number']], true)) {
        $errors[] = '必須項目を入力してください。';
    }
    if (!$productIds) {
        $errors[] = 'カートに商品がありません。';
    }
    if (!$errors) {
        try {
            $orderId = Order::create((int) $_SESSION['user_id'], $productIds, $shipping);
            OrderMailService::notify($orderId);
            $_SESSION['cart'] = [];
            header('Location: order-complete.php?id=' . $orderId);
            exit;
        } catch (OrderException $exception) {
            $errors[] = $exception->getMessage();
        } catch (PDOException $exception) {
            error_log('Order database operation failed.');
            $errors[] = '注文処理に失敗しました。';
        } catch (Throwable $exception) {
            error_log('Unexpected order processing failure: ' . get_class($exception));
            $errors[] = '注文処理に失敗しました。';
        }
    }
}
function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/nav.php';
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>注文手続き | OVERHAUL</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
    <main class="container py-5">
        <h1 class="h3 mb-4">注文手続き</h1><?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endforeach; ?><form method="post" class="card card-body"><input type="hidden" name="csrf_token" value="<?= h(Auth::csrfToken()) ?>"><input type="hidden" name="checkout_token" value="<?= h((string) $_SESSION['checkout_token']) ?>">
            <div class="mb-3"><label class="form-label">郵便番号</label><input class="form-control" name="postal_code" required></div>
            <div class="mb-3"><label class="form-label">都道府県</label><input class="form-control" name="prefecture" required></div>
            <div class="mb-3"><label class="form-label">市区町村</label><input class="form-control" name="city" required></div>
            <div class="mb-3"><label class="form-label">住所</label><input class="form-control" name="address_line" required></div>
            <div class="mb-3"><label class="form-label">建物名</label><input class="form-control" name="building"></div>
            <div class="mb-3"><label class="form-label">宛名</label><input class="form-control" name="recipient_name" required></div>
            <div class="mb-3"><label class="form-label">電話番号</label><input class="form-control" name="phone_number" required></div><button class="btn btn-primary" type="submit">注文を確定する</button>
        </form>
    </main>
</body>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
</html>