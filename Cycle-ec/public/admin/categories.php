<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/Category.php';

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
    $categories = Category::all();
} catch (PDOException $e) {
    $errors[] = 'カテゴリ一覧の取得に失敗しました。';
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>カテゴリ管理 | OVERHAUL 管理画面</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-dark bg-dark"><div class="container"><a class="navbar-brand" href="index.php">OVERHAUL 管理画面</a><a class="btn btn-outline-light btn-sm" href="../index.php">サイトを見る</a></div></nav>
<main class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">カテゴリ管理</h1>
        <a class="btn btn-primary" href="category_add.php">カテゴリを追加</a>
    </div>
    <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endforeach; ?>
    <?php if (($_GET['success'] ?? '') === 'updated'): ?><div class="alert alert-success">カテゴリを更新しました。</div><?php endif; ?>
    <?php if (($_GET['success'] ?? '') === 'deleted'): ?><div class="alert alert-success">カテゴリを削除しました。</div><?php endif; ?>
    <div class="card shadow-sm"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>ID</th><th>画像</th><th>カテゴリ名</th><th class="text-end">操作</th></tr></thead>
        <tbody>
        <?php foreach ($categories as $category): ?>
            <tr>
                <td><?= (int) $category['category_id'] ?></td>
                <td><?php if (!empty($category['icon_url'])): ?><img src="<?= h((string) $category['icon_url']) ?>" alt="" style="width:64px;height:64px;object-fit:cover"><?php else: ?><span class="text-muted">なし</span><?php endif; ?></td>
                <td><?= h((string) $category['name']) ?></td>
                <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="category_edit.php?id=<?= (int) $category['category_id'] ?>">編集</a> <form method="post" class="d-inline" onsubmit="return confirm('このカテゴリを削除しますか？')"><input type="hidden" name="category_id" value="<?= (int) $category['category_id'] ?>"><button class="btn btn-sm btn-outline-danger" type="submit">削除</button></form></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$categories): ?><tr><td colspan="4" class="text-center py-4">カテゴリがありません。</td></tr><?php endif; ?>
        </tbody>
    </table></div></div>
</main>
</body>
</html>