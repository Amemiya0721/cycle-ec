<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../../src/Mailer.php';

if (empty($_SESSION['mail_test_csrf'])) {
    $_SESSION['mail_test_csrf'] = bin2hex(random_bytes(32));
}

$config = require __DIR__ . '/../../config/config.php';
$recipient = (string) ($config['smtp']['username'] ?? '');
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals((string) $_SESSION['mail_test_csrf'], $csrf)) {
        $errors[] = '不正なリクエストです。';
    } elseif (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'SMTP_USERNAMEが未設定です。プロジェクト直下の.envに設定してください。';
    } elseif (Mailer::sendTestMail($recipient)) {
        $success = true;
    } else {
        $errors[] = 'メールを送信できませんでした。SMTP設定とアプリパスワードを確認してください。';
    }
}

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>メール送信テスト | OVERHAUL</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<main class="container py-5" style="max-width: 720px">
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <h1 class="h3 mb-3">メール送信テスト</h1>
            <p class="text-muted">設定済みのSMTP_USERNAME宛にテストメールを送信します。</p>
            <?php if ($success): ?><div class="alert alert-success">テストメールを送信しました。受信トレイを確認してください。</div><?php endif; ?>
            <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endforeach; ?>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= h((string) $_SESSION['mail_test_csrf']) ?>">
        <div class="mb-3">
            <label class="form-label">送信先</label>
            <div class="form-control-plaintext"><?= h($recipient) ?></div>
        </div>
        <button class="btn btn-primary" type="submit">テストメールを送信</button>
    </form>
        </div>
    </div>
</main>
</body>
</html>
