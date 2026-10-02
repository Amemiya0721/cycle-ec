<?php

/**
 * product-create.php
 * -----------------------------------------------------------
 * config.php のDB設定を使用してPDO接続を生成する。
 */

require_once __DIR__ . '/../../../src/Database.php';
require_once __DIR__ . '/../../../src/Product.php';
require_once __DIR__ . '/../../../src/Category.php';
require_once __DIR__ . '/../includes/auth.php';
$productConditions = require __DIR__ . '/../../../config/product_conditions.php';

try {
    $pdo = Database::getConnection();
} catch (PDOException $e) {
    exit('データベースへの接続に失敗しました。');
}

$errors = [];

$categoryId       = $_POST['category_id'] ?? '';
$manufacturer     = trim($_POST['manufacturer'] ?? '');
$name             = trim($_POST['name'] ?? '');
$description      = trim($_POST['description'] ?? '');
$price            = $_POST['price'] ?? '';
$taxRate          = $_POST['tax_rate'] ?? '10';
$status            = $_POST['status'] ?? '';
$productCondition = $_POST['product_condition'] ?? '';
$stockQuantity    = $_POST['stock_quantity'] ?? '0';
$staffComment     = trim($_POST['staff_comment'] ?? '');
$specsRaw         = $_POST['specs_raw'] ?? '';
$accessoriesRaw   = $_POST['accessories_raw'] ?? '';
$isRecommended    = isset($_POST['is_recommended']);
$categories = Category::all();


// ========================================
// POST処理
// ========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ------------------------------------
    // バリデーション
    // ------------------------------------

    if ($categoryId === '') {
        $errors[] = 'カテゴリを選択してください。';
    }

    if ($name === '') {
        $errors[] = '商品名を入力してください。';
    } elseif (mb_strlen($name) > 255) {
        $errors[] = '商品名は255文字以内で入力してください。';
    }

    if (mb_strlen($manufacturer) > 100) {
        $errors[] = 'メーカー名は100文字以内で入力してください。';
    }

    if ($price === '' || !is_numeric($price) || $price < 0) {
        $errors[] = '価格を正しく入力してください。';
    }

    if ($taxRate === '' || !is_numeric($taxRate) || $taxRate < 0 || $taxRate > 100) {
        $errors[] = '税率を正しく入力してください。';
    }

    if ($status === '') {
        $errors[] = '販売ステータスを選択してください。';
    }

    if ($productCondition === '') {
        $errors[] = '商品の状態を選択してください。';
    } elseif (!array_key_exists($productCondition, $productConditions)) {
        $errors[] = '商品の状態を正しく選択してください。';
    }

    if ($stockQuantity === '' || !ctype_digit((string) $stockQuantity)) {
        $errors[] = '在庫数を正しく入力してください。';
    }
    if (
        Product::isAvailableStatus((string) $status)
        && ctype_digit((string) $stockQuantity)
        && (int) $stockQuantity === 0
    ) {
        $errors[] = '在庫数が0の商品は販売中にできません。売切れまたは準備中を選択してください。';
    }


    // ------------------------------------
    // 画像バリデーション（メイン画像 / 傷・使用感写真）
    // ------------------------------------

    $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];
    $maxFileSize = 5 * 1024 * 1024; // 5MB

    foreach (['images' => 'メイン', 'condition_images' => '傷・使用感'] as $fieldName => $label) {
        if (!isset($_FILES[$fieldName]) || empty($_FILES[$fieldName]['name'][0])) {
            continue;
        }

        if (count($_FILES[$fieldName]['name']) > 5) {
            $errors[] = "{$label}画像は最大5枚まで登録できます。";
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


    // ====================================
    // DB登録
    // ====================================

    if (empty($errors)) {

        try {

            $pdo->beginTransaction();

            $productId = Product::create([
                'category_id' => (int) $categoryId,
                'manufacturer' => $manufacturer !== '' ? $manufacturer : null,
                'name' => $name,
                'description' => $description,
                'price' => $price,
                'tax_rate' => $taxRate,
                'status' => $status,
                'product_condition' => $productCondition,
                'stock_quantity' => (int) $stockQuantity,
                'staff_comment' => $staffComment !== '' ? $staffComment : null,
                'is_recommended' => $isRecommended,
            ]);

            Product::replaceSpecs($productId, Product::parseSpecLines($specsRaw), $pdo);
            Product::replaceAccessories($productId, Product::parseAccessoryLines($accessoriesRaw), $pdo);

            // --------------------------------
            // 画像保存（メイン / 傷・使用感）
            // --------------------------------

            $uploadDir = __DIR__ . '/../../uploads/products/' . $productId;

            foreach (['images' => 'main', 'condition_images' => 'condition'] as $fieldName => $imageType) {
                if (!isset($_FILES[$fieldName]) || empty($_FILES[$fieldName]['name'][0])) {
                    continue;
                }

                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $sortOrder = 1;

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


            // --------------------------------
            // コミット
            // --------------------------------

            $pdo->commit();


            header('Location: index.php?success=created');
            exit;


        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $errors[] = '商品の登録に失敗しました。';

            // 開発中のみ
            // $errors[] = $e->getMessage();
        }
    }
}

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<?php
$adminTitle = '商品追加';
$activeMenu = 'products';
$breadcrumbs = [
    ['label' => '商品管理', 'url' => 'index.php'],
    ['label' => '商品追加'],
];
require __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid px-0">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">商品追加</h1>
            <p class="text-muted mb-0">商品情報を入力してください。</p>
        </div>
        <a href="index.php" class="btn btn-outline-secondary">商品一覧へ戻る</a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= h($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body p-4">

            <form method="POST" enctype="multipart/form-data">

                <!-- カテゴリ -->
                <div class="mb-3">
                    <label for="category_id" class="form-label">カテゴリ <span class="text-danger">*</span></label>
                    <select name="category_id" id="category_id" class="form-select" required>
                        <option value="">選択してください</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= (int) $category['category_id'] ?>" <?= (string) $categoryId === (string) $category['category_id'] ? 'selected' : '' ?>>
                                <?= h((string) $category['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="is_recommended" id="is_recommended" <?= $isRecommended ? 'checked' : '' ?>>
                    <label class="form-check-label" for="is_recommended">おすすめ商品として表示する</label>
                </div>

                <!-- メーカー -->
                <div class="mb-3">
                    <label for="manufacturer" class="form-label">メーカー</label>
                    <input type="text" name="manufacturer" id="manufacturer" class="form-control" maxlength="100"
                        value="<?= h($manufacturer) ?>" placeholder="例：Cannondale">
                </div>

                <!-- 商品名 -->
                <div class="mb-3">
                    <label for="name" class="form-label">商品名 <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="name" class="form-control" maxlength="255"
                        value="<?= h($name) ?>" placeholder="例：Cannondale CAAD13 Disc 105" required>
                </div>

                <!-- 商品説明 -->
                <div class="mb-3">
                    <label for="description" class="form-label">商品説明</label>
                    <textarea name="description" id="description" class="form-control" rows="6"
                        placeholder="商品の詳細、使用状況、注意事項など"><?= h($description) ?></textarea>
                </div>

                <!-- 価格・税率 -->
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="price" class="form-label">価格 <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">¥</span>
                            <input type="number" name="price" id="price" class="form-control" min="0" step="1"
                                value="<?= h((string) $price) ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="tax_rate" class="form-label">税率 <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" name="tax_rate" id="tax_rate" class="form-control" min="0" max="100" step="0.01"
                                value="<?= h((string) $taxRate) ?>" required>
                            <span class="input-group-text">%</span>
                        </div>
                    </div>
                </div>

                <!-- ステータス・商品状態 -->
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="status" class="form-label">販売ステータス <span class="text-danger">*</span></label>
                        <select name="status" id="status" class="form-select" required>
                            <option value="">選択してください</option>
                            <option value="販売中" <?= ($status ?? '') === '販売中' ? 'selected' : '' ?>>販売中</option>
                            <option value="売切れ" <?= ($status ?? '') === '売切れ' ? 'selected' : '' ?>>売切れ</option>
                            <option value="準備中" <?= ($status ?? '') === '準備中' ? 'selected' : '' ?>>準備中</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="product_condition" class="form-label">商品の状態 <span class="text-danger">*</span></label>
                        <select name="product_condition" id="product_condition" class="form-select" required>
                            <option value="">選択してください</option>
                            <?php foreach ($productConditions as $conditionValue => $condition): ?>
                                <option value="<?= h($conditionValue) ?>" <?= $productCondition === $conditionValue ? 'selected' : '' ?>>
                                    <?= h((string) $condition['label']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- 在庫数 -->
                <div class="mb-3">
                    <label for="stock_quantity" class="form-label">在庫数 <span class="text-danger">*</span></label>
                    <input type="number" name="stock_quantity" id="stock_quantity" class="form-control" min="0" step="1"
                        value="<?= h((string) $stockQuantity) ?>" required style="max-width: 160px">
                    <div class="form-text">0にすると商品詳細ページで「売り切れ」として表示されます。</div>
                </div>

                <!-- スペック -->
                <div class="mb-3">
                    <label for="specs_raw" class="form-label">スペック</label>
                    <textarea name="specs_raw" id="specs_raw" class="form-control" rows="6"
                        placeholder="1行に1項目、「項目名:値」の形式で入力してください&#10;例：&#10;モデル:CAAD13 Disc 105&#10;年式:2024&#10;サイズ:54&#10;素材:カーボン&#10;重量:1,050g"><?= h($specsRaw) ?></textarea>
                    <div class="form-text">「項目名:値」の形式で1行ずつ入力してください（コロンは半角）。</div>
                </div>

                <!-- 付属品 -->
                <div class="mb-3">
                    <label for="accessories_raw" class="form-label">付属品</label>
                    <textarea name="accessories_raw" id="accessories_raw" class="form-control" rows="4"
                        placeholder="1行に1項目&#10;例：&#10;シートポスト&#10;専用ハンドル&#10;取扱説明書"><?= h($accessoriesRaw) ?></textarea>
                </div>

                <!-- スタッフコメント -->
                <div class="mb-3">
                    <label for="staff_comment" class="form-label">スタッフコメント</label>
                    <textarea name="staff_comment" id="staff_comment" class="form-control" rows="3"
                        placeholder="実際にスタッフが確認したポイントや使用上の注意点など"><?= h($staffComment) ?></textarea>
                </div>

                <!-- メイン画像 -->
                <div class="mb-3">
                    <label for="images" class="form-label">商品画像（メイン）</label>
                    <input type="file" name="images[]" id="images" class="form-control"
                        accept="image/jpeg,image/png,image/webp" multiple>
                    <div class="form-text">最大5枚まで登録できます。JPG / PNG / WebP、1枚あたり5MBまで。1枚目が一覧・関連商品のサムネイルになります。</div>
                </div>

                <!-- 傷・使用感写真 -->
                <div class="mb-4">
                    <label for="condition_images" class="form-label">傷・使用感の写真</label>
                    <input type="file" name="condition_images[]" id="condition_images" class="form-control"
                        accept="image/jpeg,image/png,image/webp" multiple>
                    <div class="form-text">商品詳細ページの「商品状態」タブに表示されます。最大5枚まで。</div>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="index.php" class="btn btn-secondary">キャンセル</a>
                    <button type="submit" class="btn btn-primary">商品を登録</button>
                </div>

            </form>

        </div>
    </div>

</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
