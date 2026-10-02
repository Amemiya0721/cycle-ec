<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/Category.php';
require_once __DIR__ . '/includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

function respond(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    respond(405, ['success' => false, 'message' => 'POSTリクエストが必要です。']);
}

$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
if (!is_string($csrfToken)
    || empty($_SESSION['category_reorder_csrf'])
    || !hash_equals((string) $_SESSION['category_reorder_csrf'], $csrfToken)) {
    respond(403, ['success' => false, 'message' => 'CSRFトークンが正しくありません。']);
}

try {
    $payload = json_decode((string) file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $exception) {
    respond(400, ['success' => false, 'message' => '送信データを読み取れませんでした。']);
}

$categoryIds = is_array($payload) ? ($payload['category_ids'] ?? null) : null;
if (!is_array($categoryIds)
    || ($categoryIds !== [] && array_keys($categoryIds) !== range(0, count($categoryIds) - 1))) {
    respond(422, ['success' => false, 'message' => 'カテゴリ一覧の形式が正しくありません。']);
}
foreach ($categoryIds as $categoryId) {
    if (!is_int($categoryId) || $categoryId < 1) {
        respond(422, ['success' => false, 'message' => 'カテゴリIDが正しくありません。']);
    }
}
if (count($categoryIds) !== count(array_unique($categoryIds))) {
    respond(422, ['success' => false, 'message' => 'カテゴリIDが重複しています。']);
}

try {
    if (!Category::reorder($categoryIds)) {
        respond(409, ['success' => false, 'message' => 'カテゴリ一覧が更新されています。画面を再読み込みしてください。']);
    }
    respond(200, ['success' => true, 'message' => 'カテゴリの表示順を保存しました。']);
} catch (Throwable $exception) {
    error_log('Category reorder failed: ' . get_class($exception));
    respond(500, ['success' => false, 'message' => '表示順を保存できませんでした。変更は保存されていません。']);
}