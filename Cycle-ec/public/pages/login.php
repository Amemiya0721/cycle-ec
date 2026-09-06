<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../../src/User.php';
require_once __DIR__ . '/../../src/AdminLoginVerification.php';
require_once __DIR__ . '/../../src/Mailer.php';

$errors = [];
$email = trim((string) ($_POST['email'] ?? ''));
if (empty($_SESSION['login_csrf'])) {
    $_SESSION['login_csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string) ($_POST['password'] ?? '');
    $csrf = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals((string) $_SESSION['login_csrf'], $csrf)) {
        $errors[] = '不正なリクエストです。もう一度お試しください。';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
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
                $_SESSION['password_authenticated'] = true;
                $_SESSION['admin_verified'] = false;
                $_SESSION['admin_pending'] = (int) ($user['is_admin'] ?? 0) === 1;
                unset($_SESSION['admin_verification_id']);

                if ((int) ($user['is_admin'] ?? 0) === 1) {
                    $code = (string) random_int(100000, 999999);
                    $verificationId = AdminLoginVerification::issue((int) $user['user_id'], $code);
                    if (!Mailer::sendAdminLoginCode((string) $user['email'], $code)) {
                        AdminLoginVerification::invalidate($verificationId);
                        unset(
                            $_SESSION['password_authenticated'],
                            $_SESSION['admin_pending'],
                            $_SESSION['admin_verification_id'],
                            $_SESSION['admin_verified']
                        );
                        $errors[] = '確認メールを送信できませんでした。時間をおいて再度お試しください。';
                    } else {
                        $_SESSION['admin_verification_id'] = $verificationId;
                        header('Location: admin_verify.php');
                        exit;
                    }
                } else {
                    header('Location: ../index.php');
                    exit;
                }
            }
        } catch (Throwable $exception) {
            error_log('Login processing failed: ' . $exception->getMessage());
            $errors[] = 'ログイン処理に失敗しました。';
        }
    }
}

function h(?string $value): string { return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8'); }
?><!DOCTYPE html>
<html lang="ja"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>ログイン | OVERHAUL</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-light"><main class="container py-5" style="max-width:640px"><div class="card shadow-sm"><div class="card-body p-4"><h1 class="h3 mb-4">ログイン</h1><?php if (isset($_GET['registered'])): ?><div class="alert alert-success">登録が完了しました。ログインしてください。</div><?php endif; ?><?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endforeach; ?><form method="post"><input type="hidden" name="csrf_token" value="<?= h((string) $_SESSION['login_csrf']) ?>"><div class="mb-3"><label class="form-label" for="email">メールアドレス</label><input class="form-control" type="email" id="email" name="email" value="<?= h($email) ?>" required></div><div class="mb-4"><label class="form-label" for="password">パスワード</label><input class="form-control" type="password" id="password" name="password" required></div><button class="btn btn-primary w-100" type="submit">ログイン</button></form><p class="mt-3 mb-0 text-center"><a href="register.php">新規登録はこちら</a></p></div></div></main></body></html>
