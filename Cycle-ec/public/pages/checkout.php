<?php

declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../src/Order.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$productIds = array_values(array_filter($_SESSION['cart'] ?? [], 'is_numeric'));
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shipping = [
        'postal_code' => trim((string) ($_POST['postal_code'] ?? '')),
        'prefecture' => trim((string) ($_POST['prefecture'] ?? '')),
        'city' => trim((string) ($_POST['city'] ?? '')),
        'address_line' => trim((string) ($_POST['address_line'] ?? '')),
        'building' => trim((string) ($_POST['building'] ?? '')),
        'recipient_name' => trim((string) ($_POST['recipient_name'] ?? '')),
        'phone_number' => trim((string) ($_POST['phone_number'] ?? '')),
    ];
    if (in_array('', [$shipping['postal_code'], $shipping['prefecture'], $shipping['city'], $shipping['address_line'], $shipping['recipient_name'], $shipping['phone_number']], true)) {
        $errors[] = '必須項目を入力してください。';
    }
    if (!$productIds) {
        $errors[] = 'カートに商品がありません。';
    }
    if (!$errors) {
        try {
            $orderId = Order::create((int) $_SESSION['user_id'], $shipping, $productIds);
            $_SESSION['cart'] = [];
            header('Location: order-complete.php?id=' . $orderId);
            exit;
        } catch (InvalidArgumentException $exception) {
            $errors[] = $exception->getMessage();
        } catch (PDOException $exception) {
            $errors[] = '注文処理に失敗しました。';
        }
    }
}
function h(?string $value): string { return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8'); }
?><!DOCTYPE html><html lang="ja"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>注文手続き | OVERHAUL</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light"><main class="container py-5"><h1 class="h3 mb-4">注文手続き</h1><?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endforeach; ?><form method="post" class="card card-body"><div class="mb-3"><label class="form-label">郵便番号</label><input class="form-control" name="postal_code" required></div><div class="mb-3"><label class="form-label">都道府県</label><input class="form-control" name="prefecture" required></div><div class="mb-3"><label class="form-label">市区町村</label><input class="form-control" name="city" required></div><div class="mb-3"><label class="form-label">住所</label><input class="form-control" name="address_line" required></div><div class="mb-3"><label class="form-label">建物名</label><input class="form-control" name="building"></div><div class="mb-3"><label class="form-label">宛名</label><input class="form-control" name="recipient_name" required></div><div class="mb-3"><label class="form-label">電話番号</label><input class="form-control" name="phone_number" required></div><button class="btn btn-primary" type="submit">注文を確定する</button></form></main></body></html>
