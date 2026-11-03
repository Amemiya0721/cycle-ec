<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/Order.php';
require_once __DIR__ . '/../../src/OrderMailService.php';
require_once __DIR__ . '/../../src/Product.php';
require_once __DIR__ . '/../../src/UserAddress.php';

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

$errors = [];
$bankTransferConfigured = false;
try {
    $bankTransferConfigured = OrderMailService::isBankTransferConfigured();
} catch (PDOException $exception) {
    error_log('Checkout bank transfer settings fetch failed: ' . $exception->getMessage());
    $errors[] = 'お支払い情報を取得できません。時間をおいて再度お試しください。';
}

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

$savedAddress = UserAddress::findByUserId((int) $_SESSION['user_id']);
if ($savedAddress !== null) {
    $shipping = [
        'postal_code' => UserAddress::formatPostalCode((string) $savedAddress['postal_code']),
        'prefecture' => (string) $savedAddress['prefecture'],
        'city' => (string) $savedAddress['city'],
        'address_line' => (string) $savedAddress['address_line'],
        'building' => (string) ($savedAddress['building'] ?? ''),
        'recipient_name' => (string) $savedAddress['recipient_name'],
        'phone_number' => (string) $savedAddress['phone_number'],
    ];
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
$subtotalCents = 0;
$shippingQuote = null;
$invalidProducts = false;

foreach ($productIds as $productId) {
    $product = Product::findById((int) $productId);

    if ($product === null) {
        $invalidProducts = true;
        continue;
    }

    $checkoutItems[] = $product;
    $price = (string) $product['price'];
    if (!preg_match('/^\d{1,8}\.\d{2}$/D', $price)) {
        $invalidProducts = true;
        continue;
    }
    $subtotalCents += (int) str_replace('.', '', $price);
}
$currentSubtotal = sprintf('%d.%02d', intdiv($subtotalCents, 100), $subtotalCents % 100);

if ($invalidProducts || !$checkoutItems) {
    $errors[] = '購入できない商品が含まれています。商品を確認してください。';
} else {
    try {
        $shippingQuote = ShippingRate::quoteForProducts(
            Database::getConnection(),
            $checkoutItems
        );
    } catch (ShippingRateException $exception) {
        $errors[] = $exception->getMessage();
    } catch (PDOException $exception) {
        error_log('Checkout shipping quote failed: ' . $exception->getMessage());
        $errors[] = '送料を取得できませんでした。時間をおいて再度お試しください。';
    }
}

/*
|--------------------------------------------------------------------------
| 6. 注文確定処理
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$bankTransferConfigured) {
        $errors[] = '銀行振込先口座が未設定のため、現在ご注文を受け付けていません。';
    }

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

    $expectedRateId = filter_var(
        $_POST['expected_shipping_rate_id'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );
    $expectedFee = $_POST['expected_shipping_fee'] ?? null;
    $expectedSize = $_POST['expected_shipping_size'] ?? null;
    $expectedSubtotal = $_POST['expected_subtotal'] ?? null;
    if (
        !is_string($expectedSubtotal)
        || $expectedSubtotal !== $currentSubtotal
        || (
            $shippingQuote !== null
            && (
                $expectedRateId !== (int) $shippingQuote['shipping_rate_id']
                || !is_string($expectedFee)
                || $expectedFee !== (string) $shippingQuote['shipping_fee']
                || !is_string($expectedSize)
                || $expectedSize !== (string) $shippingQuote['shipping_size']
            )
        )
    ) {
        $errors[] = '商品価格または送料が更新されたため、ご注文内容を確認してから再度注文してください。';
    }

    $normalizedAddress = UserAddress::normalize([
        'recipient_name' => $_POST['recipient_name'] ?? '',
        'postal_code' => $_POST['postal_code'] ?? '',
        'prefecture' => $_POST['prefecture'] ?? '',
        'city' => $_POST['city'] ?? '',
        'address_line' => $_POST['address_line'] ?? '',
        'building' => $_POST['building'] ?? '',
        'phone_number' => $_POST['phone_number'] ?? '',
    ]);
    $addressErrors = UserAddress::validate($normalizedAddress);
    if ($addressErrors !== []) {
        $errors = array_merge($errors, array_values($addressErrors));
    }
    $shipping = [
        'postal_code' => $normalizedAddress['postal_code'],
        'prefecture' => $normalizedAddress['prefecture'],
        'city' => $normalizedAddress['city'],
        'address_line' => $normalizedAddress['address_line'],
        'building' => $normalizedAddress['building'],
        'recipient_name' => $normalizedAddress['recipient_name'],
        'phone_number' => $normalizedAddress['phone_number'],
    ];

    if (!$productIds) {
        $errors[] = '購入する商品がありません。';
    }

    if (!$errors) {
        $_SESSION['checkout_token'] = bin2hex(random_bytes(32));

        try {
            $orderId = Order::create(
                (int) $_SESSION['user_id'],
                $productIds,
                $shipping,
                [
                    'subtotal' => $expectedSubtotal,
                    'shipping_rate_id' => is_int($expectedRateId) ? $expectedRateId : 0,
                    'shipping_fee' => $expectedFee,
                    'shipping_size' => $expectedSize,
                ]
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
                            ¥<?= number_format((float) $item['price'], 2) ?>
                        </div>

                    </div>

                </div>

            <?php endforeach; ?>

            <hr>

            <div class="d-flex justify-content-between">
                <strong>商品小計</strong>
                <strong class="fs-5">
                    ¥<?= number_format($subtotalCents / 100, 2) ?>
                </strong>
            </div>
            <?php if ($shippingQuote !== null): ?>
                <div class="d-flex justify-content-between mt-2">
                    <span>送料（<?= h($shippingQuote['shipping_size']) ?>）</span>
                    <strong>¥<?= number_format((float) $shippingQuote['shipping_fee'], 2) ?></strong>
                </div>
                <hr>
                <div class="d-flex justify-content-between">
                    <strong>ご請求合計</strong>
                    <strong class="fs-5">¥<?= number_format(($subtotalCents / 100) + (float) $shippingQuote['shipping_fee'], 2) ?></strong>
                </div>
            <?php endif; ?>

        </div>

    </section>

    <section class="card mb-4">
        <div class="card-body">
            <h2 class="h5">お支払い方法：銀行振込</h2>
            <?php if ($bankTransferConfigured): ?>
                <p class="mb-0">振込先は注文確認メールに記載します。お支払い期限は注文確定から3日間です。</p>
            <?php else: ?>
                <p class="alert alert-warning mb-0">銀行振込先口座が未設定のため、注文を受け付けていません。</p>
            <?php endif; ?>
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

        <?php if ($shippingQuote !== null): ?>
            <input type="hidden" name="expected_subtotal" value="<?= h($currentSubtotal) ?>">
            <input type="hidden" name="expected_shipping_rate_id" value="<?= (int) $shippingQuote['shipping_rate_id'] ?>">
            <input type="hidden" name="expected_shipping_fee" value="<?= h((string) $shippingQuote['shipping_fee']) ?>">
            <input type="hidden" name="expected_shipping_size" value="<?= h((string) $shippingQuote['shipping_size']) ?>">
        <?php endif; ?>

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
                    value="<?= h($shipping['postal_code']) ?>"
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
            <select class="form-select" id="prefecture" name="prefecture" required>
                <option value="">選択してください</option>
                <?php foreach (UserAddress::PREFECTURES as $prefecture): ?>
                    <option value="<?= h($prefecture) ?>" <?= $shipping['prefecture'] === $prefecture ? 'selected' : '' ?>><?= h($prefecture) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label for="city" class="form-label">市区町村</label>
            <input type="text" class="form-control"
                id="city" name="city" maxlength="50" value="<?= h($shipping['city']) ?>" required>
        </div>

        <div class="mb-3">
            <label for="address_line" class="form-label">町名・番地</label>
            <input type="text" class="form-control"
                id="address_line" name="address_line"
                placeholder="町名・番地を入力" maxlength="100" value="<?= h($shipping['address_line']) ?>" required>
        </div>


        <div class="mb-3">
            <label class="form-label">建物名</label>
            <input
                class="form-control"
                name="building"
                maxlength="100"
                value="<?= h($shipping['building']) ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">宛名</label>
            <input
                class="form-control"
                name="recipient_name"
                maxlength="50"
                value="<?= h($shipping['recipient_name']) ?>"
                required>
        </div>

        <div class="mb-3">
            <label class="form-label">電話番号</label>
            <input
                type="tel"
                class="form-control"
                name="phone_number"
                maxlength="20"
                value="<?= h($shipping['phone_number']) ?>"
                required>
        </div>

        <button
            class="btn btn-primary"
            type="submit"
            <?= $invalidProducts || !$checkoutItems || $shippingQuote === null || !$bankTransferConfigured
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