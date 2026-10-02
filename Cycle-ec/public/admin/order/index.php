<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../src/UserAddress.php';
require_once __DIR__ . '/../../../src/Order.php';
require_once __DIR__ . '/../includes/auth.php';

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

$status = isset($_GET['status']) && is_string($_GET['status'])
    ? $_GET['status']
    : '';
$keyword = isset($_GET['keyword']) && is_string($_GET['keyword'])
    ? trim($_GET['keyword'])
    : '';
$pageValue = isset($_GET['page']) && is_string($_GET['page'])
    ? filter_var($_GET['page'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : 1;
$page = $pageValue === false ? 1 : (int) $pageValue;

if (empty($_SESSION['order_status_csrf'])) {
    $_SESSION['order_status_csrf'] = bin2hex(random_bytes(32));
}

$actionMessage = $_SESSION['order_status_flash'] ?? null;
unset($_SESSION['order_status_flash']);
if (!is_array($actionMessage)) {
    $actionMessage = null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? null;
    $csrfValid = is_string($csrfToken)
        && hash_equals((string) $_SESSION['order_status_csrf'], $csrfToken);

    if (!$csrfValid) {
        $_SESSION['order_status_flash'] = [
            'type' => 'danger',
            'text' => 'リクエストを検証できませんでした。画面を再読み込みしてください。',
        ];
    } else {
        $_SESSION['order_status_csrf'] = bin2hex(random_bytes(32));
        $orderIdValue = $_POST['order_id'] ?? null;
        $orderId = (is_string($orderIdValue) || is_int($orderIdValue))
            ? filter_var($orderIdValue, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            : false;
        $targetStatus = $_POST['target_status'] ?? null;

        if ($orderId === false || !is_string($targetStatus)) {
            $_SESSION['order_status_flash'] = [
                'type' => 'danger',
                'text' => '注文IDまたは操作内容が正しくありません。',
            ];
        } elseif (!in_array($targetStatus, [Order::STATUS_SHIPPED, Order::STATUS_CANCELED], true)) {
            $_SESSION['order_status_flash'] = [
                'type' => 'danger',
                'text' => '許可されていない注文操作です。',
            ];
        } else {
            try {
                if (Order::transitionStatus((int) $orderId, $targetStatus)) {
                    $actionText = $targetStatus === Order::STATUS_SHIPPED
                        ? '発送済みにしました。'
                        : 'キャンセルし、在庫を復元しました。';
                    $_SESSION['order_status_flash'] = [
                        'type' => 'success',
                        'text' => '注文 #' . (int) $orderId . ' を' . $actionText,
                    ];
                } else {
                    $_SESSION['order_status_flash'] = [
                        'type' => 'danger',
                        'text' => '注文が見つからないか、既に状態が変更されています。最新の一覧を確認してください。',
                    ];
                }
            } catch (Throwable $exception) {
                error_log('Admin order status update failed.');
                $_SESSION['order_status_flash'] = [
                    'type' => 'danger',
                    'text' => '注文状態を更新できませんでした。注文と在庫は変更されていません。',
                ];
            }
        }
    }

    $returnStatus = $_POST['return_status'] ?? '';
    $returnStatus = is_string($returnStatus) && in_array($returnStatus, Order::STATUSES, true)
        ? $returnStatus
        : '';
    $returnKeyword = $_POST['return_keyword'] ?? '';
    $returnKeyword = is_string($returnKeyword) ? trim($returnKeyword) : '';
    $returnPageValue = $_POST['return_page'] ?? '1';
    $returnPage = (is_string($returnPageValue) || is_int($returnPageValue))
        ? filter_var($returnPageValue, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
        : false;
    $returnPage = $returnPage === false ? 1 : (int) $returnPage;
    $returnParams = array_filter([
        'status' => $returnStatus,
        'keyword' => $returnKeyword,
        'page' => $returnPage > 1 ? (string) $returnPage : '',
    ], static fn ($value): bool => $value !== '');
    $returnUrl = 'index.php' . ($returnParams ? '?' . http_build_query($returnParams) : '');
    header('Location: ' . $returnUrl, true, 303);
    exit;
}

$result = ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 20, 'total_pages' => 1];
$counts = [];
try {
    $result = Order::listForAdmin(['status' => $status, 'keyword' => $keyword, 'page' => $page]);
    $counts = Order::statusCounts();
} catch (PDOException $exception) {
    error_log('Admin order list failed: ' . $exception->getMessage());
    $errorMessage = '注文一覧の取得に失敗しました。';
}

$url = static fn (array $params): string => 'index.php' . (($q = http_build_query(array_filter(
    $params,
    static fn ($v): bool => $v !== '' && $v !== null
))) !== '' ? '?' . $q : '');

$allCount = array_sum($counts);

$adminTitle = '注文管理';
$activeMenu = 'orders';
$breadcrumbs = [['label' => '注文管理']];
require __DIR__ . '/../includes/header.php';
?>
<div class="admin-page-header">
    <div><h1>注文管理</h1><p class="text-muted mb-0">誰が購入し、どこへ送るかを確認します。</p></div>
</div>
<?php if (isset($errorMessage)): ?><div class="alert alert-danger"><?= h($errorMessage) ?></div><?php endif; ?>
<?php if ($actionMessage !== null): ?><div class="alert alert-<?= h((string) $actionMessage['type']) ?>" role="status"><?= h((string) $actionMessage['text']) ?></div><?php endif; ?>

<ul class="nav nav-pills mb-3">
    <li class="nav-item"><a class="nav-link <?= $status === '' ? 'active' : '' ?>" href="<?= h($url(['keyword' => $keyword])) ?>">すべて <span class="badge text-bg-light"><?= (int) $allCount ?></span></a></li>
    <?php foreach (Order::STATUSES as $s): ?>
        <li class="nav-item"><a class="nav-link <?= $status === $s ? 'active' : '' ?>" href="<?= h($url(['status' => $s, 'keyword' => $keyword])) ?>"><?= h($s) ?> <span class="badge text-bg-light"><?= (int) ($counts[$s] ?? 0) ?></span></a></li>
    <?php endforeach; ?>
</ul>

<form method="get" class="row g-2 mb-3">
    <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= h($status) ?>"><?php endif; ?>
    <div class="col-sm-6 col-md-5">
        <input type="search" name="keyword" class="form-control form-control-sm" value="<?= h($keyword) ?>" placeholder="注文番号・購入者名・メール・受取人名で検索">
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-sm btn-primary">検索</button>
        <?php if ($keyword !== '' || $status !== ''): ?><a class="btn btn-sm btn-outline-secondary" href="index.php">クリア</a><?php endif; ?>
    </div>
</form>

<div class="admin-card bg-white"><div class="table-responsive"><table class="table admin-table table-hover align-middle mb-0">
<thead><tr><th>注文番号</th><th>注文日時</th><th>購入者</th><th>送付先（受取人）</th><th class="text-end">合計</th><th>ステータス</th><th>詳細</th><th>操作</th></tr></thead>
<tbody>
<?php foreach ($result['items'] as $o): ?>
    <tr>
        <td>#<?= (int) $o['order_id'] ?></td>
        <td><?= h((string) $o['ordered_at']) ?></td>
        <td><?= h((string) $o['buyer_name']) ?><br><small class="text-muted"><?= h((string) $o['buyer_email']) ?></small></td>
        <td><?= h((string) $o['shipping_recipient_name']) ?> 様<br><small class="text-muted"><?= h((string) $o['shipping_prefecture'] . $o['shipping_city']) ?></small></td>
        <td class="text-end"><?= number_format((float) $o['total_price']) ?>円<br><small class="text-muted"><?= (int) $o['item_count'] ?>点</small></td>
        <td><?= h((string) $o['status']) ?></td>
        <td><a class="btn btn-sm btn-outline-primary" href="order-detail.php?id=<?= (int) $o['order_id'] ?>">詳細</a></td>
        <td>
            <?php if ($o['status'] === Order::STATUS_ORDERED): ?>
                <div class="d-flex flex-column flex-sm-row gap-1">
                    <form method="post" class="m-0">
                        <input type="hidden" name="csrf_token" value="<?= h((string) $_SESSION['order_status_csrf']) ?>">
                        <input type="hidden" name="order_id" value="<?= (int) $o['order_id'] ?>">
                        <input type="hidden" name="target_status" value="<?= h(Order::STATUS_SHIPPED) ?>">
                        <input type="hidden" name="return_status" value="<?= h($status) ?>">
                        <input type="hidden" name="return_keyword" value="<?= h($keyword) ?>">
                        <input type="hidden" name="return_page" value="<?= (int) $result['page'] ?>">
                        <button class="btn btn-sm btn-success text-nowrap w-100" type="submit">発送済みにする</button>
                    </form>
                    <form method="post" class="m-0" onsubmit="return confirm('注文 #<?= (int) $o['order_id'] ?> をキャンセルします。在庫を復元します。よろしいですか？');">
                        <input type="hidden" name="csrf_token" value="<?= h((string) $_SESSION['order_status_csrf']) ?>">
                        <input type="hidden" name="order_id" value="<?= (int) $o['order_id'] ?>">
                        <input type="hidden" name="target_status" value="<?= h(Order::STATUS_CANCELED) ?>">
                        <input type="hidden" name="return_status" value="<?= h($status) ?>">
                        <input type="hidden" name="return_keyword" value="<?= h($keyword) ?>">
                        <input type="hidden" name="return_page" value="<?= (int) $result['page'] ?>">
                        <button class="btn btn-sm btn-outline-danger text-nowrap w-100" type="submit">キャンセルする</button>
                    </form>
                </div>
            <?php elseif ($o['status'] === Order::STATUS_SHIPPED): ?>
                <span class="badge text-bg-success">発送済み</span>
            <?php elseif ($o['status'] === Order::STATUS_CANCELED): ?>
                <span class="badge text-bg-secondary">キャンセル済み</span>
            <?php else: ?>
                <span class="text-muted">操作できません</span>
            <?php endif; ?>
        </td>
    </tr>
<?php endforeach; ?>
<?php if (!$result['items']): ?><tr><td colspan="8" class="text-center text-muted py-4">該当する注文はありません。</td></tr><?php endif; ?>
</tbody></table></div></div>

<div class="d-flex justify-content-between align-items-center mt-3">
    <span class="text-muted small">該当 <?= (int) $result['total'] ?> 件</span>
    <?php if ($result['total_pages'] > 1): ?>
    <nav aria-label="ページ送り"><ul class="pagination pagination-sm mb-0">
        <?php for ($p = 1; $p <= $result['total_pages']; $p++): ?>
            <li class="page-item <?= $p === $result['page'] ? 'active' : '' ?>"><a class="page-link" href="<?= h($url(['status' => $status, 'keyword' => $keyword, 'page' => $p > 1 ? (string) $p : ''])) ?>"><?= $p ?></a></li>
        <?php endfor; ?>
    </ul></nav>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
