<?php
declare(strict_types=1);

/**
 * Database
 * -----------------------------------------------------------
 * PDO接続を一元管理する。
 * 将来的に接続先（レプリカ/検索用DB等）が増えても、
 * ここだけを拡張すればよい構造にしておく。
 */
class Database
{
    private static ?PDO $connection = null;

    public static function getConnection(): PDO
    {
        if (self::$connection === null) {
            $config = require __DIR__ . '/../config/config.php';
            $db = $config['db'];

            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $db['host'],
                $db['port'],
                $db['name'],
                $db['charset']
            );

            self::$connection = new PDO($dsn, $db['user'], $db['password'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false, // プレースホルダを実際のプリペアドステートメントとして扱う
            ]);
        }

        return self::$connection;
    }
}
