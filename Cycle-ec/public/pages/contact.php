<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| OVERHAUL - お問い合わせ
|--------------------------------------------------------------------------
| 入力 → 確認 → 修正
| ※メール送信・DB保存は未実装
|--------------------------------------------------------------------------
*/

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['contact_csrf_token'])) {
    $_SESSION['contact_csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$confirmed = false;

$name = '';
$email = '';
$category = '';
$message = '';

// お問い合わせ種別（）
$contactCategories = [
    '商品について',
    '注文について',
    '配送について',
    '返品・キャンセルについて',
    'その他',
];

function contactEscape(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function contactPostString(string $key): string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? $value : '';
}

/*
|--------------------------------------------------------------------------
| POST処理
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim(contactPostString('name'));
    $email = trim(contactPostString('email'));
    $category = contactPostString('category');
    $message = trim(contactPostString('message'));

    $csrfToken = contactPostString('csrf_token');
    $action = contactPostString('action');

    if (!hash_equals(
        (string) $_SESSION['contact_csrf_token'],
        $csrfToken
    )) {
        $errors[] = '不正なリクエストです。ページを再読み込みしてください。';
    }

    if (!in_array($action, ['confirm', 'edit'], true)) {
        $errors[] = '不正な操作です。';
    }

    if ($name === '') {
        $errors[] = 'お名前を入力してください。';
    } elseif (mb_strlen($name) > 100) {
        $errors[] = 'お名前は100文字以内で入力してください。';
    }

    if (
        $email === '' ||
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

    if (empty($errors) && $action === 'confirm') {
        $confirmed = true;
    }
}

/*
|--------------------------------------------------------------------------
| 共通レイアウト
|--------------------------------------------------------------------------
*/

$pageTitle = 'お問い合わせ | OVERHAUL';

// header.phpがassets/css/を付けるため、ファイル名のみ指定
$pageCss = 'contact.css';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/nav.php';
?>

<main class="contact-container">

    <h1>お問い合わせ</h1>

    <p class="description">
        商品やご注文についてのご質問はこちらからお問い合わせください。
    </p>

    <?php if ($errors): ?>

        <div class="error-box" role="alert">
            <strong>入力内容をご確認ください。</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= contactEscape($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>

    <?php endif; ?>

    <?php if ($confirmed): ?>

        <section class="contact-confirm">

            <h2>入力内容の確認</h2>

            <p class="confirm-description">
                以下の内容をご確認ください。
            </p>

            <dl class="confirm-list">

                <div class="confirm-item">
                    <dt>お名前</dt>
                    <dd><?= contactEscape($name) ?></dd>
                </div>

                <div class="confirm-item">
                    <dt>メールアドレス</dt>
                    <dd><?= contactEscape($email) ?></dd>
                </div>

                <div class="confirm-item">
                    <dt>お問い合わせ種別</dt>
                    <dd><?= contactEscape($category) ?></dd>
                </div>

                <div class="confirm-item">
                    <dt>お問い合わせ内容</dt>
                    <dd class="confirm-message"><?= contactEscape($message) ?></dd>
                </div>

            </dl>

            <p class="confirm-notice">
                現在は確認機能のみです。お問い合わせ内容は送信・保存されません。
            </p>

            <form method="POST" action="">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= contactEscape($_SESSION['contact_csrf_token']) ?>">

                <input
                    type="hidden"
                    name="name"
                    value="<?= contactEscape($name) ?>">

                <input
                    type="hidden"
                    name="email"
                    value="<?= contactEscape($email) ?>">

                <input
                    type="hidden"
                    name="category"
                    value="<?= contactEscape($category) ?>">

                <input
                    type="hidden"
                    name="message"
                    value="<?= contactEscape($message) ?>">

                <button
                    type="submit"
                    name="action"
                    value="edit"
                    class="back-button">
                    入力内容を修正する
                </button>

            </form>

        </section>

    <?php else: ?>

        <form method="POST" action="">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= contactEscape($_SESSION['contact_csrf_token']) ?>">

            <div class="form-group">
                <label for="name">
                    お名前
                    <span class="required">必須</span>
                </label>

                <input
                    type="text"
                    id="name"
                    name="name"
                    maxlength="100"
                    autocomplete="name"
                    placeholder="山田 太郎"
                    value="<?= contactEscape($name) ?>"
                    required>
            </div>

            <div class="form-group">
                <label for="email">
                    メールアドレス
                    <span class="required">必須</span>
                </label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    maxlength="254"
                    autocomplete="email"
                    placeholder="example@example.com"
                    value="<?= contactEscape($email) ?>"
                    required>
            </div>

            <div class="form-group">
                <label for="category">
                    お問い合わせ種別
                    <span class="required">必須</span>
                </label>

                <select id="category" name="category" required>
                    <option value="">選択してください</option>

                    <?php foreach ($contactCategories as $contactCategory): ?>
                        <option
                            value="<?= contactEscape($contactCategory) ?>"
                            <?= $category === $contactCategory ? 'selected' : '' ?>>
                            <?= contactEscape($contactCategory) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="message">
                    お問い合わせ内容
                    <span class="required">必須</span>
                </label>

                <textarea
                    id="message"
                    name="message"
                    maxlength="5000"
                    placeholder="お問い合わせ内容をご入力ください。"
                    required><?= contactEscape($message) ?></textarea>

                <p class="form-hint">
                    最大5000文字まで入力できます。
                </p>
            </div>

            <button
                type="submit"
                name="action"
                value="confirm"
                class="submit-button">
                入力内容を確認する
            </button>

        </form>

    <?php endif; ?>

    <a class="back-link" href="/index.php">
        トップページへ戻る
    </a>

</main>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>