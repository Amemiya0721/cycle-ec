<?php

declare(strict_types=1);
require_once __DIR__ . '/../../../src/Product.php';
$productId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$productId || $productId < 1) { http_response_code(400); exit('商品IDが正しくありません。'); }
try { $product = Product::findById($productId); } catch (PDOException $exception) { http_response_code(500); exit('商品情報の取得に失敗しました。'); }
if ($product === null) { http_response_code(404); exit('商品が見つかりません。'); }
function h(?string $value): string { return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8'); }
?><!DOCTYPE html><html lang="ja"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>商品画像 | OVERHAUL</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light"><main class="container py-4"><div class="d-flex justify-content-between mb-4"><h1 class="h3">商品画像: <?= h((string) $product['name']) ?></h1><a class="btn btn-outline-secondary" href="product-edit.php?id=<?= (int) $productId ?>">商品編集へ</a></div><div class="row g-3"><?php foreach ($product['images'] as $image): ?><div class="col-6 col-md-3"><div class="card"><img class="card-img-top" src="<?= h((string) $image['image_url']) ?>" alt="<?= h((string) $product['name']) ?>"><div class="card-body"><small>表示順: <?= (int) $image['sort_order'] ?></small></div></div></div><?php endforeach; ?><?php if (empty($product['images'])): ?><p>登録画像はありません。</p><?php endif; ?></div></main></body></html>
