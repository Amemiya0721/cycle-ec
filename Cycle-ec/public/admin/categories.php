<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/Category.php';
require_once __DIR__ . '/includes/auth.php';

if (empty($_SESSION['category_reorder_csrf'])) {
    $_SESSION['category_reorder_csrf'] = bin2hex(random_bytes(32));
}

$errors = [];
$categories = [];

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $categoryId = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
    if (!$categoryId) {
        $errors[] = '削除対象のカテゴリが見つかりません。';
    } else {
        try {
            $category = Category::findById($categoryId);
            if ($category === null) {
                throw new RuntimeException('削除対象のカテゴリが見つかりません。');
            }
            $pdo = Database::getConnection();
            $pdo->beginTransaction();
            if (!Category::delete($categoryId, $pdo)) {
                throw new RuntimeException('カテゴリを削除できませんでした。');
            }
            $pdo->commit();

            if (!empty($category['icon_url'])) {
                $filePath = __DIR__ . '/..' . parse_url((string) $category['icon_url'], PHP_URL_PATH);
                if (is_file($filePath) && !unlink($filePath)) {
                    error_log('Category image deletion failed after category deletion. path=' . $filePath);
                }
            }
            header('Location: categories.php?success=deleted');
            exit;
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Category deletion failed: ' . $e->getMessage());
            $errors[] = 'カテゴリを削除できませんでした。商品で使用中のカテゴリは削除できない場合があります。';
        }
    }
}

try {
    $categories = Category::allWithProductCounts();
} catch (PDOException $e) {
    $errors[] = 'カテゴリ一覧の取得に失敗しました。';
}
?>
<?php
$adminTitle = 'カテゴリ管理';
$activeMenu = 'categories';
$breadcrumbs = [['label' => 'カテゴリ管理']];
require __DIR__ . '/includes/header.php';
?>
<div class="admin-page-header"><div><h1>カテゴリ管理</h1><p class="text-muted mb-0">ショップの商品分類を管理します。</p></div><a class="btn btn-primary" href="category_add.php">＋ カテゴリを追加</a></div>
<?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endforeach; ?>
<?php if (($_GET['success'] ?? '') === 'updated'): ?><div class="alert alert-success">カテゴリを更新しました。</div><?php endif; ?>
<?php if (($_GET['success'] ?? '') === 'created'): ?><div class="alert alert-success">カテゴリを登録しました。</div><?php endif; ?>
<?php if (($_GET['success'] ?? '') === 'deleted'): ?><div class="alert alert-success">カテゴリを削除しました。</div><?php endif; ?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <p id="category-sort-status" class="mb-0 text-muted" role="status" aria-live="polite">ドラッグ、または上下ボタンで表示順を変更できます。</p>
    <button id="category-sort-retry" class="btn btn-sm btn-outline-primary" type="button" hidden>保存を再試行</button>
</div>
<div class="admin-card bg-white"><div class="table-responsive"><table class="table admin-table align-middle mb-0">
    <thead><tr><th scope="col">順序</th><th scope="col">ID</th><th scope="col">画像</th><th scope="col">カテゴリ名</th><th scope="col">商品数</th><th scope="col" class="text-end">操作</th></tr></thead>
    <tbody id="category-sort-list" data-reorder-url="<?= h($baseUrl . 'admin/category_reorder.php') ?>" data-csrf-token="<?= h((string) $_SESSION['category_reorder_csrf']) ?>">
    <?php foreach ($categories as $category): ?>
        <tr data-category-id="<?= (int) $category['category_id'] ?>">
            <td>
                <div class="d-flex flex-wrap align-items-center gap-1">
                    <button class="btn btn-sm btn-outline-secondary category-sort-handle" type="button" draggable="true" data-drag-handle aria-label="<?= h((string) $category['name']) ?>をドラッグで移動" title="ドラッグして移動">移動</button>
                    <button class="btn btn-sm btn-outline-secondary" type="button" data-move-up aria-label="<?= h((string) $category['name']) ?>を上へ移動" title="上へ">↑</button>
                    <button class="btn btn-sm btn-outline-secondary" type="button" data-move-down aria-label="<?= h((string) $category['name']) ?>を下へ移動" title="下へ">↓</button>
                    <span class="small text-muted" data-sort-position></span>
                </div>
            </td>
            <td><?= (int) $category['category_id'] ?></td>
            <td><?php if (!empty($category['icon_url'])): ?><img src="<?= h((string) $category['icon_url']) ?>" alt="" style="width:56px;height:56px;object-fit:cover;border-radius:.3rem"><?php else: ?><span class="text-muted">なし</span><?php endif; ?></td>
            <td class="fw-semibold"><?= h((string) $category['name']) ?></td>
            <td><?= (int) $category['product_count'] ?></td>
            <td class="text-end text-nowrap"><a class="btn btn-sm btn-outline-primary" href="category_edit.php?id=<?= (int) $category['category_id'] ?>">編集</a> <form method="post" class="d-inline"><input type="hidden" name="category_id" value="<?= (int) $category['category_id'] ?>"><button class="btn btn-sm btn-outline-danger" type="submit" data-confirm="このカテゴリを削除しますか？">削除</button></form></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$categories): ?><tr><td colspan="6" class="text-center text-muted py-4">カテゴリがありません。</td></tr><?php endif; ?>
    </tbody>
</table></div></div>
<script src="<?= h($baseUrl . 'assets/js/category-sort.js') ?>" defer></script>
<?php require __DIR__ . '/includes/footer.php'; ?>