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
            error_log('Configured text email delivery failed.');
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
}