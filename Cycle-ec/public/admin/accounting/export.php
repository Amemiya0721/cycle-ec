<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../src/Accounting.php';
require_once __DIR__ . '/../includes/auth.php';

$period = is_string($_GET['period'] ?? null) ? $_GET['period'] : '';
$date = is_string($_GET['date'] ?? null) ? $_GET['date'] : '';
try {
    $range = Accounting::dateRange($period, $date);
    $rows = Accounting::exportRows($range['start'], $range['end']);
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    exit('出力期間が正しくありません。');
} catch (Throwable $exception) {
    error_log('Accounting CSV export failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('帳簿CSVを作成できません。DBマイグレーションの適用状況を確認してください。');
}

$safeCell = static function (string $value): string {
    return preg_match('/^[\s]*[=+\-@]/u', $value) === 1 ? "'" . $value : $value;
};
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="accounting-' . $range['start'] . '.csv"');
header('X-Content-Type-Options: nosniff');
echo "\xEF\xBB\xBF";
$output = fopen('php://output', 'wb');
if ($output === false) {
    throw new RuntimeException('CSV出力を開始できません。');
}
fputcsv($output, ['日付', '区分', '注文番号', '内容', '売上', '売上原価', '経費', '仕入支出', '未入金額', '入金状態']);
foreach ($rows as $row) {
    foreach ($row as $index => $value) {
        if (is_string($value) && in_array($index, [1, 2, 3, 9], true)) {
            $row[$index] = $safeCell($value);
        }
    }
    fputcsv($output, $row);
}
fclose($output);
