<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Order.php';
require_once __DIR__ . '/Mailer.php';
require_once __DIR__ . '/BankTransferSettings.php';

/**
 * 注文関連メールの送信と、送信結果の記録（order_mail_logs）。
 */
final class OrderMailService
{
    /** 振込案内に必要な口座情報を取得する。 */
    public static function bankTransferSettings(): array
    {
        return BankTransferSettings::get();
    }

    public static function isBankTransferConfigured(): bool
    {
        return BankTransferSettings::isConfigured(self::bankTransferSettings());
    }

    /** 配送会社ごとの追跡URL。「その他」はURLなし。 */
    public static function trackingUrl(string $carrier, string $trackingNumber): ?string
    {
        $n = rawurlencode($trackingNumber);

        switch ($carrier) {
            case 'ヤマト運輸':
                return 'https://toi.kuronekoyamato.co.jp/cgi-bin/tneko?number00=1&number01=' . $n;
            case '佐川急便':
                return 'https://k2k.sagawa-exp.co.jp/p/web/okurijosearch.do?okurijoNo=' . $n;
            case '日本郵便':
                return 'https://trackings.post.japanpost.jp/services/srv/search/direct?searchKind=S004&locale=ja&reqCodeNo1=' . $n;
            default:
                return null;
        }
    }

    /**
     * 注文確認メールを送信する。checkout.php の OrderMailService::notify($orderId) から呼ばれる。
     * メール送信失敗時も注文自体は取り消さない。
     */
    public static function notify(int $orderId): bool
    {
        try {
            $order = Order::findForAdmin($orderId);
            if ($order === null) {
                error_log('Order confirmation mail failed: order not found. order_id=' . $orderId);
                return false;
            }

            $bankTransfer = self::bankTransferSettings();
            $to = trim((string) ($order['buyer_email'] ?? ''));
            $orderNumber = (string) ($order['order_number'] ?? '');
            $customerSent = false;
            if (!BankTransferSettings::isConfigured($bankTransfer)) {
                self::log($orderId, $to, 'failed', '銀行振込先口座が未設定です。', 'confirmation');
            } elseif (
                preg_match('/[\r\n]/', $to) === 1
                || filter_var($to, FILTER_VALIDATE_EMAIL) === false
            ) {
                self::log($orderId, $to, 'failed', '宛先メールアドレスが不正です。', 'confirmation');
            } else {
                $customerSent = Mailer::sendTemplateMail(
                    $to,
                    '【OVERHAUL】ご注文ありがとうございます（注文番号：' . $orderNumber . '）',
                    'order_confirmation.html.php',
                    'order_confirmation.txt.php',
                    ['order' => $order, 'bankTransfer' => $bankTransfer]
                );
                self::log(
                    $orderId,
                    $to,
                    $customerSent ? 'sent' : 'failed',
                    $customerSent ? null : '注文確認メールの送信に失敗しました。',
                    'confirmation'
                );
            }

            $config = require __DIR__ . '/../config/config.php';
            $adminRecipient = trim((string) ($config['site']['contact_email'] ?? ''));
            $adminSent = false;
            if (
                $adminRecipient === ''
                || preg_match('/[\r\n]/', $adminRecipient) === 1
                || filter_var($adminRecipient, FILTER_VALIDATE_EMAIL) === false
            ) {
                error_log('Admin order notification failed: CONTACT_EMAIL is missing or invalid. order_id=' . $orderId);
                self::log($orderId, $adminRecipient, 'failed', '管理者通知先（CONTACT_EMAIL）が未設定か不正です。', 'admin_notification');
            } else {
                $adminSent = Mailer::sendTemplateMail(
                    $adminRecipient,
                    '【OVERHAUL】新しい注文を受け付けました（注文番号：' . $orderNumber . '）',
                    'order_admin_notification.html.php',
                    'order_admin_notification.txt.php',
                    ['order' => $order]
                );
                self::log(
                    $orderId,
                    $adminRecipient,
                    $adminSent ? 'sent' : 'failed',
                    $adminSent ? null : '管理者への注文通知メールの送信に失敗しました。',
                    'admin_notification'
                );
            }

            return $customerSent && $adminSent;
        } catch (Throwable $exception) {
            error_log('Order confirmation mail unexpected failure. order_id=' . $orderId . ' error=' . $exception->getMessage());
            return false;
        }
    }

    /** 発送通知メールを送る。成功時は orders.shipment_mail_sent_at を更新する。 */
    public static function sendShipmentNotice(int $orderId, string $receiptToken): bool
    {
        $order = Order::findForAdmin($orderId);
        if ($order === null || $order['status'] !== Order::STATUS_SHIPPED || empty($order['tracking_number'])) {
            return false;
        }

        $to = trim((string) $order['buyer_email']);
        $baseUrl = self::baseUrl();

        if (preg_match('/[\r\n]/', $to) === 1 || filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            return self::fail($orderId, $to, '宛先メールアドレスが不正です。');
        }
        if ($baseUrl === '') {
            return self::fail($orderId, $to, 'サイトURL（.env の SITE_URL）が未設定か、不正です。');
        }

        $receiptUrl = $baseUrl . '/pages/receive.php?' . http_build_query([
            'order' => $orderId,
            'token' => $receiptToken,
        ]);
        $trackingUrl = self::trackingUrl((string) $order['shipping_carrier'], (string) $order['tracking_number']);

        if (!Mailer::sendShipmentNotice($to, $order, $receiptUrl, $trackingUrl)) {
            return self::fail($orderId, $to, 'SMTP送信に失敗しました。サーバーのエラーログ（Shipment notice SMTP delivery failed）を確認してください。');
        }

        self::log($orderId, $to, 'sent', null);
        Order::markShipmentMailSent($orderId);

        return true;
    }

    /** 受取確認リンクの土台になるURL（末尾スラッシュなし）。 */
    private static function baseUrl(): string
    {
        $config = require __DIR__ . '/../config/config.php';

        $appBase = trim((string) ($config['app']['base_url'] ?? ''));
        $site = rtrim(trim((string) ($config['site']['url'] ?? '')), '/');
        $fallback = rtrim(trim((string) (getenv('APP_BASE_URL') ?: ($_ENV['APP_BASE_URL'] ?? ''))), '/');

        if (preg_match('#^https?://[^\s/]+#i', $appBase) === 1) {
            $url = rtrim($appBase, '/');
        } elseif (preg_match('#^https?://[^\s/]+#i', $site) === 1) {
            $path = trim($appBase, '/');
            $url = $site . ($path === '' ? '' : '/' . $path);
        } else {
            $url = $fallback;
        }

        return preg_match('#^https?://[^\s/]+#i', $url) === 1 ? $url : '';
    }

    private static function fail(int $orderId, string $to, string $message): bool
    {
        error_log('Order mail failed. order_id=' . $orderId . ' reason=' . $message);
        self::log($orderId, $to, 'failed', $message);

        return false;
    }

    /**
     * @param string $mailType shipment、confirmation または admin_notification
     */
    private static function log(int $orderId, string $to, string $status, ?string $error, string $mailType = 'shipment'): void
    {
        try {
            $stmt = Database::getConnection()->prepare(
                'INSERT INTO order_mail_logs (order_id, mail_type, recipient, status, error_message, created_at)
                 VALUES (:order_id, :mail_type, :recipient, :status, :error, NOW())'
            );
            $stmt->execute([
                ':order_id'  => $orderId,
                ':mail_type' => $mailType,
                ':recipient' => mb_substr($to, 0, 255),
                ':status'    => $status,
                ':error'     => $error !== null ? mb_substr($error, 0, 255) : null,
            ]);
        } catch (Throwable $exception) {
            error_log('Order mail log insert failed: ' . $exception->getMessage());
        }
    }
}
