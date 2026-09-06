<?php
declare(strict_types=1);

require_once __DIR__ . '/Database.php';

class AdminLoginVerification
{
    public const MAX_ATTEMPTS = 5;
    public const LIFETIME_MINUTES = 10;
    public const RESEND_COOLDOWN_SECONDS = 60;

    public static function issue(int $userId, string $code): int
    {
        $pdo = Database::getConnection();
        $pdo->beginTransaction();
        try {
            self::invalidateForUser($userId, $pdo);
            $stmt = $pdo->prepare(
                'INSERT INTO admin_login_verifications
                    (user_id, code_hash, expires_at)
                 VALUES (:user_id, :code_hash, DATE_ADD(NOW(), INTERVAL 10 MINUTE))'
            );
            $stmt->execute([
                ':user_id' => $userId,
                ':code_hash' => password_hash($code, PASSWORD_DEFAULT),
            ]);
            $verificationId = (int) $pdo->lastInsertId();
            $pdo->commit();
            return $verificationId;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public static function findActive(int $verificationId, int $userId): ?array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT verification_id, user_id, code_hash, expires_at, attempts
             FROM admin_login_verifications
             WHERE verification_id = :verification_id
               AND user_id = :user_id
               AND used_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([
            ':verification_id' => $verificationId,
            ':user_id' => $userId,
        ]);
        $verification = $stmt->fetch();
        return $verification ?: null;
    }

    public static function resendWaitSeconds(int $userId): int
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT created_at FROM admin_login_verifications
             WHERE user_id = :user_id
             ORDER BY verification_id DESC LIMIT 1'
        );
        $stmt->execute([':user_id' => $userId]);
        $createdAt = $stmt->fetchColumn();
        if ($createdAt === false) {
            return 0;
        }
        $elapsed = time() - (new DateTimeImmutable((string) $createdAt))->getTimestamp();
        return max(0, self::RESEND_COOLDOWN_SECONDS - $elapsed);
    }

    public static function incrementAttempts(int $verificationId): int
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'UPDATE admin_login_verifications
             SET attempts = attempts + 1
             WHERE verification_id = :verification_id AND used_at IS NULL'
        );
        $stmt->execute([':verification_id' => $verificationId]);
        $current = $pdo->prepare('SELECT attempts FROM admin_login_verifications WHERE verification_id = :verification_id');
        $current->execute([':verification_id' => $verificationId]);
        return (int) ($current->fetchColumn() ?: self::MAX_ATTEMPTS);
    }

    public static function consume(int $verificationId, int $userId): bool
    {
        $stmt = Database::getConnection()->prepare(
            'UPDATE admin_login_verifications
             SET used_at = NOW()
             WHERE verification_id = :verification_id
               AND user_id = :user_id
               AND used_at IS NULL
               AND expires_at >= NOW()
               AND attempts < :max_attempts'
        );
        $stmt->execute([
            ':verification_id' => $verificationId,
            ':user_id' => $userId,
            ':max_attempts' => self::MAX_ATTEMPTS,
        ]);
        return $stmt->rowCount() > 0;
    }

    public static function invalidateForUser(int $userId, ?PDO $pdo = null): void
    {
        $pdo ??= Database::getConnection();
        $stmt = $pdo->prepare(
            'UPDATE admin_login_verifications
             SET used_at = NOW()
             WHERE user_id = :user_id AND used_at IS NULL'
        );
        $stmt->execute([':user_id' => $userId]);
    }

    public static function invalidate(int $verificationId): void
    {
        $stmt = Database::getConnection()->prepare(
            'UPDATE admin_login_verifications SET used_at = NOW()
             WHERE verification_id = :verification_id AND used_at IS NULL'
        );
        $stmt->execute([':verification_id' => $verificationId]);
    }
}