<?php

declare(strict_types=1);

echo implode("\n", [
    'お問い合わせを受信しました。',
    '',
    'お名前：' . $contact['name'],
    'メールアドレス：' . $contact['email'],
    '種別：' . $contact['category'],
    '',
    'お問い合わせ内容',
    $contact['message'],
]);
