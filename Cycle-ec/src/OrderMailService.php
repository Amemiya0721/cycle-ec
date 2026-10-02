<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Mailer.php';

final class OrderMailService
{
    private const TEMPLATE_DIR = __DIR__ . '/../templates/emails/';

    public static function notify(int $orderId): void
    {
        if ($orderId <= 0) {
            return;
        }

        try {
            $config = require __DIR__ . '/../config/config.php';
            $site = $config['site'];
            $siteName = trim(str_replace(["\r", "\n"], ' ', (string) $site['name']));
            $siteUrl = self::absoluteSiteUrl((string) ($site['url'] ?? ''));
            if ($siteUrl === null) {
                $siteUrl = self::absoluteSiteUrl((string) ($config['app']['base_url'] ?? ''));
            }
            if ($siteName === '') {
                error_log('Order email notifications skipped: site name is unavailable.');
                return;
            }

            $contactEmail = (string) $site['contact_email'];
            $contactLine = filter_var($contactEmail, FILTER_VALIDATE_EMAIL)
                ? 'お問い合わせ先: ' . $contactEmail
                : '';
            $adminOrdersUrl = $siteUrl !== null
                ? $siteUrl . 'admin/order/index.php'
                : '';
            $common = [
                '{{site_name}}' => $siteName,
                '{{site_url}}' => $siteUrl ?? '',
                '{{admin_orders_url}}' => $adminOrdersUrl,
                '{{order_number}}' => (string) $orderId,
                '{{contact_line}}' => $contactLine,
            ];

            try {
                self::notifyBuyer($orderId, $common);
            } catch (\Throwable $exception) {
                error_log('Order confirmation notification failed.');
            }

            if ($adminOrdersUrl === '') {
                error_log('Sale notification is being sent without an admin link: SITE_URL is not configured.');
            }

            try {
                self::notifyAdministrators($common);
            } catch (\Throwable $exception) {
                error_log('Sale notification failed.');
            }
        } catch (\Throwable $exception) {
            error_log('Order email notification processing failed.');
        }
    }

    private static function notifyBuyer(int $orderId, array $variables): void
    {
        try {
            $stmt = Database::getConnection()->prepare(
                'SELECT u.email
                 FROM orders o
                 INNER JOIN users u ON u.user_id = o.user_id
                 WHERE o.order_id = :order_id
                 LIMIT 1'
            );
            $stmt->execute([':order_id' => $orderId]);
            $email = $stmt->fetchColumn();
        } catch (\Throwable $exception) {
            error_log('Order confirmation recipient lookup failed.');
            return;
        }

        if (!is_string($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            error_log('Order confirmation email skipped: recipient is unavailable.');
            return;
        }

        self::sendTemplated(
            [$email],
            'order_completed.subject.txt',
            'order_completed.body.txt',
            $variables
        );
    }

    private static function notifyAdministrators(array $variables): void
    {
        try {
            $stmt = Database::getConnection()->query(
                'SELECT email FROM users WHERE is_admin = 1 ORDER BY user_id'
            );
            $emails = array_values(array_unique(array_filter(
                $stmt->fetchAll(PDO::FETCH_COLUMN),
                static fn ($email): bool => is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL)
            )));
        } catch (\Throwable $exception) {
            error_log('Sale notification administrator lookup failed.');
            return;
        }

        if ($emails === []) {
            error_log('Sale notification skipped: no valid administrator recipient.');
            return;
        }

        self::sendTemplated(
            $emails,
            'admin_sale_notification.subject.txt',
            'admin_sale_notification.body.txt',
            $variables,
            true
        );
    }

    private static function sendTemplated(
        array $recipients,
        string $subjectFile,
        string $bodyFile,
        array $variables,
        bool $blindCopy = false
    ): void {
        $subject = self::render($subjectFile, $variables);
        $body = self::render($bodyFile, $variables);
        if ($subject === null || $body === null) {
            error_log('Order email template is unavailable.');
            return;
        }

        if (!Mailer::sendTextMail($recipients, $subject, $body, $blindCopy)) {
            error_log('Order notification email was not delivered.');
        }
    }

    private static function render(string $filename, array $variables): ?string
    {
        $path = self::TEMPLATE_DIR . $filename;
        if (!is_file($path)) {
            return null;
        }
        $template = @file_get_contents($path);
        return $template === false ? null : trim(strtr($template, $variables));
    }

    private static function absoluteSiteUrl(string $url): ?string
    {
        $url = trim($url);
        $parts = parse_url($url);
        if (!filter_var($url, FILTER_VALIDATE_URL)
            || !is_array($parts)
            || !isset($parts['scheme'], $parts['host'])
            || !in_array(strtolower($parts['scheme']), ['http', 'https'], true)
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])) {
            return null;
        }

        return rtrim($url, '/') . '/';
    }
}