<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../../src/BankTransferSettings.php';

if (empty($_SESSION['bank_transfer_csrf'])) {
    $_SESSION['bank_transfer_csrf'] = bin2hex(random_bytes(32));
}

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

$errors = [];
$successMessage = null;
$settings = [
    'bank_name' => '',
    'branch_name' => '',
    'account_type' => '',
    'account_number' => '',
    'account_holder' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? null;
    if (!is_string($csrf) || !hash_equals((string) $_SESSION['bank_transfer_csrf'], $csrf)) {
        http_response_code(403);
        exit('不正なリクエストです。');
    }

    foreach (array_keys($settings) as $key) {
        $value = $_POST[$key] ?? null;
        $settings[$key] = is_string($value) ? trim($value) : '';
    }

    $limits = [
        'bank_name' => 100,
        'branch_name' => 100,
        'account_type' => 30,
        'account_number' => 50,
        'account_holder' => 100,
    ];
    foreach ($settings as $key => $value) {
        if ($value === '') {
            $errors[] = 'すべての項目を入力してください。';
            break;
        }
        if (mb_strlen($value) > $limits[$key]) {
            $errors[] = '各項目の文字数が上限を超えています。';
            break;
        }
    }

    if ($errors === []) {
        try {
            BankTransferSettings::save($settings);
            $successMessage = '振込先情報を保存しました。以後の注文確認メールに反映されます。';
        } catch (Throwable $exception) {
            error_log('Bank transfer settings save failed: ' . $exception->getMessage());
            $errors[] = '振込先情報を保存できませんでした。DBマイグレーションの適用状況を確認してください。';
        }
    }
}

try {
    $savedSettings = BankTransferSettings::get();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $errors === []) {
        $settings = $savedSettings;
    }
} catch (PDOException $exception) {
    error_log('Bank transfer settings fetch failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('振込先情報を取得できません。先に銀行振込のDBマイグレーションを適用してください。');
}

$adminTitle = '振込先管理';
$activeMenu = 'bank-transfer';
$breadcrumbs = [['label' => '振込先管理']];
require __DIR__ . '/includes/header.php';
?>
<div class="admin-page-header">
    <div>
        <h1>振込先管理</h1>
        <p class="text-muted mb-0">ここで登録した口座情報は、注文確認メールにのみ記載されます。</p>
    </div>
</div>

<?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endforeach; ?>
<?php if ($successMessage !== null): ?><div class="alert alert-success"><?= h($successMessage) ?></div><?php endif; ?>

<section class="admin-card bg-white p-3">
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= h((string) $_SESSION['bank_transfer_csrf']) ?>">
        <div class="mb-3">
            <label class="form-label" for="bank_name">銀行名</label>
            <input class="form-control" id="bank_name" name="bank_name" maxlength="100" value="<?= h((string) $settings['bank_name']) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label" for="branch_name">支店名</label>
            <input class="form-control" id="branch_name" name="branch_name" maxlength="100" value="<?= h((string) $settings['branch_name']) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label" for="account_type">口座種別</label>
            <input class="form-control" id="account_type" name="account_type" maxlength="30" placeholder="普通・当座など" value="<?= h((string) $settings['account_type']) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label" for="account_number">口座番号</label>
            <input class="form-control" id="account_number" name="account_number" maxlength="50" value="<?= h((string) $settings['account_number']) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label" for="account_holder">口座名義</label>
            <input class="form-control" id="account_holder" name="account_holder" maxlength="100" value="<?= h((string) $settings['account_holder']) ?>" required>
        </div>
        <button class="btn btn-primary" type="submit">振込先を保存</button>
    </form>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
