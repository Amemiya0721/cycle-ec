<?php
// DB接続設定を返すファイル
return [
    'host' => 'localhost',   // MySQL のホスト名
    'dbname' => 'cycle-ec',  // データベース名
    'user' => 'root',        // ユーザー名
    'pass' => ''             // パスワード（Laragonは空 環境によって変更）
];


declare(strict_types=1);

/**
 * config.php
 * -----------------------------------------------------------
 * 環境依存の設定値をまとめる。
 * 本番運用時は環境変数（getenv）から読み込む形へ差し替えることを想定し、
 * ここではデフォルト値のみを定義している。
 */
return [
    'db' => [
        'host'     => getenv('DB_HOST') ?: '127.0.0.1',
        'port'     => getenv('DB_PORT') ?: '3306',
        'name'     => getenv('DB_NAME') ?: 'overhaul',
        'user'     => getenv('DB_USER') ?: 'overhaul_app',
        'password' => getenv('DB_PASSWORD') ?: '',
        'charset'  => 'utf8mb4',
    ],

    // 商品一覧の1ページあたりの表示件数（将来の page パラメータ対応の下地）
    'products' => [
        'per_page' => 12,
    ],
];
