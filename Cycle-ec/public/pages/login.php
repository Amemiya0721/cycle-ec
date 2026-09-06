<?php

declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../src/User.php';

$errors = [];
$email = trim((string) ($_POST['email'] ?? ''));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string) ($_POST['password'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $errors[] = 'メールアドレスとパスワードを入力してください。';
    } else {
        try {
            $user = User::findByEmail($email);
            if ($user === null || !password_verify($password, (string) $user['password'])) {
                $errors[] = 'メールアドレスまたはパスワードが正しくありません。';
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int) $user['user_id'];
                $_SESSION['user_name'] = (string) $user['name'];
                header('Location: ../index.php');
                exit;
            }
        } catch (PDOException $exception) {
            $errors[] = 'ログイン処理に失敗しました。';
        }
    }
}
function h(?string $value): string { return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8'); }
?><!DOCTYPE html><html lang="ja"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>ログイン | OVERHAUL</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light"><main class="container py-5" style="max-width:640px"><div class="card shadow-sm"><div class="card-body p-4"><h1 class="h3 mb-4">ログイン</h1><?php if (isset($_GET['registered'])): ?><div class="alert alert-success">登録が完了しました。ログインしてください。</div><?php endif; ?><?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endforeach; ?><form method="post"><div class="mb-3"><label class="form-label" for="email">メールアドレス</label><input class="form-control" type="email" id="email" name="email" value="<?= h($email) ?>" required></div><div class="mb-4"><label class="form-label" for="password">パスワード</label><input class="form-control" type="password" id="password" name="password" required></div><button class="btn btn-primary w-100" type="submit">ログイン</button></form><p class="mt-3 mb-0 text-center"><a href="register.php">新規登録はこちら</a></p></div></div></main></body></html>
