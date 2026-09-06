<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/Category.php';
require_once __DIR__ . '/includes/auth.php';

const MAX_CATEGORY_NAME_LENGTH = 100;
const MAX_CATEGORY_IMAGE_SIZE = 5 * 1024 * 1024;

$errors = [];
$name = trim((string) ($_POST['name'] ?? ''));
$pdo = null;
$savedFilePath = null;

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($name === '') {
        $errors[] = 'カテゴリ名を入力してください。';
    } elseif (mb_strlen($name) > MAX_CATEGORY_NAME_LENGTH) {
        $errors[] = 'カテゴリ名は100文字以内で入力してください。';
        $errors[] = 'カテゴリ名は100文字以内で入力してください。';
    }

    $file = $_FILES['icon'] ?? null;
    $hasFile = is_array($file) && (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE);
    $extension = null;

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
        try {
            $pdo = Database::getConnection();
            $pdo->beginTransaction();

            $iconUrl = null;
            if ($hasFile) {
                $uploadDir = __DIR__ . '/../uploads/categories';
                if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                    throw new RuntimeException('カテゴリ画像の保存先を作成できませんでした。');
                }

                $fileName = bin2hex(random_bytes(16)) . '.' . $extension;
                $savedFilePath = $uploadDir . DIRECTORY_SEPARATOR . $fileName;
                if (!move_uploaded_file((string) $file['tmp_name'], $savedFilePath)) {
                    throw new RuntimeException('カテゴリ画像の保存に失敗しました。');
                }
                $iconUrl = '/uploads/categories/' . $fileName;
            }

            Category::create($name, $iconUrl, $pdo);
            $pdo->commit();

            header('Location: categories.php?success=created');
            exit;
        } catch (Throwable $e) {
            if ($pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($savedFilePath !== null && is_file($savedFilePath) && !unlink($savedFilePath)) {
                error_log(sprintf(
                    'Category image cleanup failed. path=%s db_error=%s unlink_error=%s',
                    $savedFilePath,
                    $e->getMessage(),
                    error_get_last()['message'] ?? 'unknown'
                ));
            }

            error_log(sprintf(
                'Category registration failed. path=%s reason=%s',
                $savedFilePath ?? '(no file)',
                $e->getMessage()
            ));
            $errors[] = 'カテゴリの登録に失敗しました。';
        }
    }
}
?>
<?php
$adminTitle = 'カテゴリ追加';
$activeMenu = 'categories';
$breadcrumbs = [
    ['label' => 'カテゴリ管理', 'url' => 'categories.php'],
    ['label' => 'カテゴリ追加'],
];
require __DIR__ . '/includes/header.php';
?>
<div class="admin-page-header"><div><h1>カテゴリ追加</h1><p class="text-muted mb-0">新しい商品分類を登録します。</p></div></div>
    <?php if ($errors): ?>
        <div class="alert alert-danger" role="alert">
            <?php foreach ($errors as $error): ?>
                <div><?= h($error) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <div class="admin-form-section">
            <form method="post" enctype="multipart/form-data">
                <div class="mb-3">
                    <label for="name" class="form-label">カテゴリ名 <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="name" name="name" maxlength="100" value="<?= h($name) ?>" required>
                        <input type="text" class="form-control" id="name" name="name" maxlength="100" value="<?= h($name) ?>" required>
                </div>
                <div class="mb-4">
                    <label for="icon" class="form-label">カテゴリ画像</label>
                    <input type="file" class="form-control" id="icon" name="icon" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                    <div class="form-text">JPG、PNG、WebP / 5MB以下。未指定でも登録できます。</div>
                </div>
                <div class="d-flex justify-content-between mt-4"><a href="categories.php" class="btn btn-outline-secondary">キャンセル</a><button type="submit" class="btn btn-primary">追加する</button></div>
            </form>
    </div>
<?php require __DIR__ . '/includes/footer.php'; ?>