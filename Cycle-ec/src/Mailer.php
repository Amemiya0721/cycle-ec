<?php

declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/../vendor/autoload.php';

class Mailer
{
    /**
     * Send a template-based HTML email with a plain-text alternative.
     */
    public static function sendTemplateMail(
        string $recipient,
        string $subject,
        string $htmlTemplate,
        string $textTemplate,
        array $data = [],
        ?string $replyTo = null
    ): bool {
        if (
            !filter_var($recipient, FILTER_VALIDATE_EMAIL)
            || $subject === ''
            || preg_match('/[\r\n]/', $subject)
            || (
                $replyTo !== null
                && (
                    preg_match('/[\r\n]/', $replyTo)
                    || !filter_var($replyTo, FILTER_VALIDATE_EMAIL)
                )
            )
        ) {
            return false;
        }

        try {
            $config = require __DIR__ . '/../config/config.php';
            $mailer = new PHPMailer(true);
            self::configure($mailer, $config['smtp']);
            $mailer->addAddress($recipient);
            if ($replyTo !== null) {
                $mailer->addReplyTo($replyTo);
            }
            $mailer->Subject = $subject;
            $mailer->isHTML(true);
            $mailer->Body = self::renderTemplate($htmlTemplate, $data);
            $mailer->AltBody = self::renderTemplate($textTemplate, $data);
            $mailer->send();

            return true;
        } catch (\Throwable $exception) {
            error_log(
                'Template email delivery failed: '
                . $exception->getMessage()
            );

            return false;
        }
    }

    /**
     * Send a plain-text message to one or more recipients.
     */
    public static function sendTextMail(
        array $recipients,
        string $subject,
        string $body,
        bool $blindCopy = false
    ): bool {
        if ($subject === '' || preg_match('/[\r\n]/', $subject)) {
            return false;
        }

        $validRecipients = [];

        foreach ($recipients as $recipient) {
            if (
                !is_string($recipient)
                || !filter_var($recipient, FILTER_VALIDATE_EMAIL)
            ) {
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
            error_log(
                'Configured text email delivery failed: '
                . $exception->getMessage()
            );

            return false;
        }
    }

    /**
     * 管理者ログイン認証コードメールを送信する。
     */
    public static function sendAdminLoginCode(
        string $recipient,
        string $code
    ): bool {
        return self::sendTemplateMail(
            $recipient,
            '【OVERHAUL】管理者ログイン認証コード',
            'admin_login.html.php',
            'admin_login.txt.php',
            ['code' => $code]
        );
    }

    /**
     * 発送通知メールを送信する（HTML + テキスト）。
     *
     * @param array $order findForAdmin() の結果（items を含む）
     */
    public static function sendShipmentNotice(
        string $recipient,
        array $order,
        string $receiptUrl,
        ?string $trackingUrl
    ): bool {
        $orderNumber = (string) ($order['order_number'] ?? '');
        return self::sendTemplateMail(
            $recipient,
            '【OVERHAUL】発送のお知らせ（ご注文 #' . $orderNumber . '）',
            'shipment.html.php',
            'shipment.txt.php',
            [
                'order' => $order,
                'receiptUrl' => $receiptUrl,
                'trackingUrl' => $trackingUrl,
            ]
        );
    }

    /**
     * SMTP送信テストメールを送信する。
     */
    public static function sendTestMail(string $recipient): bool
    {
        return self::sendTemplateMail(
            $recipient,
            '【OVERHAUL】SMTP送信テスト',
            'test_mail.html.php',
            'test_mail.txt.php'
        );
    }

    /**
     * メールテンプレートを読み込む。
     *
     * テンプレート配置先:
     * Cycle-ec/templates/emails/
     *
     * @param string $template テンプレートファイル名
     * @param array $data テンプレートに渡すデータ
     */
    private static function renderTemplate(
        string $template,
        array $data = []
    ): string {
        // ファイル名のみ受け付け、任意のパス指定を防ぐ。
        if (
            $template === ''
            || basename($template) !== $template
            || !str_ends_with($template, '.php')
        ) {
            throw new \InvalidArgumentException(
                'Invalid email template name.'
            );
        }

        $templatePath = __DIR__
            . '/../templates/emails/'
            . $template;

        if (!is_file($templatePath)) {
            throw new \RuntimeException(
                'Email template not found: ' . $template
            );
        }

        extract($data, EXTR_SKIP);

        ob_start();

        try {
            require $templatePath;

            $content = ob_get_clean();

            if ($content === false) {
                throw new \RuntimeException(
                    'Failed to render email template: ' . $template
                );
            }

            return $content;
        } catch (\Throwable $exception) {
            ob_end_clean();
            throw $exception;
        }
    }

    /**
     * PHPMailerのSMTP設定を行う。
     */
    private static function configure(
        PHPMailer $mailer,
        array $smtp
    ): void {
        $requiredSettings = [
            'host',
            'username',
            'password',
            'encryption',
            'from_address',
            'from_name',
        ];
        foreach ($requiredSettings as $key) {
            if (!isset($smtp[$key]) || !is_string($smtp[$key]) || trim($smtp[$key]) === '') {
                throw new \InvalidArgumentException('Missing SMTP setting: ' . $key);
            }
        }
        if (
            !isset($smtp['port'])
            || !is_int($smtp['port'])
            || $smtp['port'] < 1
            || $smtp['port'] > 65535
        ) {
            throw new \InvalidArgumentException('Invalid SMTP port.');
        }
        if (!filter_var($smtp['from_address'], FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid SMTP from address.');
        }
        if (preg_match('/[\r\n]/', $smtp['from_name']) === 1) {
            throw new \InvalidArgumentException('Invalid SMTP from name.');
        }
        if (!in_array($smtp['encryption'], ['tls', 'ssl'], true)) {
            throw new \InvalidArgumentException('SMTP encryption must be tls or ssl.');
        }

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
        $mailer->setFrom(
            $smtp['from_address'],
            $smtp['from_name']
        );
    }
}
