<?php

declare(strict_types=1);

echo implode("\n", [
    (string) $siteName,
    '',
    (string) $contact['name'] . ' 様',
    '',
    'お問い合わせを受け付けました。内容を確認のうえ、担当者よりご連絡いたします。',
    '',
    '■ お問い合わせ内容',
    (string) $contact['message'],
    '',
    'このメールは自動送信です。',
]);
