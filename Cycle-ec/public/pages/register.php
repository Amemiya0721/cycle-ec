<?php

declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../src/User.php';

$errors = [];
$name = trim((string) ($_POST['name'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirmation = (string) ($_POST['password_confirmation'] ?? '');
    if ($name === '' || mb_strlen($name) > 100) $errors[] = '名前を入力してください。';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255) $errors[] = 'メールアドレスを正しく入力してください。';
    if (strlen($password) < 8) $errors[] = 'パスワードは8文字以上で入力してください。';
    if ($password !== $passwordConfirmation) $errors[] = 'パスワードが一致しません。';
    if (!$errors) {
        try {
            if (User::findByEmail($email) !== null) {
                $errors[] = 'このメールアドレスは登録済みです。';
            } else {
                User::create($name, $email, $password);
                header('Location: login.php?registered=1');
                exit;
            }
        } catch (PDOException $exception) {
            $errors[] = '登録処理に失敗しました。';
        }
    }
}
function h(?string $value): string { return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8'); }
?><!DOCTYPE html><html lang="ja"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>新規登録 | OVERHAUL</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light"><main class="container py-5" style="max-width:640px"><div class="card shadow-sm"><div class="card-body p-4"><h1 class="h3 mb-4">新規登録</h1><?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endforeach; ?><form method="post"><div class="mb-3"><label class="form-label" for="name">名前</label><input class="form-control" id="name" name="name" maxlength="100" value="<?= h($name) ?>" required></div><div class="mb-3"><label class="form-label" for="email">メールアドレス</label><input class="form-control" type="email" id="email" name="email" maxlength="255" value="<?= h($email) ?>" required></div><div class="mb-3"><label class="form-label" for="password">パスワード</label><input class="form-control" type="password" id="password" name="password" minlength="8" required></div><div class="mb-4"><label class="form-label" for="password_confirmation">パスワード（確認）</label><input class="form-control" type="password" id="password_confirmation" name="password_confirmation" minlength="8" required></div><button class="btn btn-primary w-100" type="submit">登録する</button></form><p class="mt-3 mb-0 text-center"><a href="login.php">ログインはこちら</a></p></div></div></main></body></html>
