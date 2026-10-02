<?php

if (is_file(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

$envFile = __DIR__ . '/../.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if ($value !== '' && (($value[0] ?? '') === '"' || ($value[0] ?? '') === "'")) {
            $value = trim($value, "\"'");
        }
        if ($key !== '') {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }
}

/**
 * config.php
 * -----------------------------------------------------------
 * アプリケーション全体の設定を管理する。
 *
 * 環境依存の値は環境変数から取得する。
 * 環境変数が設定されていない場合はデフォルト値を使用する。
 *
 * 本番環境ではDB_USER / DB_PASSWORD等を
 * 環境変数から設定することを想定。
 */

return [

    /*
     * -------------------------------------------------------
     * アプリケーション設定
     * -------------------------------------------------------
     */
    'app' => [
        'base_url' => getenv('BASE_URL') ?: '/',
    ],

    'site' => [
        'name' => getenv('SITE_NAME') ?: 'OVERHAUL',
        'url' => getenv('SITE_URL') ?: '',
        'contact_email' => getenv('CONTACT_EMAIL') ?: '',
    ],

    /*
     * -------------------------------------------------------
     * データベース設定
     * -------------------------------------------------------
     */
    'db' => [
        'host'     => getenv('DB_HOST') ?: '127.0.0.1',
        'port'     => getenv('DB_PORT') ?: '3306',
        'name'     => getenv('DB_NAME') ?: 'cycle_ec',
        'user'     => getenv('DB_USER') ?: 'root',
        'password' => getenv('DB_PASSWORD') ?: '',
        'charset'  => 'utf8mb4',
    ],

    /*
     * -------------------------------------------------------
     * 商品一覧設定
     * -------------------------------------------------------
     */
    'products' => [
        'per_page' => 12,
    ],

    'mail' => [
        'from' => getenv('MAIL_FROM') ?: 'no-reply@example.com',
    ],

    'smtp' => [
        'host' => getenv('SMTP_HOST') ?: 'smtp.gmail.com',
        'port' => (int) (getenv('SMTP_PORT') ?: 587),
        'username' => getenv('SMTP_USERNAME') ?: '',
        'password' => getenv('SMTP_PASSWORD') ?: '',
        'encryption' => getenv('SMTP_ENCRYPTION') ?: 'tls',
        'from_address' => getenv('SMTP_FROM_ADDRESS') ?: (getenv('SMTP_USERNAME') ?: ''),
        'from_name' => getenv('SMTP_FROM_NAME') ?: 'OVERHAUL',
    ],
];