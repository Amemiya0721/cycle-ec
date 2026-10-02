<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../src/Database.php';
require_once __DIR__ . '/../../../src/Product.php';
require_once __DIR__ . '/../../../src/Category.php';
require_once __DIR__ . '/../includes/auth.php';
$productConditions = require __DIR__ . '/../../../config/product_conditions.php';

$productId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
}
if ($productId === false || $productId === null || $productId < 1) {
    http_response_code(400);
    exit('商品IDが正しくありません。');
}

$errors = [];
try {
    $pdo = Database::getConnection();
    $product = Product::findById($productId);
    $categories = Category::all();
} catch (PDOException $exception) {
    http_response_code(500);
    exit('商品情報の取得に失敗しました。');
}
if ($product === null) {
    http_response_code(404);
    exit('商品が見つかりません。');
}

// 表示用の初期値（POST時は入力値、それ以外はDBの値）
$specsRaw = Product::specsToLines($product['specs']);
$accessoriesRaw = Product::accessoriesToLines($product['accessories']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'category_id' => filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT),
        'manufacturer' => trim((string) ($_POST['manufacturer'] ?? '')),
        'name' => trim((string) ($_POST['name'] ?? '')),
        'description' => trim((string) ($_POST['description'] ?? '')),
        'price' => $_POST['price'] ?? '',
        'tax_rate' => $_POST['tax_rate'] ?? '',
        'status' => trim((string) ($_POST['status'] ?? '')),
        'product_condition' => trim((string) ($_POST['product_condition'] ?? '')),
        'stock_quantity' => $_POST['stock_quantity'] ?? '0',
        'staff_comment' => trim((string) ($_POST['staff_comment'] ?? '')),
        'is_recommended' => isset($_POST['is_recommended']),
    ];
    $specsRaw = (string) ($_POST['specs_raw'] ?? '');
    $accessoriesRaw = (string) ($_POST['accessories_raw'] ?? '');

    if (
        !$data['category_id']
        || $data['name'] === ''
        || !is_numeric($data['price']) || (float) $data['price'] < 0
        || !is_numeric($data['tax_rate']) || (float) $data['tax_rate'] < 0 || (float) $data['tax_rate'] > 100
        || $data['status'] === ''
        || !array_key_exists($data['product_condition'], $productConditions)
        || !ctype_digit((string) $data['stock_quantity'])
    ) {
        $errors[] = '入力値を確認してください。';
    }
    if (
        Product::isAvailableStatus((string) $data['status'])
        && ctype_digit((string) $data['stock_quantity'])
        && (int) $data['stock_quantity'] === 0
    ) {
        $errors[] = '在庫数が0の商品は販売中にできません。売切れまたは準備中を選択してください。';
    }

    // 画像バリデーション（新規追加分のみ）
    $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];
    $maxFileSize = 5 * 1024 * 1024;
    foreach (['images' => 'メイン', 'condition_images' => '傷・使用感'] as $fieldName => $label) {
        if (!isset($_FILES[$fieldName]) || empty($_FILES[$fieldName]['name'][0])) {
            continue;
        }
        foreach ($_FILES[$fieldName]['tmp_name'] as $key => $tmpName) {
            if ($_FILES[$fieldName]['error'][$key] !== UPLOAD_ERR_OK) {
                $errors[] = "{$label}画像のアップロードに失敗しました。";
                continue;
            }
            if ($_FILES[$fieldName]['size'][$key] > $maxFileSize) {
                $errors[] = "{$label}画像は1枚あたり5MB以下にしてください。";
            }
            $mimeType = mime_content_type($tmpName);
            if (!in_array($mimeType, $allowedMimeTypes, true)) {
                $errors[] = "{$label}画像はJPG、PNG、WebP形式のみアップロードできます。";
            }
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            Product::update($productId, $data);
            Product::replaceSpecs($productId, Product::parseSpecLines($specsRaw), $pdo);
            Product::replaceAccessories($productId, Product::parseAccessoryLines($accessoriesRaw), $pdo);

            // 新規アップロード画像を末尾に追加登録する
            $uploadDir = __DIR__ . '/../../uploads/products/' . $productId;
            foreach (['images' => 'main', 'condition_images' => 'condition'] as $fieldName => $imageType) {
                if (!isset($_FILES[$fieldName]) || empty($_FILES[$fieldName]['name'][0])) {
                    continue;
                }
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $sortOrder = ($imageType === 'main' ? count($product['images']) : count($product['condition_images'])) + 1;

                foreach ($_FILES[$fieldName]['tmp_name'] as $key => $tmpName) {
                    if ($_FILES[$fieldName]['error'][$key] !== UPLOAD_ERR_OK) {
                        continue;
                    }
                    $mimeType = mime_content_type($tmpName);
                    $extension = match ($mimeType) {
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                        'image/webp' => 'webp',
                        default => null,
                    };
                    if ($extension === null) {
                        continue;
                    }
                    $fileName = bin2hex(random_bytes(16)) . '.' . $extension;
                    $filePath = $uploadDir . '/' . $fileName;
                    if (!move_uploaded_file($tmpName, $filePath)) {
                        throw new Exception('画像ファイルの保存に失敗しました。');
                    }
                    $imageUrl = '/uploads/products/' . $productId . '/' . $fileName;
                    Product::addImage($productId, $imageUrl, $imageType, $sortOrder, $pdo);
                    $sortOrder++;
                }
            }

            $pdo->commit();
            header('Location: index.php?success=updated');
            exit;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = '商品の更新に失敗しました。';
        }
    }
    $product = array_merge($product, $data);
}

if (isset($_GET['delete'])) {
    try {
        Product::delete($productId);
        header('Location: index.php?success=deleted');
        exit;
    } catch (PDOException $exception) {
        $errors[] = '商品の削除に失敗しました。';
    }
}

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<?php
$adminTitle = '商品編集';
$activeMenu = 'products';
$breadcrumbs = [
    ['label' => '商品管理', 'url' => 'index.php'],
    ['label' => '商品編集'],
];
require __DIR__ . '/../includes/header.php';
?>
<div class="admin-page-header"><div><h1>商品編集</h1><p class="text-muted mb-0">商品情報を更新します。</p></div></div>
<div class="admin-form-section">
<?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endforeach; ?>
<form method="post" enctype="multipart/form-data">
<input type="hidden" name="product_id" value="<?= (int) $productId ?>">

<div class="mb-3"><label class="form-label" for="category_id">カテゴリ</label><select class="form-select" id="category_id" name="category_id" required><?php foreach ($categories as $category): ?><option value="<?= (int) $category['category_id'] ?>" <?= (int) $product['category_id'] === (int) $category['category_id'] ? 'selected' : '' ?>><?= h((string) $category['name']) ?></option><?php endforeach; ?></select></div>

<div class="mb-3"><label class="form-label" for="manufacturer">メーカー</label><input class="form-control" id="manufacturer" name="manufacturer" maxlength="100" value="<?= h((string) ($product['manufacturer'] ?? '')) ?>"></div>

<div class="mb-3"><label class="form-label" for="name">商品名</label><input class="form-control" id="name" name="name" maxlength="255" value="<?= h((string) $product['name']) ?>" required></div>

<div class="mb-3"><label class="form-label" for="description">商品説明</label><textarea class="form-control" id="description" name="description" rows="5"><?= h((string) ($product['description'] ?? '')) ?></textarea></div>

<div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="is_recommended" id="is_recommended" <?= !empty($product['is_recommended']) ? 'checked' : '' ?>><label class="form-check-label" for="is_recommended">おすすめ商品として表示する</label></div>

<div class="row">
<div class="col-md-6 mb-3"><label class="form-label" for="price">価格</label><input class="form-control" type="number" min="0" step="0.01" id="price" name="price" value="<?= h((string) $product['price']) ?>" required></div>
<div class="col-md-6 mb-3"><label class="form-label" for="tax_rate">税率</label><input class="form-control" type="number" min="0" max="100" step="0.01" id="tax_rate" name="tax_rate" value="<?= h((string) $product['tax_rate']) ?>" required></div>
</div>

<div class="row">
<div class="col-md-6 mb-3"><label class="form-label" for="status">販売ステータス</label><input class="form-control" id="status" name="status" value="<?= h((string) $product['status']) ?>" required></div>
<div class="col-md-6 mb-3"><label class="form-label" for="product_condition">商品状態</label><select class="form-select" id="product_condition" name="product_condition" required><option value="">選択してください</option><?php foreach ($productConditions as $conditionValue => $condition): ?><option value="<?= h($conditionValue) ?>" <?= (string) $product['product_condition'] === (string) $conditionValue ? 'selected' : '' ?>><?= h((string) $condition['label']) ?></option><?php endforeach; ?></select></div>
</div>

<div class="mb-3"><label class="form-label" for="stock_quantity">在庫数</label><input class="form-control" type="number" min="0" step="1" id="stock_quantity" name="stock_quantity" value="<?= h((string) ($product['stock_quantity'] ?? 0)) ?>" required style="max-width:160px"></div>

<div class="mb-3"><label class="form-label" for="specs_raw">スペック</label><textarea class="form-control" id="specs_raw" name="specs_raw" rows="6" placeholder="1行に1項目、「項目名:値」の形式で入力してください"><?= h($specsRaw) ?></textarea><div class="form-text">「項目名:値」の形式で1行ずつ入力してください（保存すると既存のスペックは全て置き換わります）。</div></div>

<div class="mb-3"><label class="form-label" for="accessories_raw">付属品</label><textarea class="form-control" id="accessories_raw" name="accessories_raw" rows="4" placeholder="1行に1項目"><?= h($accessoriesRaw) ?></textarea></div>

<div class="mb-3"><label class="form-label" for="staff_comment">スタッフコメント</label><textarea class="form-control" id="staff_comment" name="staff_comment" rows="3"><?= h((string) ($product['staff_comment'] ?? '')) ?></textarea></div>

<div class="mb-3">
    <label class="form-label">登録済みのメイン画像</label>
    <div class="d-flex flex-wrap gap-2 mb-2">
        <?php if (!empty($product['images'])): ?>
            <?php foreach ($product['images'] as $image): ?>
                <?php $imageUrl = Product::publicImageUrl($image['image_url'] ?? null); ?>
                <div class="border p-1" style="width:90px">
                    <?php if ($imageUrl !== null): ?><img src="<?= h($imageUrl) ?>" class="img-fluid mb-1" alt=""><?php endif; ?>
                    <a class="btn btn-sm btn-outline-danger w-100" href="product-images.php?id=<?= (int) $productId ?>&delete=<?= (int) $image['image_id'] ?>" onclick="return confirm('この画像を削除しますか？')">削除</a>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="text-muted small mb-0">登録画像はありません。</p>
        <?php endif; ?>
    </div>
    <label class="form-label" for="images">メイン画像を追加</label>
    <input type="file" name="images[]" id="images" class="form-control" accept="image/jpeg,image/png,image/webp" multiple>
</div>

<div class="mb-4">
    <label class="form-label">登録済みの傷・使用感写真</label>
    <div class="d-flex flex-wrap gap-2 mb-2">
        <?php if (!empty($product['condition_images'])): ?>
            <?php foreach ($product['condition_images'] as $image): ?>
                <?php $imageUrl = Product::publicImageUrl($image['image_url'] ?? null); ?>
                <div class="border p-1" style="width:90px">
                    <?php if ($imageUrl !== null): ?><img src="<?= h($imageUrl) ?>" class="img-fluid mb-1" alt=""><?php endif; ?>
                    <a class="btn btn-sm btn-outline-danger w-100" href="product-images.php?id=<?= (int) $productId ?>&delete=<?= (int) $image['image_id'] ?>" onclick="return confirm('この画像を削除しますか？')">削除</a>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="text-muted small mb-0">登録画像はありません。</p>
        <?php endif; ?>
    </div>
    <label class="form-label" for="condition_images">傷・使用感写真を追加</label>
    <input type="file" name="condition_images[]" id="condition_images" class="form-control" accept="image/jpeg,image/png,image/webp" multiple>
</div>

<div class="d-flex justify-content-between"><a class="btn btn-outline-secondary" href="index.php">戻る</a><div><a class="btn btn-outline-danger" href="?id=<?= (int) $productId ?>&delete=1" onclick="return confirm('この商品を削除しますか？')">論理削除</a> <button class="btn btn-primary" type="submit">保存</button></div></div>
</form></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
