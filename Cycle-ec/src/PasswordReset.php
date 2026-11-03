<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';

final class PasswordReset
{
    public const TOKEN_TTL_SECONDS = 3600;

    public static function issueForEmail(string $email): ?array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT user_id, name, email FROM users WHERE email = :email LIMIT 1'
        );
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user === false) {
            return null;
        }

        $token = bin2hex(random_bytes(32));
        $save = Database::getConnection()->prepare(
            'INSERT INTO password_reset_tokens
                (user_id, token_hash, expires_at, created_at)
             VALUES
                (:user_id, :token_hash, DATE_ADD(NOW(), INTERVAL 1 HOUR), NOW())
             ON DUPLICATE KEY UPDATE
                token_hash = VALUES(token_hash),
                expires_at = VALUES(expires_at),
                created_at = VALUES(created_at)'
        );
        $save->execute([
            ':user_id' => (int) $user['user_id'],
            ':token_hash' => hash('sha256', $token),
        ]);

        return [
            'user_id' => (int) $user['user_id'],
            'name' => (string) $user['name'],
            'email' => (string) $user['email'],
            'token' => $token,
        ];
    }

    public static function invalidate(int $userId): void
    {
        $stmt = Database::getConnection()->prepare(
            'DELETE FROM password_reset_tokens WHERE user_id = :user_id'
        );
        $stmt->execute([':user_id' => $userId]);
    }

    public static function reset(string $token, string $newPassword): bool
    {
        if (preg_match('/^[a-f0-9]{64}$/D', $token) !== 1) {
            return false;
        }

        $pdo = Database::getConnection();
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                'SELECT user_id
                 FROM password_reset_tokens
                 WHERE token_hash = :token_hash
                   AND expires_at > NOW()
                 FOR UPDATE'
            );
            $stmt->execute([':token_hash' => hash('sha256', $token)]);
            $userId = $stmt->fetchColumn();
            if ($userId === false) {
                $pdo->rollBack();
                return false;
            }

            $update = $pdo->prepare(
                'UPDATE users SET password = :password, updated_at = NOW()
                 WHERE user_id = :user_id'
            );
            $update->execute([
                ':password' => password_hash($newPassword, PASSWORD_DEFAULT),
                ':user_id' => (int) $userId,
            ]);
            if ($update->rowCount() !== 1) {
                throw new RuntimeException('パスワードを更新できませんでした。');
            }

            $delete = $pdo->prepare(
                'DELETE FROM password_reset_tokens WHERE user_id = :user_id'
            );
            $delete->execute([':user_id' => (int) $userId]);
            $pdo->commit();
            return true;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }
}
