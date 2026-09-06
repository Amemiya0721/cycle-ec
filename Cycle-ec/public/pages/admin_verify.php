<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../../src/AdminLoginVerification.php';
require_once __DIR__ . '/../../src/Mailer.php';
require_once __DIR__ . '/../../src/User.php';

if (empty($_SESSION['password_authenticated']) || empty($_SESSION['admin_pending']) || empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$errors = [];
$messages = [];
$userId = (int) $_SESSION['user_id'];
$verificationId = (int) ($_SESSION['admin_verification_id'] ?? 0);
$user = User::findById($userId);
if ($user === null || (int) ($user['is_admin'] ?? 0) !== 1) {
    header('Location: logout.php');
    exit;
}

if (empty($_SESSION['admin_verify_csrf'])) {
    $_SESSION['admin_verify_csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals((string) $_SESSION['admin_verify_csrf'], $csrf)) {
        $errors[] = '不正なリクエストです。';
    } elseif (($_POST['action'] ?? '') === 'resend') {
        try {
            $waitSeconds = AdminLoginVerification::resendWaitSeconds($userId);
            if ($waitSeconds > 0) {
                $errors[] = "確認コードは{$waitSeconds}秒後に再送できます。";
            } else {
                $code = (string) random_int(100000, 999999);
                $newVerificationId = AdminLoginVerification::issue($userId, $code);
                if (!Mailer::sendAdminLoginCode((string) $user['email'], $code)) {
                    AdminLoginVerification::invalidate($newVerificationId);
                    $errors[] = '確認メールを送信できませんでした。時間をおいて再度お試しください。';
                } else {
                    $_SESSION['admin_verification_id'] = $newVerificationId;
                    $verificationId = $newVerificationId;
                    $messages[] = '確認コードを再送しました。';
                }
            }
        } catch (Throwable $exception) {
            error_log('Admin verification resend failed: ' . $exception->getMessage());
            $errors[] = '確認コードを再送できませんでした。';
        }
    } else {
        $code = trim((string) ($_POST['code'] ?? ''));
        $verification = AdminLoginVerification::findActive($verificationId, $userId);
        if ($verification === null) {
            $errors[] = '確認コードの有効期限が切れています。再送してください。';
        } elseif (new DateTimeImmutable((string) $verification['expires_at']) < new DateTimeImmutable()) {
            AdminLoginVerification::invalidate($verificationId);
            $errors[] = '確認コードの有効期限が切れています。再送してください。';
        } elseif (!preg_match('/^\d{6}$/', $code) || !password_verify($code, (string) $verification['code_hash'])) {
            $attempts = AdminLoginVerification::incrementAttempts($verificationId);
            if ($attempts >= AdminLoginVerification::MAX_ATTEMPTS) {
                header('Location: logout.php?error=verification_attempts');
                exit;
            }
            $errors[] = '確認コードが正しくありません。';
        } elseif (!AdminLoginVerification::consume($verificationId, $userId)) {
            $errors[] = '確認コードを認証できませんでした。再送してください。';
        } else {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $userId;
            $_SESSION['user_name'] = (string) $user['name'];
            $_SESSION['password_authenticated'] = true;
            $_SESSION['admin_verified'] = true;
            unset($_SESSION['admin_pending'], $_SESSION['admin_verification_id']);
            header('Location: ../admin/index.php');
            exit;
        }
    }
}

function h(?string $value): string { return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8'); }
?><!DOCTYPE html>
<html lang="ja"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>管理者確認 | OVERHAUL</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-light"><main class="container py-5" style="max-width:640px"><div class="card shadow-sm"><div class="card-body p-4"><h1 class="h3 mb-3">管理者ログイン確認</h1><p class="text-muted">登録済みメールアドレスへ送信した6桁の確認コードを入力してください。コードは10分間有効です。</p><?php foreach ($messages as $message): ?><div class="alert alert-success"><?= h($message) ?></div><?php endforeach; ?><?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endforeach; ?><form method="post" class="mb-3"><input type="hidden" name="csrf_token" value="<?= h((string) $_SESSION['admin_verify_csrf']) ?>"><input type="hidden" name="action" value="verify"><label class="form-label" for="code">確認コード</label><input class="form-control form-control-lg mb-3" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" id="code" name="code" autocomplete="one-time-code" required><button class="btn btn-primary w-100" type="submit">確認する</button></form><form method="post" class="text-center"><input type="hidden" name="csrf_token" value="<?= h((string) $_SESSION['admin_verify_csrf']) ?>"><input type="hidden" name="action" value="resend"><button class="btn btn-link" type="submit">コードが届かない場合：確認コードを再送</button></form><p class="text-center mb-0"><a href="logout.php">ログイン画面へ戻る</a></p></div></div></main></body></html>