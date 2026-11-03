<?php

declare(strict_types=1);

require_once __DIR__ . '/Mailer.php';

final class ContactMailService
{
    public static function sendAdminNotification(array $contact): bool
    {
        $config = require __DIR__ . '/../config/config.php';
        $recipient = trim((string) ($config['site']['contact_email'] ?? ''));
        if ($recipient === '' || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            error_log('Contact notification failed: CONTACT_EMAIL is missing or invalid.');
            return false;
        }

        $sent = Mailer::sendTemplateMail(
            $recipient,
            '【OVERHAUL】お問い合わせを受信しました（' . $contact['category'] . '）',
            'contact_admin.html.php',
            'contact_admin.txt.php',
            ['contact' => $contact],
            $contact['email']
        );
        if (!$sent) {
            error_log('Contact notification email failed.');
        }

        return $sent;
    }

    public static function sendAutoReply(array $contact): bool
    {
        $config = require __DIR__ . '/../config/config.php';
        $siteName = trim((string) ($config['site']['name'] ?? 'OVERHAUL'));
        $sent = Mailer::sendTemplateMail(
            $contact['email'],
            '【' . $siteName . '】お問い合わせを受け付けました',
            'contact_autoreply.html.php',
            'contact_autoreply.txt.php',
            ['contact' => $contact, 'siteName' => $siteName]
        );
        if (!$sent) {
            error_log('Contact auto-reply email failed.');
        }

        return $sent;
    }
}
