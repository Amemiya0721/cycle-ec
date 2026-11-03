<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../../src/ShippingRate.php';

if (empty($_SESSION['shipping_rate_csrf'])) {
    $_SESSION['shipping_rate_csrf'] = bin2hex(random_bytes(32));
}

$errors = [];
$successMessage = null;
$blockedProducts = [];
$formValues = [
    'shipping_size' => '',
    'sort_order' => '',
    'shipping_fee' => '',
    'is_active' => true,
];

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function validateShippingRateInput(array $input): array
{
    $sizeInput = $input['shipping_size'] ?? null;
    $sortOrderInput = $input['sort_order'] ?? null;
    $feeInput = $input['shipping_fee'] ?? null;
    $size = is_string($sizeInput) ? trim($sizeInput) : '';
    $sortOrder = is_string($sortOrderInput) || is_int($sortOrderInput)
        ? (string) $sortOrderInput
        : '';
    $fee = is_string($feeInput) ? trim($feeInput) : '';
    $isActive = isset($input['is_active']) && $input['is_active'] === '1';
    $errors = [];

    if ($size === '' || mb_strlen($size) > 100) {
        $errors[] = '配送サイズは1〜100文字で入力してください。';
    }
    if (!ctype_digit($sortOrder) || (int) $sortOrder > 4294967295) {
        $errors[] = '並び順は0以上の整数で入力してください。';
    }
    if (!preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', $fee)) {
        $errors[] = '送料は0以上、99999999.99以下の金額で入力してください。';
    }

    return [
        'errors' => $errors,
        'data' => [
            'shipping_size' => $size,
            'sort_order' => (int) $sortOrder,
            'shipping_fee' => $fee,
            'is_active' => $isActive,
        ],
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? null;
    if (!is_string($csrf) || !hash_equals((string) $_SESSION['shipping_rate_csrf'], $csrf)) {
        http_response_code(403);
        exit('不正なリクエストです。');
    }

    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    $rateIdValue = $_POST['shipping_rate_id'] ?? null;
    $rateId = is_string($rateIdValue) || is_int($rateIdValue)
        ? filter_var($rateIdValue, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
        : false;

    if ($action === 'delete') {
        if ($rateId === false || $rateId === null) {
            $errors[] = '送料サイズIDが正しくありません。';
        } else {
            try {
                $blockedProducts = ShippingRate::delete((int) $rateId);
                if ($blockedProducts !== []) {
                    $errors[] = 'この配送サイズは商品で使用中のため削除できません。先に該当商品の配送サイズを変更してください。';
                } else {
                    $successMessage = '送料サイズを削除しました。';
                }
            } catch (Throwable $exception) {
                error_log('Shipping rate deletion failed: ' . $exception->getMessage());
                $errors[] = '送料サイズの削除に失敗しました。';
            }
        }
    } elseif ($action === 'create' || $action === 'update') {
        $validated = validateShippingRateInput($_POST);
        $errors = $validated['errors'];
        $data = $validated['data'];
        if ($action === 'create') {
            $formValues = array_merge($formValues, $data);
        } elseif ($rateId === false || $rateId === null) {
            $errors[] = '送料サイズIDが正しくありません。';
        }

        if ($errors === []) {
            try {
                if ($action === 'create') {
                    ShippingRate::create($data);
                    $successMessage = '送料サイズを追加しました。';
                    $formValues = [
                        'shipping_size' => '',
                        'sort_order' => '',
                        'shipping_fee' => '',
                        'is_active' => true,
                    ];
                } elseif (!ShippingRate::update((int) $rateId, $data)) {
                    $errors[] = '送料サイズを更新できませんでした。';
                } else {
                    $successMessage = '送料サイズを更新しました。';
                }
            } catch (PDOException $exception) {
                error_log('Shipping rate save failed: ' . $exception->getMessage());
                $errors[] = (int) ($exception->errorInfo[1] ?? 0) === 1062
                    ? '同じ配送サイズ名または並び順が既に登録されています。'
                    : '送料サイズの保存に失敗しました。';
            } catch (Throwable $exception) {
                error_log('Shipping rate save failed: ' . $exception->getMessage());
                $errors[] = '送料サイズの保存に失敗しました。';
            }
        }
    } else {
        $errors[] = '操作内容が正しくありません。';
    }
}

try {
    $rates = ShippingRate::all();
} catch (PDOException $exception) {
    error_log('Shipping rate list fetch failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('送料マスタの取得に失敗しました。先にDBマイグレーションを適用してください。');
}

$adminTitle = '送料管理';
$activeMenu = 'shipping-rates';
$breadcrumbs = [['label' => '送料管理']];
require __DIR__ . '/includes/header.php';
?>
<div class="admin-page-header">
    <div><h1>送料管理</h1><p class="text-muted mb-0">配送サイズ・並び順・送料を管理します。</p></div>
</div>

<?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endforeach; ?>
<?php if ($blockedProducts !== []): ?>
    <div class="alert alert-warning">
        <strong>利用中の商品</strong>
        <ul class="mb-0 mt-2">
            <?php foreach ($blockedProducts as $product): ?>
                <li>商品ID <?= (int) $product['product_id'] ?>：<?= h($product['name']) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
<?php if ($successMessage !== null): ?><div class="alert alert-success"><?= h($successMessage) ?></div><?php endif; ?>

<section class="admin-card bg-white mb-4">
    <div class="p-3 border-bottom"><h2 class="admin-section-title mb-0">登録済みサイズ</h2></div>
    <?php if ($rates === []): ?>
        <p class="p-3 mb-0 text-muted">送料サイズがありません。下のフォームから追加してください。</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table admin-table align-middle mb-0">
                <thead class="table-light"><tr><th>配送サイズ</th><th>並び順（大きいほど大型）</th><th>送料</th><th>状態</th><th>操作</th></tr></thead>
                <tbody>
                <?php foreach ($rates as $rate): ?>
                    <tr>
                        <?php $rateFormId = 'shipping-rate-' . (int) $rate['shipping_rate_id']; ?>
                        <td><form method="post" id="<?= h($rateFormId) ?>">
                            <input type="hidden" name="csrf_token" value="<?= h((string) $_SESSION['shipping_rate_csrf']) ?>">
                            <input type="hidden" name="shipping_rate_id" value="<?= (int) $rate['shipping_rate_id'] ?>">
                            <input class="form-control" name="shipping_size" maxlength="100" value="<?= h((string) $rate['shipping_size']) ?>" required>
                        </form></td>
                        <td><input class="form-control" form="<?= h($rateFormId) ?>" type="number" name="sort_order" min="0" step="1" value="<?= (int) $rate['sort_order'] ?>" required></td>
                        <td><div class="input-group"><input class="form-control" form="<?= h($rateFormId) ?>" type="number" name="shipping_fee" min="0" max="99999999.99" step="0.01" value="<?= h((string) $rate['shipping_fee']) ?>" required><span class="input-group-text">円</span></div></td>
                        <td>
                            <input type="hidden" form="<?= h($rateFormId) ?>" name="is_active" value="0">
                            <div class="form-check"><input class="form-check-input" form="<?= h($rateFormId) ?>" type="checkbox" name="is_active" value="1" <?= (int) $rate['is_active'] === 1 ? 'checked' : '' ?>><label class="form-check-label">有効</label></div>
                        </td>
                        <td class="text-nowrap">
                            <button class="btn btn-sm btn-primary" form="<?= h($rateFormId) ?>" type="submit" name="action" value="update">保存</button>
                            <button class="btn btn-sm btn-outline-danger" form="<?= h($rateFormId) ?>" type="submit" name="action" value="delete" onclick="return confirm('この送料サイズを削除しますか？商品で使用中の場合は削除できません。')">削除</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="admin-card bg-white p-3">
    <h2 class="admin-section-title">配送サイズを追加</h2>
    <form method="post" class="row g-3">
        <input type="hidden" name="csrf_token" value="<?= h((string) $_SESSION['shipping_rate_csrf']) ?>">
        <input type="hidden" name="action" value="create">
        <input type="hidden" name="is_active" value="0">
        <div class="col-md-4"><label class="form-label" for="new-shipping-size">配送サイズ名</label><input class="form-control" id="new-shipping-size" name="shipping_size" maxlength="100" value="<?= h((string) $formValues['shipping_size']) ?>" required></div>
        <div class="col-md-2"><label class="form-label" for="new-sort-order">並び順</label><input class="form-control" type="number" id="new-sort-order" name="sort_order" min="0" step="1" value="<?= h((string) $formValues['sort_order']) ?>" required></div>
        <div class="col-md-3"><label class="form-label" for="new-shipping-fee">送料</label><div class="input-group"><input class="form-control" type="number" id="new-shipping-fee" name="shipping_fee" min="0" max="99999999.99" step="0.01" value="<?= h((string) $formValues['shipping_fee']) ?>" required><span class="input-group-text">円</span></div></div>
        <div class="col-md-2 d-flex align-items-end"><div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="new-active" name="is_active" value="1" <?= !empty($formValues['is_active']) ? 'checked' : '' ?>><label class="form-check-label" for="new-active">有効</label></div></div>
        <div class="col-md-1 d-flex align-items-end"><button class="btn btn-primary" type="submit">追加</button></div>
    </form>
    <p class="form-text mb-0 mt-3">複数商品を注文する場合は、並び順が最大の配送サイズに設定された送料を適用します。</p>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
