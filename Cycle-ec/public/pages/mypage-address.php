<?php

declare(strict_types=1);

$config = require __DIR__ . '/../../config/config.php';
$baseUrl = $config['app']['base_url'];

require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/UserAddress.php';

Auth::start();
$userId = Auth::requireLogin($baseUrl . 'pages/login.php');

if (!function_exists('h')) {
    function h(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}

$errors = [];
$errorMessage = null;
$saved = isset($_GET['saved']);

$addr = UserAddress::findByUserId($userId) ?? array_fill_keys(UserAddress::FIELDS, '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        $errorMessage = '不正なリクエストです。ページを再読み込みしてやり直してください。';
    } else {
        $addr = UserAddress::normalize($_POST);
        $errors = UserAddress::validate($addr);

        if ($errors === []) {
            try {
                UserAddress::save($userId, $addr);
                header('Location: mypage-address.php?saved=1');
                exit;
            } catch (PDOException $exception) {
                error_log('Address save failed: ' . $exception->getMessage());
                $errorMessage = '住所の保存に失敗しました。時間をおいてやり直してください。';
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/nav.php';
?>

<main>
    <div class="container py-5" style="max-width: 720px;">
        <h1 class="h3 mb-4">お届け先住所</h1>

        <?php if ($saved): ?>
            <div class="alert alert-success">住所を保存しました。</div>
        <?php endif; ?>
        <?php if ($errorMessage !== null): ?>
            <div class="alert alert-danger"><?= h($errorMessage) ?></div>
        <?php endif; ?>

        <form method="post" novalidate>
            <input type="hidden" name="csrf_token" value="<?= h(Auth::csrfToken()) ?>">
            <?php require __DIR__ . '/../includes/address_fields.php'; ?>
            <div class="mt-4">
                <button type="submit" class="btn btn-primary">保存する</button>
            </div>
        </form>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
