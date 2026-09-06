<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../src/Product.php';
require_once __DIR__ . '/../../../src/Category.php';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'category_id' => filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT),
        'name' => trim((string) ($_POST['name'] ?? '')),
        'description' => trim((string) ($_POST['description'] ?? '')),
        'price' => $_POST['price'] ?? '',
        'tax_rate' => $_POST['tax_rate'] ?? '',
        'status' => trim((string) ($_POST['status'] ?? '')),
        'product_condition' => trim((string) ($_POST['product_condition'] ?? '')),
    ];
    if (!$data['category_id'] || $data['name'] === '' || !is_numeric($data['price']) || (float) $data['price'] < 0 || !is_numeric($data['tax_rate']) || (float) $data['tax_rate'] < 0 || (float) $data['tax_rate'] > 100 || $data['status'] === '' || $data['product_condition'] === '') {
        $errors[] = '入力値を確認してください。';
    }
    if (empty($errors)) {
        try {
            Product::update($productId, $data);
            header('Location: index.php?success=updated');
            exit;
        } catch (PDOException $exception) {
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
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>商品編集 | OVERHAUL</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-dark bg-dark"><div class="container"><a class="navbar-brand" href="index.php">商品管理</a></div></nav>
<main class="container py-4"><div class="card shadow-sm"><div class="card-body p-4">
<h1 class="h3 mb-4">商品編集</h1>
<?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endforeach; ?>
<form method="post"><input type="hidden" name="product_id" value="<?= (int) $productId ?>">
<div class="mb-3"><label class="form-label" for="category_id">カテゴリ</label><select class="form-select" id="category_id" name="category_id" required><?php foreach ($categories as $category): ?><option value="<?= (int) $category['category_id'] ?>" <?= (int) $product['category_id'] === (int) $category['category_id'] ? 'selected' : '' ?>><?= h((string) $category['name']) ?></option><?php endforeach; ?></select></div>
<div class="mb-3"><label class="form-label" for="name">商品名</label><input class="form-control" id="name" name="name" maxlength="255" value="<?= h((string) $product['name']) ?>" required></div>
<div class="mb-3"><label class="form-label" for="description">商品説明</label><textarea class="form-control" id="description" name="description" rows="5"><?= h((string) ($product['description'] ?? '')) ?></textarea></div>
<div class="row"><div class="col-md-6 mb-3"><label class="form-label" for="price">価格</label><input class="form-control" type="number" min="0" step="0.01" id="price" name="price" value="<?= h((string) $product['price']) ?>" required></div><div class="col-md-6 mb-3"><label class="form-label" for="tax_rate">税率</label><input class="form-control" type="number" min="0" max="100" step="0.01" id="tax_rate" name="tax_rate" value="<?= h((string) $product['tax_rate']) ?>" required></div></div>
<div class="row"><div class="col-md-6 mb-3"><label class="form-label" for="status">販売ステータス</label><input class="form-control" id="status" name="status" value="<?= h((string) $product['status']) ?>" required></div><div class="col-md-6 mb-3"><label class="form-label" for="product_condition">商品状態</label><input class="form-control" id="product_condition" name="product_condition" value="<?= h((string) $product['product_condition']) ?>" required></div></div>
<div class="d-flex justify-content-between"><a class="btn btn-outline-secondary" href="index.php">戻る</a><div><a class="btn btn-outline-danger" href="?id=<?= (int) $productId ?>&delete=1" onclick="return confirm('この商品を削除しますか？')">論理削除</a> <button class="btn btn-primary" type="submit">保存</button></div></div>
</form></div></div></main>
</body></html>
