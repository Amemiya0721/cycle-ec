<?php

declare(strict_types=1);

/**
 * Auth
 * -----------------------------------------------------------
 * 一般ユーザー側ページ（checkout / mypage-address / order-complete）用の
 * ログイン判定とCSRFトークンの最小ヘルパー。
 *
 * ※ 管理画面の認可は既存の admin/includes/auth.php が担当する。
 *
 * ★ 要確認: ログイン中ユーザーIDを入れているセッションキー。
 *   ここでは $_SESSION['user_id'] と仮定している。
 *   実際のキー名は pages/login.php で確認して userId() を合わせること。
 */
final class Auth
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function userId(): ?int
    {
        self::start();
        $id = $_SESSION['user_id'] ?? null;
        return is_numeric($id) && (int) $id > 0 ? (int) $id : null;
    }

    /** 未ログインならログイン画面へ。ログイン済みならユーザーIDを返す。 */
    public static function requireLogin(string $loginUrl): int
    {
        $userId = self::userId();
        if ($userId === null) {
            header('Location: ' . $loginUrl);
            exit;
        }
        return $userId;
    }

    public static function csrfToken(): string
    {
        self::start();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return (string) $_SESSION['csrf_token'];
    }

    public static function verifyCsrf(?string $token): bool
    {
        self::start();
        $expected = $_SESSION['csrf_token'] ?? '';
        return is_string($token) && $expected !== '' && hash_equals($expected, $token);
    }
}
