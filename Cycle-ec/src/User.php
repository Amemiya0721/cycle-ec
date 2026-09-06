<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';

class User
{
    public static function create(string $name, string $email, string $password): int
    {
        $stmt = Database::getConnection()->prepare(
            'INSERT INTO users (name, email, password)
             VALUES (:name, :email, :password)'
        );
        $stmt->execute([
            ':name' => $name,
            ':email' => $email,
            ':password' => password_hash($password, PASSWORD_DEFAULT),
        ]);
        return (int) Database::getConnection()->lastInsertId();
    }

    public static function findByEmail(string $email): ?array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT user_id, name, email, password, is_admin, created_at, updated_at
             FROM users WHERE email = :email LIMIT 1'
        );
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();
        return $user === false ? null : $user;
    }

    public static function findById(int $userId): ?array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT user_id, name, email, password, is_admin, created_at, updated_at
             FROM users WHERE user_id = :user_id LIMIT 1'
        );
        $stmt->execute([':user_id' => $userId]);
        $user = $stmt->fetch();
        return $user === false ? null : $user;
    }
}
