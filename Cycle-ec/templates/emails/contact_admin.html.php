<?php

declare(strict_types=1);

$e = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="ja">
<head><meta charset="UTF-8"><title>お問い合わせ</title></head>
<body>
    <h1>お問い合わせを受信しました</h1>
    <dl>
        <dt>お名前</dt><dd><?= $e($contact['name']) ?></dd>
        <dt>メールアドレス</dt><dd><?= $e($contact['email']) ?></dd>
        <dt>種別</dt><dd><?= $e($contact['category']) ?></dd>
    </dl>
    <h2>お問い合わせ内容</h2>
    <p><?= nl2br($e($contact['message'])) ?></p>
</body>
</html>
