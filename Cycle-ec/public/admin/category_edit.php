<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/Category.php';
require_once __DIR__ . '/includes/auth.php';

const MAX_CATEGORY_NAME_LENGTH = 100;
const MAX_CATEGORY_IMAGE_SIZE = 5 * 1024 * 1024;

$categoryId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $categoryId = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
}
if (!$categoryId || $categoryId < 1) {
    http_response_code(400);
    exit('カテゴリIDが正しくありません。');
}

$errors = [];
$category = Category::findById($categoryId);
if ($category === null) {
    http_response_code(404);
    exit('カテゴリが見つかりません。');
}

$name = (string) $category['name'];
$newFilePath = null;
$oldIconUrl = (string) ($category['icon_url'] ?? '');

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $removeIcon = isset($_POST['remove_icon']);
    $file = $_FILES['icon'] ?? null;
    $hasFile = is_array($file) && (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE);
    $extension = null;

    if ($name === '') {
        $errors[] = 'カテゴリ名を入力してください。';
    } elseif (mb_strlen($name) > MAX_CATEGORY_NAME_LENGTH) {
        $errors[] = 'カテゴリ名は100文字以内で入力してください。';
    }

    if ($hasFile) {
        $uploadError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($uploadError !== UPLOAD_ERR_OK) {
            $errors[] = '画像のアップロードに失敗しました。';
        } elseif (!is_uploaded_file((string) $file['tmp_name'])) {
            $errors[] = '不正な画像ファイルです。';
        } elseif ((int) $file['size'] > MAX_CATEGORY_IMAGE_SIZE) {
            $errors[] = '画像は5MB以下にしてください。';
        } else {
            $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
            $mimeExtensions = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
            ];
            if (!isset($mimeExtensions[$mimeType])) {
                $errors[] = 'JPG、PNG、WebP形式の画像のみアップロードできます。';
            } else {
                $extension = $mimeExtensions[$mimeType];
            }
        }
    }

    if (!$errors) {
        $pdo = null;
        try {
            $iconUrl = $oldIconUrl !== '' ? $oldIconUrl : null;
            if ($hasFile) {
                $uploadDir = __DIR__ . '/../uploads/categories';
                if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                    throw new RuntimeException('カテゴリ画像の保存先を作成できませんでした。');
                }

                $fileName = bin2hex(random_bytes(16)) . '.' . $extension;
                $newFilePath = $uploadDir . DIRECTORY_SEPARATOR . $fileName;
                if (!move_uploaded_file((string) $file['tmp_name'], $newFilePath)) {
                    throw new RuntimeException('カテゴリ画像の保存に失敗しました。');
                }
                $iconUrl = '/uploads/categories/' . $fileName;
            } elseif ($removeIcon) {
                $iconUrl = null;
            }

            $pdo = Database::getConnection();
            $pdo->beginTransaction();
            if (!Category::update($categoryId, $name, $iconUrl, $pdo)) {
                throw new RuntimeException('カテゴリを更新できませんでした。');
            }
            $pdo->commit();

            if (($hasFile || $removeIcon) && $oldIconUrl !== '') {
                $oldFilePath = __DIR__ . '/..' . parse_url($oldIconUrl, PHP_URL_PATH);
                if (is_file($oldFilePath) && !unlink($oldFilePath)) {
                    error_log('Old category image deletion failed after update. path=' . $oldFilePath);
                }
            }
            header('Location: categories.php?success=updated');
            exit;
        } catch (Throwable $e) {
            if ($pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($newFilePath !== null && is_file($newFilePath) && !unlink($newFilePath)) {
                error_log('New category image cleanup failed. path=' . $newFilePath);
            }
            error_log('Category update failed. path=' . ($newFilePath ?? '(no new file)') . ' reason=' . $e->getMessage());
            $errors[] = 'カテゴリの更新に失敗しました。';
        }
    }
}
?>
<?php
$adminTitle = 'カテゴリ編集';
$activeMenu = 'categories';
$breadcrumbs = [
    ['label' => 'カテゴリ管理', 'url' => 'categories.php'],
    ['label' => 'カテゴリ編集'],
];
require __DIR__ . '/includes/header.php';
?>
<div class="admin-page-header"><div><h1>カテゴリ編集</h1><p class="text-muted mb-0">カテゴリ情報を更新します。</p></div></div>
    <div class="admin-form-section">
        <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endforeach; ?>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="category_id" value="<?= (int) $categoryId ?>">
            <div class="mb-3"><label class="form-label" for="name">カテゴリ名</label><input class="form-control" id="name" name="name" maxlength="100" value="<?= h($name) ?>" required></div>
            <div class="mb-3"><label class="form-label" for="icon">カテゴリ画像</label><input class="form-control" type="file" id="icon" name="icon" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"><div class="form-text">JPG、PNG、WebP / 5MB以下</div></div>
            <?php if ($oldIconUrl !== ''): ?><div class="mb-3"><img src="<?= h($oldIconUrl) ?>" alt="現在のカテゴリ画像" style="max-width:180px;max-height:180px;object-fit:cover"><div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="remove_icon" id="remove_icon"><label class="form-check-label" for="remove_icon">現在の画像を削除する</label></div></div><?php endif; ?>
            <div class="d-flex justify-content-between"><a class="btn btn-outline-secondary" href="categories.php">戻る</a><button class="btn btn-primary" type="submit">保存する</button></div>
        </form>
            </div>
        <?php require __DIR__ . '/includes/footer.php'; ?>