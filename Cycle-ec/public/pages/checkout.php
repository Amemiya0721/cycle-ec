<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/Order.php';
require_once __DIR__ . '/../../src/OrderMailService.php';
require_once __DIR__ . '/../../src/Product.php';

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

$errors = [];

$shipping = [
    'postal_code' => '',
    'prefecture' => '',
    'city' => '',
    'address_line' => '',
    'building' => '',
    'recipient_name' => '',
    'phone_number' => '',
];

if (empty($_SESSION['checkout_token'])) {
    $_SESSION['checkout_token'] = bin2hex(random_bytes(32));
}

/*
|--------------------------------------------------------------------------
| 1. 今すぐ購入の受付
|--------------------------------------------------------------------------
*/

$isBuyNow = $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['checkout_intent'] ?? null) === 'buy_now';

if ($isBuyNow) {
    $csrfToken = $_POST['csrf_token'] ?? null;
    $productIdValue = $_POST['product_id'] ?? null;

    $productId = (
        is_string($productIdValue) || is_int($productIdValue)
    )
        ? filter_var(
            $productIdValue,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        )
        : false;

    if (
        !is_string($csrfToken)
        || !Auth::verifyCsrf($csrfToken)
        || $productId === false
        || $productId === null
    ) {
        http_response_code(403);
        exit('購入リクエストが正しくありません。');
    }

    $_SESSION['checkout_mode'] = 'buy_now';
    $_SESSION['buy_now_product_id'] = (int) $productId;
    $_SESSION['checkout_token'] = bin2hex(random_bytes(32));

    header('Location: checkout.php', true, 303);
    exit;
}

/*
|--------------------------------------------------------------------------
| 2. カートからの購入
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'GET'
    && ($_GET['mode'] ?? null) === 'cart'
) {
    $_SESSION['checkout_mode'] = 'cart';

    unset($_SESSION['buy_now_product_id']);

    $_SESSION['checkout_token'] = bin2hex(random_bytes(32));

    header('Location: checkout.php', true, 303);
    exit;
}

if (!isset($_SESSION['checkout_mode'])) {
    $_SESSION['checkout_mode'] = 'cart';
}

/*
|--------------------------------------------------------------------------
| 3. ログイン確認
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = 'checkout.php';

    header('Location: login.php', true, 303);
    exit;
}

/*
|--------------------------------------------------------------------------
| 4. 購入対象の商品を決定
|--------------------------------------------------------------------------
*/

$checkoutMode = $_SESSION['checkout_mode'];

if ($checkoutMode === 'buy_now') {
    $productId = (int) ($_SESSION['buy_now_product_id'] ?? 0);

    $productIds = $productId > 0
        ? [$productId]
        : [];
} else {
    $productIds = array_values(
        array_unique(
            array_map(
                'intval',
                array_filter(
                    $_SESSION['cart'] ?? [],
                    'is_numeric'
                )
            )
        )
    );
}

/*
|--------------------------------------------------------------------------
| 5. 商品情報を取得
|--------------------------------------------------------------------------
*/

$checkoutItems = [];
$subtotal = 0;
$invalidProducts = false;

foreach ($productIds as $productId) {
    $product = Product::findById((int) $productId);

    if ($product === null) {
        $invalidProducts = true;
        continue;
    }

    $checkoutItems[] = $product;
    $subtotal += (int) $product['price'];
}

if ($invalidProducts || !$checkoutItems) {
    $errors[] = '購入できない商品が含まれています。商品を確認してください。';
}

/*
|--------------------------------------------------------------------------
| 6. 注文確定処理
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? null;
    $checkoutToken = $_POST['checkout_token'] ?? null;

    if (
        !is_string($csrfToken)
        || !Auth::verifyCsrf($csrfToken)
    ) {
        $errors[] = '不正なリクエストです。もう一度お試しください。';
    }

    if (
        !is_string($checkoutToken)
        || !hash_equals(
            (string) $_SESSION['checkout_token'],
            $checkoutToken
        )
    ) {
        $errors[] = 'この注文フォームは送信済みです。画面を再読み込みしてください。';
    }

    foreach ($shipping as $field => $value) {
        $shipping[$field] = trim((string) ($_POST[$field] ?? ''));
    }

    $requiredFields = [
        'postal_code',
        'prefecture',
        'city',
        'address_line',
        'recipient_name',
        'phone_number'
    ];

    foreach ($requiredFields as $field) {
        if ($shipping[$field] === '') {
            $errors[] = '必須項目を入力してください。';
            break;
        }
    }

    if (!$productIds) {
        $errors[] = '購入する商品がありません。';
    }

    if (!$errors) {
        $_SESSION['checkout_token'] = bin2hex(random_bytes(32));

        try {
            $orderId = Order::create(
                (int) $_SESSION['user_id'],
                $productIds,
                $shipping
            );

            if ($checkoutMode === 'cart') {
                $_SESSION['cart'] = [];
            }

            unset(
                $_SESSION['checkout_mode'],
                $_SESSION['buy_now_product_id']
            );

            $_SESSION['last_order_id'] = $orderId;

            try {
                OrderMailService::notify($orderId);
            } catch (Throwable $exception) {
                error_log(
                    'Order mail notification failed: '
                        . $exception->getMessage()
                );
            }

            header(
                'Location: order-complete.php?id=' . $orderId,
                true,
                303
            );
            exit;
        } catch (OrderException $exception) {
            $errors[] = $exception->getMessage();
        } catch (PDOException $exception) {
            error_log('Order database operation failed.');
            $errors[] = '注文処理に失敗しました。';
        } catch (Throwable $exception) {
            error_log(
                'Unexpected order processing failure: '
                    . get_class($exception)
            );
            $errors[] = '注文処理に失敗しました。';
        }
    }
}

/*
|--------------------------------------------------------------------------
| 7. 共通レイアウト
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/nav.php';
?>

<main class="container py-5">

    <h1 class="h3 mb-4">注文手続き</h1>

    <?php foreach ($errors as $error): ?>
        <div class="alert alert-danger">
            <?= h($error) ?>
        </div>
    <?php endforeach; ?>

    <!-- 購入商品一覧 -->
    <section class="card mb-4">

        <div class="card-header bg-white">
            <h2 class="h5 mb-0">
                <?= $checkoutMode === 'buy_now'
                    ? '今すぐ購入する商品'
                    : 'カート内の購入商品' ?>
            </h2>
        </div>

        <div class="card-body">

            <?php foreach ($checkoutItems as $item): ?>

                <?php
                $imageUrl = Product::publicImageUrl(
                    $item['images'][0]['image_url'] ?? null
                );
                ?>

                <div class="d-flex align-items-center gap-3 mb-3">

                    <!-- 商品画像 -->
                    <div
                        class="flex-shrink-0 bg-light rounded
                               d-flex align-items-center
                               justify-content-center"
                        style="width:100px;height:100px;">

                        <?php if ($imageUrl !== null): ?>

                            <img
                                src="<?= h($imageUrl) ?>"
                                alt="<?= h((string) $item['name']) ?>"
                                class="img-fluid rounded"
                                style="width:100%;height:100%;object-fit:contain;">

                        <?php else: ?>

                            <span class="text-muted small">
                                画像なし
                            </span>

                        <?php endif; ?>

                    </div>

                    <!-- 商品情報 -->
                    <div class="flex-grow-1">

                        <div class="fw-bold">
                            <?= h((string) $item['name']) ?>
                        </div>

                        <div class="text-muted small">
                            数量：1
                        </div>

                        <div class="fw-bold fs-5 mt-2">
                            ¥<?= number_format((float) $item['price']) ?>
                        </div>

                    </div>

                </div>

            <?php endforeach; ?>

            <hr>

            <div class="d-flex justify-content-between">
                <strong>商品小計</strong>
                <strong class="fs-5">
                    ¥<?= number_format($subtotal) ?>
                </strong>
            </div>

            <p class="text-muted small mt-2 mb-0">
                ※送料は別途発生する場合があります。
            </p>

        </div>

    </section>

    <!-- お届け先情報 -->
    <form method="post" class="card card-body">

        <h2 class="h5 mb-4">お届け先情報</h2>

        <input
            type="hidden"
            name="csrf_token"
            value="<?= h(Auth::csrfToken()) ?>">

        <input
            type="hidden"
            name="checkout_token"
            value="<?= h((string) $_SESSION['checkout_token']) ?>">


        <div class="mb-3">
            <label for="postal_code" class="form-label">郵便番号</label>

            <div class="input-group">
                <input
                    type="text"
                    class="form-control"
                    id="postal_code"
                    name="postal_code"
                    placeholder="123-4567"
                    inputmode="numeric"
                    maxlength="8"
                    required>
                <button
                    type="button"
                    class="btn btn-outline-secondary"
                    id="search-address">
                    住所検索
                </button>
            </div>

            <div id="postal-message" class="form-text" aria-live="polite">
                7桁入力すると自動で住所を検索します。
            </div>
        </div>

        <div class="mb-3">
            <label for="prefecture" class="form-label">都道府県</label>
            <input type="text" class="form-control"
                id="prefecture" name="prefecture" required>
        </div>

        <div class="mb-3">
            <label for="city" class="form-label">市区町村</label>
            <input type="text" class="form-control"
                id="city" name="city" required>
        </div>

        <div class="mb-3">
            <label for="address_line" class="form-label">町名・番地</label>
            <input type="text" class="form-control"
                id="address_line" name="address_line"
                placeholder="町名・番地を入力" required>
        </div>


        <div class="mb-3">
            <label class="form-label">建物名</label>
            <input
                class="form-control"
                name="building"
                value="<?= h($shipping['building']) ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">宛名</label>
            <input
                class="form-control"
                name="recipient_name"
                value="<?= h($shipping['recipient_name']) ?>"
                required>
        </div>

        <div class="mb-3">
            <label class="form-label">電話番号</label>
            <input
                class="form-control"
                name="phone_number"
                value="<?= h($shipping['phone_number']) ?>"
                required>
        </div>

        <button
            class="btn btn-primary"
            type="submit"
            <?= $errors && ($invalidProducts || !$checkoutItems)
                ? 'disabled'
                : '' ?>>
            注文を確定する
        </button>

    </form>

</main>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const postal = document.getElementById('postal_code');
    const prefecture = document.getElementById('prefecture');
    const city = document.getElementById('city');
    const address = document.getElementById('address_line');
    const button = document.getElementById('search-address');
    const message = document.getElementById('postal-message');

    let timer;
    let controller;

    async function searchAddress() {
        const zipcode = postal.value.replace(/[^0-9]/g, '');

        if (!/^[0-9]{7}$/.test(zipcode)) {
            message.textContent = '郵便番号を7桁で入力してください。';
            return;
        }

        if (controller) controller.abort();
        controller = new AbortController();

        button.disabled = true;
        message.textContent = '住所を検索しています...';

        try {
            const response = await fetch(
                'https://zipcloud.ibsnet.co.jp/api/search?zipcode='
                + encodeURIComponent(zipcode),
                { signal: controller.signal }
            );

            if (!response.ok) {
                throw new Error('通信エラー');
            }

            const data = await response.json();

            // 検索中に郵便番号が変更された場合は反映しない
            if (postal.value.replace(/[^0-9]/g, '') !== zipcode) {
                return;
            }

            if (data.status !== 200 || !data.results?.length) {
                message.textContent =
                    '住所が見つかりません。手動で入力してください。';
                return;
            }

            const result = data.results[0];

            prefecture.value = result.address1;
            city.value = result.address2;
            address.value = result.address3;

            message.textContent =
                '住所を入力しました。番地・建物名を確認してください。';

            address.focus();

        } catch (error) {
            if (error.name !== 'AbortError') {
                message.textContent =
                    '住所検索に失敗しました。手動で入力してください。';
            }
        } finally {
            button.disabled = false;
        }
    }

    postal.addEventListener('input', () => {
        clearTimeout(timer);
        if (controller) controller.abort();

        const zipcode = postal.value.replace(/[^0-9]/g, '');

        if (zipcode.length === 7) {
            timer = setTimeout(searchAddress, 400);
        }
    });

    button.addEventListener('click', () => {
        clearTimeout(timer);
        searchAddress();
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

</body>

</html>