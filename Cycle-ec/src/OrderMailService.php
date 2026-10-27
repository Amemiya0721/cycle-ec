<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Order.php';
require_once __DIR__ . '/Mailer.php';

/**
 * 注文関連メールの送信と、送信結果の記録（order_mail_logs）。
 *
 * 送信は既存の Mailer（PHPMailer / config.php の smtp 設定）を使う。
 * 受取確認リンクのURLは config.php の site.url（.env の SITE_URL）と app.base_url から作る。
 */
final class OrderMailService
{
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
     * 発送通知メールを送る。成功時は orders.shipment_mail_sent_at を更新する。
     * 失敗しても例外は投げず false を返し、原因は order_mail_logs に残す。
     */
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

        // 既存の管理者ログインコードのメールと同じ Mailer（PHPMailer）経由で送る
        if (!Mailer::sendShipmentNotice($to, $order, $receiptUrl, $trackingUrl)) {
            return self::fail($orderId, $to, 'SMTP送信に失敗しました。サーバーのエラーログ（Shipment notice SMTP delivery failed）を確認してください。');
        }

        self::log($orderId, $to, 'sent', null);
        Order::markShipmentMailSent($orderId);

        return true;
    }

    /**
     * 受取確認リンクの土台になるURL（末尾スラッシュなし）。未設定・http(s)以外は空文字。
     *
     * config.php の構成に合わせる:
     *   site.url      = サイトのオリジン（例 https://example.com）… .env の SITE_URL
     *   app.base_url  = アプリのパス接頭辞（例 /overhaul/public/、ルート直下なら /）… .env の BASE_URL
     * app.base_url が http(s):// で始まるフルURLならそれをそのまま使う。
     * どちらも無ければ環境変数 APP_BASE_URL（フルURL）を使う。
     */
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

    private static function log(int $orderId, string $to, string $status, ?string $error): void
    {
        try {
            $stmt = Database::getConnection()->prepare(
                'INSERT INTO order_mail_logs (order_id, mail_type, recipient, status, error_message, created_at)
                 VALUES (:order_id, :mail_type, :recipient, :status, :error, NOW())'
            );
            $stmt->execute([
                ':order_id'  => $orderId,
                ':mail_type' => 'shipment',
                ':recipient' => mb_substr($to, 0, 255),
                ':status'    => $status,
                ':error'     => $error !== null ? mb_substr($error, 0, 255) : null,
            ]);
        } catch (Throwable $exception) {
            // ログ保存の失敗で発送処理自体を止めない
            error_log('Order mail log insert failed.');
        }
    }
}