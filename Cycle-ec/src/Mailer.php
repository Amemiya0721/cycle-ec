<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/../vendor/autoload.php';

class Mailer
{
    /** Send a plain-text message to one or more recipients. */
    public static function sendTextMail(array $recipients, string $subject, string $body, bool $blindCopy = false): bool
    {
        if ($subject === '' || preg_match('/[\r\n]/', $subject)) {
            return false;
        }

        $validRecipients = [];
        foreach ($recipients as $recipient) {
            if (!is_string($recipient) || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                return false;
            }
            $validRecipients[] = $recipient;
        }
        $validRecipients = array_values(array_unique($validRecipients));
        if ($validRecipients === []) {
            return false;
        }

        try {
            $config = require __DIR__ . '/../config/config.php';
            $mailer = new PHPMailer(true);
            self::configure($mailer, $config['smtp']);
            foreach ($validRecipients as $recipient) {
                if ($blindCopy) {
                    $mailer->addBCC($recipient);
                } else {
                    $mailer->addAddress($recipient);
                }
            }
            $mailer->Subject = $subject;
            $mailer->isHTML(false);
            $mailer->Body = $body;
            $mailer->send();
            return true;
        } catch (\Throwable $exception) {
            error_log('Configured text email delivery failed: ' . $exception->getMessage());
            return false;
        }
    }

    public static function sendAdminLoginCode(string $recipient, string $code): bool
    {
        $config = require __DIR__ . '/../config/config.php';
        $smtp = $config['smtp'];
        $mailer = new PHPMailer(true);

        try {
            $mailer->isSMTP();
            $mailer->Host = $smtp['host'];
            $mailer->Port = $smtp['port'];
            $mailer->SMTPAuth = true;
            $mailer->Username = $smtp['username'];
            $mailer->Password = $smtp['password'];
            $mailer->SMTPSecure = $smtp['encryption'] === 'ssl'
                ? PHPMailer::ENCRYPTION_SMTPS
                : PHPMailer::ENCRYPTION_STARTTLS;
            $mailer->CharSet = 'UTF-8';
            $mailer->setFrom($smtp['from_address'], $smtp['from_name']);
            $mailer->addAddress($recipient);
            $mailer->Subject = '【OVERHAUL】管理者ログイン認証コード';
            $mailer->isHTML(true);
            $mailer->Body = self::htmlBody($code);
            $mailer->AltBody = self::textBody($code);
            $mailer->send();
            return true;
        } catch (Exception $exception) {
            error_log('Admin login SMTP delivery failed: ' . $exception->getMessage());
            return false;
        }
    }

    /**
     * 発送通知メール（HTML + テキスト）。管理者ログインコードのメールと同じ作り。
     *
     * @param array $order findForAdmin() の結果（items を含む）
     */
    public static function sendShipmentNotice(string $recipient, array $order, string $receiptUrl, ?string $trackingUrl): bool
    {
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $config = require __DIR__ . '/../config/config.php';
        $mailer = new PHPMailer(true);

        try {
            self::configure($mailer, $config['smtp']);
            $mailer->addAddress($recipient);
            $mailer->Subject = '【OVERHAUL】発送のお知らせ（ご注文 #' . (string) $order['order_number'] . '）';
            $mailer->isHTML(true);
            $mailer->Body = self::shipmentHtmlBody($order, $receiptUrl, $trackingUrl);
            $mailer->AltBody = self::shipmentTextBody($order, $receiptUrl, $trackingUrl);
            $mailer->send();
            return true;
        } catch (Exception $exception) {
            error_log('Shipment notice SMTP delivery failed: ' . $exception->getMessage());
            return false;
        }
    }

    public static function sendTestMail(string $recipient): bool
    {
        $config = require __DIR__ . '/../config/config.php';
        $smtp = $config['smtp'];
        $mailer = new PHPMailer(true);

        try {
            self::configure($mailer, $smtp);
            $mailer->addAddress($recipient);
            $mailer->Subject = '【OVERHAUL】Gmail SMTP送信テスト';
            $mailer->isHTML(true);
            $mailer->Body = '<!doctype html><html lang="ja"><body><h1>OVERHAUL</h1><p>Gmail SMTPの送信テストに成功しました。</p></body></html>';
            $mailer->AltBody = "OVERHAUL\n\nGmail SMTPの送信テストに成功しました。";
            $mailer->send();
            return true;
        } catch (Exception $exception) {
            error_log('SMTP test mail failed: ' . $exception->getMessage());
            return false;
        }
    }

    private static function configure(PHPMailer $mailer, array $smtp): void
    {
        $mailer->isSMTP();
        $mailer->Host = $smtp['host'];
        $mailer->Port = $smtp['port'];
        $mailer->SMTPAuth = true;
        $mailer->Username = $smtp['username'];
        $mailer->Password = $smtp['password'];
        $mailer->SMTPSecure = $smtp['encryption'] === 'ssl'
            ? PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer::ENCRYPTION_STARTTLS;
        $mailer->CharSet = 'UTF-8';
        $mailer->setFrom($smtp['from_address'], $smtp['from_name']);
    }

    private static function textBody(string $code): string
    {
        return "OVERHAUL\n\n"
            . "管理者ログインが要求されました。\n\n"
            . "認証コード：{$code}\n\n"
            . "このコードは10分間有効です。\n"
            . "心当たりがない場合は、このメールを無視してください。";
    }

    private static function htmlBody(string $code): string
    {
        $escapedCode = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
        return '<!doctype html><html lang="ja"><body>'
            . '<h1>OVERHAUL</h1>'
            . '<p>管理者ログインが要求されました。</p>'
            . '<p>認証コード：<strong style="font-size:24px;letter-spacing:4px">'
            . $escapedCode
            . '</strong></p>'
            . '<p>このコードは10分間有効です。</p>'
            . '<p>心当たりがない場合は、このメールを無視してください。</p>'
            . '</body></html>';
    }

    private static function shipmentAddress(array $order): string
    {
        return trim(
            (string) $order['shipping_prefecture'] . (string) $order['shipping_city']
            . (string) $order['shipping_address_line'] . ' ' . (string) ($order['shipping_building'] ?? '')
        );
    }

    private static function shipmentTextBody(array $order, string $receiptUrl, ?string $trackingUrl): string
    {
        $lines = [
            'OVERHAUL',
            '',
            (string) $order['buyer_name'] . ' 様',
            '',
            'ご注文いただいた商品を発送いたしました。',
            '',
            '■ 注文番号：#' . (string) $order['order_number'],
            '■ 配送会社：' . (string) $order['shipping_carrier'],
            '■ 追跡番号：' . (string) $order['tracking_number'],
        ];
        if ($trackingUrl !== null) {
            $lines[] = '■ 配送状況の確認：' . $trackingUrl;
        }
        $lines[] = '';
        $lines[] = '■ お届け先';
        $lines[] = (string) $order['shipping_recipient_name'] . ' 様';
        $lines[] = '〒' . (string) $order['shipping_postal_code'];
        $lines[] = self::shipmentAddress($order);
        $lines[] = '';
        $lines[] = '■ ご注文内容';
        foreach ($order['items'] as $item) {
            $lines[] = '・' . (string) ($item['name'] ?? '（商品情報なし）') . '　' . number_format((float) $item['price']) . '円';
        }
        $lines[] = '合計：' . number_format((float) $order['total_price']) . '円';
        $lines[] = '';
        $lines[] = '商品がお手元に届きましたら、下記のリンクから受取確認をお願いいたします。';
        $lines[] = '（ログインが必要です。ご注文されたご本人のみ操作できます）';
        $lines[] = $receiptUrl;
        $lines[] = '';
        $lines[] = 'このメールは送信専用です。';

        return implode("\n", $lines);
    }

    private static function shipmentHtmlBody(array $order, string $receiptUrl, ?string $trackingUrl): string
    {
        $e = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

        $html = '<!doctype html><html lang="ja"><body>'
            . '<h1>OVERHAUL</h1>'
            . '<p>' . $e((string) $order['buyer_name']) . ' 様</p>'
            . '<p>ご注文いただいた商品を発送いたしました。</p>'
            . '<table cellpadding="4" style="border-collapse:collapse">'
            . '<tr><th align="left">注文番号</th><td>#' . (string) $order['order_number'] . '</td></tr>'
            . '<tr><th align="left">配送会社</th><td>' . $e((string) $order['shipping_carrier']) . '</td></tr>'
            . '<tr><th align="left">追跡番号</th><td>' . $e((string) $order['tracking_number']) . '</td></tr>';
        if ($trackingUrl !== null) {
            $html .= '<tr><th align="left">配送状況</th><td><a href="' . $e($trackingUrl) . '">配送状況を確認する</a></td></tr>';
        }
        $html .= '</table>'
            . '<h2 style="font-size:16px">お届け先</h2>'
            . '<p>' . $e((string) $order['shipping_recipient_name']) . ' 様<br>'
            . '〒' . $e((string) $order['shipping_postal_code']) . '<br>'
            . $e(self::shipmentAddress($order)) . '</p>'
            . '<h2 style="font-size:16px">ご注文内容</h2><ul>';
        foreach ($order['items'] as $item) {
            $html .= '<li>' . $e((string) ($item['name'] ?? '（商品情報なし）')) . '　'
                . number_format((float) $item['price']) . '円</li>';
        }
        $html .= '</ul><p><strong>合計：' . number_format((float) $order['total_price']) . '円</strong></p>'
            . '<p>商品がお手元に届きましたら、下記のボタンから受取確認をお願いいたします。<br>'
            . '<small>（ログインが必要です。ご注文されたご本人のみ操作できます）</small></p>'
            . '<p><a href="' . $e($receiptUrl) . '" style="display:inline-block;padding:10px 20px;background:#198754;color:#fff;text-decoration:none;border-radius:4px">受取確認をする</a></p>'
            . '<p><small>このメールは送信専用です。</small></p>'
            . '</body></html>';

        return $html;
    }
}