<?php

declare(strict_types=1);

session_start();
require_once __DIR__ . '/../../src/PasswordReset.php';
require_once __DIR__ . '/../../src/Mailer.php';

if (empty($_SESSION['forgot_password_csrf'])) {
    $_SESSION['forgot_password_csrf'] = bin2hex(random_bytes(32));
}

$errors = [];
$sent = false;
$emailInput = $_POST['email'] ?? null;
$email = is_string($emailInput) ? trim($emailInput) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? null;

    if (!is_string($csrf) || !hash_equals((string) $_SESSION['forgot_password_csrf'], $csrf)) {
        $errors[] = 'リクエストを確認できませんでした。ページを再読み込みしてください。';
    } elseif ($email === '' || mb_strlen($email) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'メールアドレスを正しく入力してください。';
    } else {
        try {
            $reset = PasswordReset::issueForEmail($email);
            if ($reset !== null) {
                $config = require __DIR__ . '/../../config/config.php';
                $siteUrl = rtrim(trim((string) ($config['site']['url'] ?? '')), '/');
                if (preg_match('#^https?://[^/\s]+#i', $siteUrl) !== 1) {
                    PasswordReset::invalidate($reset['user_id']);
                    throw new RuntimeException('サイトURL（SITE_URL）が未設定か不正です。');
                }

                $resetUrl = $siteUrl . rtrim((string) $config['app']['base_url'], '/')
                    . '/pages/reset-password.php?token=' . rawurlencode($reset['token']);
                $sent = Mailer::sendTemplateMail(
                    $reset['email'],
                    '【OVERHAUL】パスワード再設定のご案内',
                    'password_reset.html.php',
                    'password_reset.txt.php',
                    ['name' => $reset['name'], 'resetUrl' => $resetUrl]
                );
                if (!$sent) {
                    PasswordReset::invalidate($reset['user_id']);
                }
            }
            $_SESSION['forgot_password_csrf'] = bin2hex(random_bytes(32));
            $sent = true;
        } catch (Throwable $exception) {
            error_log('Password reset request failed: ' . $exception->getMessage());
            $errors[] = '再設定メールを送信できませんでした。時間をおいて再度お試しください。';
        }
    }
}

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

$pageTitle = 'パスワード再設定 | OVERHAUL';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/nav.php';
?>
<main class="container py-5" style="max-width:640px">
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <h1 class="h3 mb-3">パスワード再設定</h1>
            <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endforeach; ?>
            <?php if ($sent): ?>
                <div class="alert alert-info" role="status">アカウントが存在する場合、再設定用のメールを送信しました。メールをご確認ください。</div>
            <?php else: ?>
                <p>登録したメールアドレスを入力してください。</p>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= h((string) $_SESSION['forgot_password_csrf']) ?>">
                    <div class="mb-3">
                        <label class="form-label" for="email">メールアドレス</label>
                        <input class="form-control" type="email" id="email" name="email" maxlength="255" value="<?= h($email) ?>" required>
                    </div>
                    <button class="btn btn-primary" type="submit">再設定メールを送信</button>
                </form>
            <?php endif; ?>
            <p class="mt-3 mb-0"><a href="login.php">ログインへ戻る</a></p>
        </div>
    </div>
</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
