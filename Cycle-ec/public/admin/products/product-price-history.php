<?php

declare(strict_types=1);
require_once __DIR__ . '/../../../src/Product.php';
require_once __DIR__ . '/../includes/auth.php';
$productId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$productId || $productId < 1) { http_response_code(400); exit('商品IDが正しくありません。'); }
try { $product = Product::findById($productId); $history = Product::priceHistory($productId); } catch (PDOException $exception) { http_response_code(500); exit('価格履歴の取得に失敗しました。'); }
if ($product === null) { http_response_code(404); exit('商品が見つかりません。'); }
function h(?string $value): string { return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8'); }
?><!DOCTYPE html><html lang="ja"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>価格履歴 | OVERHAUL</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light"><main class="container py-4"><div class="d-flex justify-content-between mb-4"><h1 class="h3">価格履歴: <?= h((string) $product['name']) ?></h1><a class="btn btn-outline-secondary" href="product-edit.php?id=<?= (int) $productId ?>">商品編集へ</a></div><div class="card"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>価格</th><th>税率</th><th>登録日時</th></tr></thead><tbody><?php foreach ($history as $row): ?><tr><td><?= number_format((float) $row['price']) ?>円</td><td><?= h((string) $row['tax_rate']) ?>%</td><td><?= h((string) $row['created_at']) ?></td></tr><?php endforeach; ?><?php if (empty($history)): ?><tr><td colspan="3">価格履歴はありません。</td></tr><?php endif; ?></tbody></table></div></div></main></body></html>
