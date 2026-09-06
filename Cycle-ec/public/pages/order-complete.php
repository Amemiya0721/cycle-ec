<?php

declare(strict_types=1);
$orderId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
?><!DOCTYPE html><html lang="ja"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>注文完了 | OVERHAUL</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light"><main class="container py-5"><div class="alert alert-success"><h1 class="h4">ご注文ありがとうございます</h1><p class="mb-0">注文番号: <?= (int) $orderId ?></p></div><a class="btn btn-primary" href="products.php">商品一覧へ</a></main></body></html>
