<?php

declare(strict_types=1);

$escapedCode = htmlspecialchars((string) $code, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>管理者ログイン認証コード</title>
</head>
<body>
    <h1>OVERHAUL</h1>

    <p>管理者ログインが要求されました。</p>

    <p>
        認証コード：
        <strong style="font-size:24px;letter-spacing:4px">
            <?= $escapedCode ?>
        </strong>
    </p>

    <p>このコードは10分間有効です。</p>

    <p>心当たりがない場合は、このメールを無視してください。</p>
</body>
</html>
