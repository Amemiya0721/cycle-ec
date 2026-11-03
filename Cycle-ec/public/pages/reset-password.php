<?php

declare(strict_types=1);

session_start();
require_once __DIR__ . '/../../src/PasswordReset.php';

if (empty($_SESSION['reset_password_csrf'])) {
    $_SESSION['reset_password_csrf'] = bin2hex(random_bytes(32));
}

$tokenInput = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['token'] ?? null)
    : ($_GET['token'] ?? null);
$token = is_string($tokenInput) ? $tokenInput : '';
$tokenValidFormat = preg_match('/^[a-f0-9]{64}$/D', $token) === 1;
$errors = [];
$completed = isset($_GET['done']) && $_GET['done'] === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$completed) {
    $csrf = $_POST['csrf_token'] ?? null;
    $password = $_POST['password'] ?? null;
    $confirmation = $_POST['password_confirmation'] ?? null;

    if (!is_string($csrf) || !hash_equals((string) $_SESSION['reset_password_csrf'], $csrf)) {
        $errors[] = 'リクエストを確認できませんでした。ページを再読み込みしてください。';
    }
    if (!$tokenValidFormat) {
        $errors[] = '再設定リンクが無効か、有効期限が切れています。';
    }
    if (!is_string($password) || strlen($password) < 8 || strlen($password) > 4096) {
        $errors[] = '新しいパスワードは8文字以上で入力してください。';
    }
    if (!is_string($confirmation) || $password !== $confirmation) {
        $errors[] = 'パスワードが一致しません。';
    }

    if ($errors === []) {
        try {
            if (PasswordReset::reset($token, $password)) {
                $_SESSION['reset_password_csrf'] = bin2hex(random_bytes(32));
                header('Location: reset-password.php?done=1', true, 303);
                exit;
            }
            $errors[] = '再設定リンクが無効か、有効期限が切れています。もう一度再設定メールを申請してください。';
        } catch (Throwable $exception) {
            error_log('Password reset completion failed: ' . $exception->getMessage());
            $errors[] = 'パスワードを更新できませんでした。時間をおいて再度お試しください。';
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
            <?php if ($completed): ?>
                <div class="alert alert-success">パスワードを変更しました。新しいパスワードでログインしてください。</div>
                <a class="btn btn-primary" href="login.php">ログイン</a>
            <?php elseif (!$tokenValidFormat): ?>
                <p>再設定リンクが無効です。新しい再設定メールを申請してください。</p>
                <a class="btn btn-primary" href="forgot-password.php">再設定メールを申請</a>
            <?php else: ?>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= h((string) $_SESSION['reset_password_csrf']) ?>">
                    <input type="hidden" name="token" value="<?= h($token) ?>">
                    <div class="mb-3">
                        <label class="form-label" for="password">新しいパスワード</label>
                        <input class="form-control" type="password" id="password" name="password" minlength="8" maxlength="4096" autocomplete="new-password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password_confirmation">新しいパスワード（確認）</label>
                        <input class="form-control" type="password" id="password_confirmation" name="password_confirmation" minlength="8" maxlength="4096" autocomplete="new-password" required>
                    </div>
                    <button class="btn btn-primary" type="submit">パスワードを変更</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
