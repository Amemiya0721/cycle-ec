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
$name             = trim($_POST['name'] ?? '');
$description      = trim($_POST['description'] ?? '');
$price            = $_POST['price'] ?? '';
$taxRate          = $_POST['tax_rate'] ?? '10';
$status            = $_POST['status'] ?? '';
$productCondition = $_POST['product_condition'] ?? '';
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


    // ------------------------------------
    // 画像バリデーション
    // ------------------------------------

    if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {

        $imageCount = count($_FILES['images']['name']);

        if ($imageCount > 5) {
            $errors[] = '商品画像は最大5枚まで登録できます。';
        }

        $allowedMimeTypes = [
            'image/jpeg',
            'image/png',
            'image/webp'
        ];

        $maxFileSize = 5 * 1024 * 1024; // 5MB


        foreach ($_FILES['images']['tmp_name'] as $key => $tmpName) {

            if ($_FILES['images']['error'][$key] !== UPLOAD_ERR_OK) {
                $errors[] = '画像のアップロードに失敗しました。';
                continue;
            }

            // ファイルサイズ
            if ($_FILES['images']['size'][$key] > $maxFileSize) {
                $errors[] = '画像は1枚あたり5MB以下にしてください。';
            }

            // MIMEタイプ
            $mimeType = mime_content_type($tmpName);

            if (!in_array($mimeType, $allowedMimeTypes, true)) {
                $errors[] = 'JPG、PNG、WebP形式の画像のみアップロードできます。';
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
                'name' => $name,
                'description' => $description,
                'price' => $price,
                'tax_rate' => $taxRate,
                'status' => $status,
                'product_condition' => $productCondition,
                'is_recommended' => $isRecommended,
            ]);


            // --------------------------------
            // 画像保存先
            // --------------------------------

            $uploadDir = __DIR__ . '/../../uploads/products/' . $productId;


            if (
                isset($_FILES['images']) &&
                !empty($_FILES['images']['name'][0])
            ) {

                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }


                $imageSql = "
                    INSERT INTO product_images (
                        product_id,
                        image_url,
                        sort_order,
                        created_at
                    )
                    VALUES (
                        :product_id,
                        :image_url,
                        :sort_order,
                        NOW()
                    )
                ";

                $imageStmt = $pdo->prepare($imageSql);


                $sortOrder = 1;


                foreach ($_FILES['images']['tmp_name'] as $key => $tmpName) {

                    if ($_FILES['images']['error'][$key] !== UPLOAD_ERR_OK) {
                        continue;
                    }


                    // MIMEタイプから拡張子を決定
                    $mimeType = mime_content_type($tmpName);

                    switch ($mimeType) {

                        case 'image/jpeg':
                            $extension = 'jpg';
                            break;

                        case 'image/png':
                            $extension = 'png';
                            break;

                        case 'image/webp':
                            $extension = 'webp';
                            break;

                        default:
                            continue 2;
                    }


                    // ランダムなファイル名
                    $fileName = bin2hex(random_bytes(16)) . '.' . $extension;

                    $filePath = $uploadDir . '/' . $fileName;


                    // ファイル保存
                    if (!move_uploaded_file($tmpName, $filePath)) {
                        throw new Exception('画像ファイルの保存に失敗しました。');
                    }


                    // DBに保存するURL
                    $imageUrl = '/uploads/products/'
                              . $productId
                              . '/'
                              . $fileName;


                    $imageStmt->execute([
                        ':product_id' => $productId,
                        ':image_url' => $imageUrl,
                        ':sort_order' => $sortOrder
                    ]);


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


    <!-- ============================= -->
    <!-- ヘッダー -->
    <!-- ============================= -->

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h1 class="h3 mb-1">
                商品追加
            </h1>

            <p class="text-muted mb-0">
                商品情報を入力してください。
            </p>

        </div>


        <a
            href="index.php"
            class="btn btn-outline-secondary"
        >
            商品一覧へ戻る
        </a>

    </div>


    <!-- ============================= -->
    <!-- エラー -->
    <!-- ============================= -->

    <?php if (!empty($errors)): ?>

        <div class="alert alert-danger">

            <ul class="mb-0">

                <?php foreach ($errors as $error): ?>

                    <li>
                        <?= htmlspecialchars(
                            $error,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </li>

                <?php endforeach; ?>

            </ul>

        </div>

    <?php endif; ?>


    <!-- ============================= -->
    <!-- 商品追加フォーム -->
    <!-- ============================= -->

    <div class="card shadow-sm">

        <div class="card-body p-4">


            <form
                method="POST"
                enctype="multipart/form-data"
            >


                <!-- ===================== -->
                <!-- カテゴリ -->
                <!-- ===================== -->

                <div class="mb-3">

                    <label
                        for="category_id"
                        class="form-label"
                    >
                        カテゴリ
                        <span class="text-danger">*</span>
                    </label>


                    <select
                        name="category_id"
                        id="category_id"
                        class="form-select"
                        required
                    >

                        <option value="">
                            選択してください
                        </option>

                        <?php foreach ($categories as $category): ?>
                            <option value="<?= (int) $category['category_id'] ?>"
                                <?= (string) $categoryId === (string) $category['category_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string) $category['name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="is_recommended" id="is_recommended" <?= $isRecommended ? 'checked' : '' ?> >
                    <label class="form-check-label" for="is_recommended">おすすめ商品として表示する</label>
                </div>


                <!-- ===================== -->
                <!-- 商品名 -->
                <!-- ===================== -->

                <div class="mb-3">

                    <label
                        for="name"
                        class="form-label"
                    >
                        商品名
                        <span class="text-danger">*</span>
                    </label>


                    <input
                        type="text"
                        name="name"
                        id="name"
                        class="form-control"
                        maxlength="255"
                        value="<?= htmlspecialchars(
                            $name ?? '',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        placeholder="例：Cannondale CAAD13 Disc 105"
                        required
                    >

                </div>


                <!-- ===================== -->
                <!-- 商品説明 -->
                <!-- ===================== -->

                <div class="mb-3">

                    <label
                        for="description"
                        class="form-label"
                    >
                        商品説明
                    </label>


                    <textarea
                        name="description"
                        id="description"
                        class="form-control"
                        rows="6"
                        placeholder="商品の詳細、使用状況、注意事項など"
                    ><?= htmlspecialchars(
                        $description ?? '',
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?></textarea>

                </div>


                <!-- ===================== -->
                <!-- 価格・税率 -->
                <!-- ===================== -->

                <div class="row">


                    <div class="col-md-6 mb-3">

                        <label
                            for="price"
                            class="form-label"
                        >
                            価格
                            <span class="text-danger">*</span>
                        </label>


                        <div class="input-group">

                            <span class="input-group-text">
                                ¥
                            </span>

                            <input
                                type="number"
                                name="price"
                                id="price"
                                class="form-control"
                                min="0"
                                step="1"
                                value="<?= htmlspecialchars(
                                    $price ?? '',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                required
                            >

                        </div>

                    </div>


                    <div class="col-md-6 mb-3">

                        <label
                            for="tax_rate"
                            class="form-label"
                        >
                            税率
                            <span class="text-danger">*</span>
                        </label>


                        <div class="input-group">

                            <input
                                type="number"
                                name="tax_rate"
                                id="tax_rate"
                                class="form-control"
                                min="0"
                                max="100"
                                step="0.01"
                                value="<?= htmlspecialchars(
                                    $taxRate ?? '10',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                required
                            >

                            <span class="input-group-text">
                                %
                            </span>

                        </div>

                    </div>


                </div>


                <!-- ===================== -->
                <!-- ステータス -->
                <!-- ===================== -->

                <div class="mb-3">

                    <label
                        for="status"
                        class="form-label"
                    >
                        販売ステータス
                        <span class="text-danger">*</span>
                    </label>


                    <select
                        name="status"
                        id="status"
                        class="form-select"
                        required
                    >

                        <option value="">
                            選択してください
                        </option>

                        <option value="販売中"
                            <?= ($status ?? '') === '販売中'
                                ? 'selected'
                                : '' ?>>
                            販売中
                        </option>

                        <option value="売切れ"
                            <?= ($status ?? '') === '売切れ'
                                ? 'selected'
                                : '' ?>>
                            売切れ
                        </option>

                        <option value="準備中"
                            <?= ($status ?? '') === '準備中'
                                ? 'selected'
                                : '' ?>>
                            準備中
                        </option>

                    </select>

                </div>


                <!-- ===================== -->
                <!-- 商品状態 -->
                <!-- ===================== -->

                <div class="mb-3">

                    <label
                        for="product_condition"
                        class="form-label"
                    >
                        商品の状態
                        <span class="text-danger">*</span>
                    </label>


                    <select
                        name="product_condition"
                        id="product_condition"
                        class="form-select"
                        required
                    >

                        <option value="">
                            選択してください
                        </option>

                        <?php foreach ($productConditions as $conditionValue => $condition): ?>
                            <option value="<?= htmlspecialchars($conditionValue, ENT_QUOTES, 'UTF-8') ?>" <?= $productCondition === $conditionValue ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string) $condition['label'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- ===================== -->
                <!-- 商品画像 -->
                <!-- ===================== -->

                <div class="mb-4">

                    <label
                        for="images"
                        class="form-label"
                    >
                        商品画像
                    </label>


                    <input
                        type="file"
                        name="images[]"
                        id="images"
                        class="form-control"
                        accept="image/jpeg,image/png,image/webp"
                        multiple
                    >


                    <div class="form-text">

                        最大5枚まで登録できます。
                        JPG / PNG / WebP
                        1枚あたり5MBまで。

                    </div>

                </div>


                <!-- ===================== -->
                <!-- 登録ボタン -->
                <!-- ===================== -->

                <div class="d-flex justify-content-end gap-2">


                    <a
                        href="index.php"
                        class="btn btn-secondary"
                    >
                        キャンセル
                    </a>


                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        商品を登録
                    </button>


                </div>


            </form>

        </div>

    </div>

</div>


<?php require __DIR__ . '/../includes/footer.php'; ?>