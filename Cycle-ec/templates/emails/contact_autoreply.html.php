<?php

declare(strict_types=1);

$e = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="ja">
<head><meta charset="UTF-8"><title>お問い合わせ受付</title></head>
<body>
    <h1><?= $e($siteName) ?></h1>
    <p><?= $e($contact['name']) ?> 様</p>
    <p>お問い合わせを受け付けました。内容を確認のうえ、担当者よりご連絡いたします。</p>
    <h2>お問い合わせ内容</h2>
    <p><?= nl2br($e($contact['message'])) ?></p>
    <p>このメールは自動送信です。</p>
</body>
</html>
