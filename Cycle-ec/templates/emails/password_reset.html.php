<?php

declare(strict_types=1);

$e = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="ja">
<head><meta charset="UTF-8"><title>パスワード再設定</title></head>
<body>
    <h1>OVERHAUL</h1>
    <p><?= $e((string) $name) ?> 様</p>
    <p>パスワード再設定のリクエストを受け付けました。以下のリンクから1時間以内に新しいパスワードを設定してください。</p>
    <p><a href="<?= $e((string) $resetUrl) ?>">パスワードを再設定する</a></p>
    <p>この操作に心当たりがない場合、このメールを無視してください。パスワードは変更されません。</p>
</body>
</html>
