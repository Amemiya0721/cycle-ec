<?php

declare(strict_types=1);
require_once __DIR__ . '/../../src/Order.php';
require_once __DIR__ . '/includes/auth.php';
try { $orders = Order::all(); } catch (PDOException $exception) { $orders = []; $errorMessage = '注文一覧の取得に失敗しました。'; }
function h(?string $value): string { return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8'); }
$adminTitle = '注文管理';
$activeMenu = 'orders';
$breadcrumbs = [['label' => '注文管理']];
require __DIR__ . '/includes/header.php';
?>
<div class="admin-page-header"><div><h1>注文管理</h1><p class="text-muted mb-0">注文内容と配送状況を確認します。</p></div></div>
<?php if (isset($errorMessage)): ?><div class="alert alert-danger"><?= h($errorMessage) ?></div><?php endif; ?>
<div class="admin-card bg-white"><div class="table-responsive"><table class="table admin-table table-hover mb-0"><thead><tr><th>注文番号</th><th>ユーザー</th><th>メール</th><th>合計</th><th>ステータス</th><th>注文日時</th></tr></thead><tbody><?php foreach ($orders as $order): ?><tr><td><?= (int) $order['order_id'] ?></td><td><?= h((string) $order['user_name']) ?></td><td><?= h((string) $order['email']) ?></td><td><?= number_format((float) $order['total_price']) ?>円</td><td><?= h((string) $order['status']) ?></td><td><?= h((string) $order['ordered_at']) ?></td></tr><?php endforeach; ?><?php if (!$orders): ?><tr><td colspan="6" class="text-center text-muted py-4">注文はありません。</td></tr><?php endif; ?></tbody></table></div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
