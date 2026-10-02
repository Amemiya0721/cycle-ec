<!--
お問い合わせを登録されているメールアドレスに送信します。
-->

<?php


// CSRFトークン生成
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$success = false;

$name = '';
$email = '';
$category = '';
$message = '';

$categories = [
    '商品について',
    '注文・配送について',
    '返品・キャンセルについて',
    '不具合・その他',
];

// HTMLエスケープ
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

// フォーム送信処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $category = (string) ($_POST['category'] ?? '');
    $message = trim((string) ($_POST['message'] ?? ''));
    $csrfToken = (string) ($_POST['csrf_token'] ?? '');

    // CSRF検証
    if (
        !hash_equals(
            (string) $_SESSION['csrf_token'],
            $csrfToken
        )
    ) {
        $errors[] = '不正なリクエストです。ページを再読み込みしてください。';
    }

    // 入力値検証
    if ($name === '') {
        $errors[] = 'お名前を入力してください。';
    } elseif (mb_strlen($name) > 100) {
        $errors[] = 'お名前は100文字以内で入力してください。';
    }

    if ($email === '') {
        $errors[] = 'メールアドレスを入力してください。';
    } elseif (
        mb_strlen($email) > 254 ||
        !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {
        $errors[] = '正しいメールアドレスを入力してください。';
    }

    if (!in_array($category, $categories, true)) {
        $errors[] = 'お問い合わせ種別を選択してください。';
    }

    if ($message === '') {
        $errors[] = 'お問い合わせ内容を入力してください。';
    } elseif (mb_strlen($message) > 5000) {
        $errors[] = 'お問い合わせ内容は5000文字以内で入力してください。';
    }

    // 現段階では入力確認のみ。実際の送信は行わない。
    if (empty($errors)) {
        $success = true;
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/nav.php';
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>お問い合わせ | Cycle-ec</title>
</head>
<body>

<main class="contact-container">
    <h1>お問い合わせ</h1>
    <p class="description">
        商品やご注文についてのご質問はこちらからお問い合わせください。
    </p>

    <?php if ($success): ?>
        <div class="success-box" role="status">
            <strong>入力内容を確認しました。</strong>
            <p>
                現在、このページではお問い合わせの送信処理は実装されていません。
                入力内容は保存・送信されていません。
            </p>
        </div>
    <?php else: ?>

        <?php if (!empty($errors)): ?>
            <div class="error-box" role="alert">
                <strong>入力内容をご確認ください。</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($_SESSION['csrf_token']) ?>"
            >

            <div class="form-group">
                <label for="name">
                    お名前<span class="required">必須</span>
                </label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    maxlength="100"
                    autocomplete="name"
                    value="<?= e($name) ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="email">
                    メールアドレス<span class="required">必須</span>
                </label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    maxlength="254"
                    autocomplete="email"
                    value="<?= e($email) ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="category">
                    お問い合わせ種別<span class="required">必須</span>
                </label>
                <select id="category" name="category" required>
                    <option value="">選択してください</option>
                    <?php foreach ($categories as $item): ?>
                        <option
                            value="<?= e($item) ?>"
                            <?= $category === $item ? 'selected' : '' ?>
                        >
                            <?= e($item) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="message">
                    お問い合わせ内容<span class="required">必須</span>
                </label>
                <textarea
                    id="message"
                    name="message"
                    maxlength="5000"
                    required
                ><?= e($message) ?></textarea>
            </div>

            <button type="submit" class="submit-button">
                入力内容を確認する
            </button>
        </form>
    <?php endif; ?>

    <a class="back-link" href="../index.php">
        トップページへ戻る
    </a>
</main>
    <?php require_once __DIR__ . '/../includes/footer.php'; ?>

</body>
</html>