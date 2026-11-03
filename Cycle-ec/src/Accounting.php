<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';

final class Accounting
{
    private const GROUP_FORMATS = [
        'day' => '%Y-%m-%d',
        'month' => '%Y-%m',
        'year' => '%Y',
    ];

    /** @return array{start: string, end: string} */
    public static function dateRange(string $period, string $date): array
    {
        if (!isset(self::GROUP_FORMATS[$period]) || !self::isValidDate($date)) {
            throw new InvalidArgumentException('集計期間が正しくありません。');
        }
        $selected = new DateTimeImmutable($date);
        $start = match ($period) {
            'day' => $selected,
            'month' => $selected->modify('first day of this month'),
            'year' => $selected->setDate((int) $selected->format('Y'), 1, 1),
        };
        $end = match ($period) {
            'day' => $start->modify('+1 day'),
            'month' => $start->modify('+1 month'),
            'year' => $start->modify('+1 year'),
        };
        return ['start' => $start->format('Y-m-d'), 'end' => $end->format('Y-m-d')];
    }

    public static function addExpense(string $date, string $category, string $description, string $amount): void
    {
        if (!self::isValidDate($date)) {
            throw new InvalidArgumentException('経費日を正しく入力してください。');
        }
        if ($category === '' || mb_strlen($category) > 80) {
            throw new InvalidArgumentException('科目は1〜80文字で入力してください。');
        }
        if ($description === '' || mb_strlen($description) > 255) {
            throw new InvalidArgumentException('内容は1〜255文字で入力してください。');
        }
        if (preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', $amount) !== 1 || (float) $amount <= 0) {
            throw new InvalidArgumentException('金額は0円より大きく、1億円未満で入力してください。');
        }
        $stmt = Database::getConnection()->prepare(
            'INSERT INTO operating_expenses (expense_date, category, description, amount, created_at)
             VALUES (:expense_date, :category, :description, :amount, NOW())'
        );
        $stmt->execute([
            ':expense_date' => $date,
            ':category' => $category,
            ':description' => $description,
            ':amount' => $amount,
        ]);
    }

    public static function deleteExpense(int $expenseId): bool
    {
        if ($expenseId < 1) {
            return false;
        }
        $stmt = Database::getConnection()->prepare(
            'DELETE FROM operating_expenses WHERE expense_id = :expense_id'
        );
        $stmt->execute([':expense_id' => $expenseId]);
        return $stmt->rowCount() === 1;
    }

    /** @return array<string, mixed> */
    public static function report(string $startDate, string $endDate, string $period): array
    {
        if (!isset(self::GROUP_FORMATS[$period]) || !self::isValidDate($startDate) || !self::isValidDate($endDate)) {
            throw new InvalidArgumentException('集計期間が正しくありません。');
        }
        $format = self::GROUP_FORMATS[$period];
        $pdo = Database::getConnection();
        $paid = $pdo->prepare(
            "SELECT DATE_FORMAT(o.paid_at, '{$format}') AS period,
                    COUNT(*) AS order_count,
                    SUM(o.subtotal) AS merchandise_sales,
                    SUM(o.shipping_fee) AS shipping_sales,
                    SUM(o.total_price) AS sales,
                    SUM(COALESCE(items.cost_total, 0)) AS cost_of_goods,
                    SUM(COALESCE(items.unknown_cost_count, 0)) AS unknown_cost_count
             FROM orders o
             LEFT JOIN (
                 SELECT order_id, SUM(cost_price) AS cost_total,
                        SUM(cost_price IS NULL) AS unknown_cost_count
                 FROM order_items GROUP BY order_id
             ) items ON items.order_id = o.order_id
             WHERE o.payment_status = 'paid'
               AND o.status <> :paid_cancelled_status
               AND o.paid_at >= :start_at AND o.paid_at < :end_at
             GROUP BY period ORDER BY period"
        );
        $paid->execute([
            ':paid_cancelled_status' => 'キャンセル',
            ':start_at' => $startDate . ' 00:00:00',
            ':end_at' => $endDate . ' 00:00:00',
        ]);

        $orders = $pdo->prepare(
            "SELECT DATE_FORMAT(o.ordered_at, '{$format}') AS period,
                    COUNT(*) AS order_count,
                    SUM(o.total_price) AS order_amount,
                    SUM(CASE WHEN o.payment_status = 'paid' AND o.status <> :paid_count_cancelled_status THEN 1 ELSE 0 END) AS paid_order_count,
                    SUM(CASE WHEN o.payment_status = 'unpaid' AND o.status <> :unpaid_count_cancelled_status THEN 1 ELSE 0 END) AS unpaid_order_count,
                    SUM(CASE WHEN o.payment_status = 'paid' AND o.status <> :paid_cancelled_status THEN o.total_price ELSE 0 END) AS paid_amount,
                    SUM(CASE WHEN o.payment_status = 'unpaid' AND o.status <> :unpaid_amount_cancelled_status THEN o.total_price ELSE 0 END) AS unpaid_amount,
                    SUM(CASE WHEN o.status = :cancelled_status THEN o.total_price ELSE 0 END) AS cancelled_amount
             FROM orders o
             WHERE o.ordered_at >= :start_at AND o.ordered_at < :end_at
             GROUP BY period ORDER BY period"
        );
        $orders->execute([
            ':paid_cancelled_status' => 'キャンセル',
            ':unpaid_amount_cancelled_status' => 'キャンセル',
            ':cancelled_status' => 'キャンセル',
            ':paid_count_cancelled_status' => 'キャンセル',
            ':unpaid_count_cancelled_status' => 'キャンセル',
            ':start_at' => $startDate . ' 00:00:00',
            ':end_at' => $endDate . ' 00:00:00',
        ]);

        $expenses = $pdo->prepare(
            "SELECT DATE_FORMAT(expense_date, '{$format}') AS period, SUM(amount) AS amount
             FROM operating_expenses
             WHERE expense_date >= :start_date AND expense_date < :end_date
             GROUP BY period ORDER BY period"
        );
        $expenses->execute([':start_date' => $startDate, ':end_date' => $endDate]);

        $totals = $pdo->prepare(
            'SELECT
                (SELECT COALESCE(SUM(total_price), 0) FROM orders
                 WHERE payment_status = \'paid\' AND status <> :sales_cancelled_status
                   AND paid_at >= :sales_start_at AND paid_at < :sales_end_at) AS sales,
                (SELECT COALESCE(SUM(oi.cost_price), 0) FROM orders o
                 JOIN order_items oi ON oi.order_id = o.order_id
                 WHERE o.payment_status = \'paid\' AND o.status <> :cost_cancelled_status
                   AND o.paid_at >= :cost_start_at AND o.paid_at < :cost_end_at) AS cost_of_goods,
                (SELECT COUNT(*) FROM orders o JOIN order_items oi ON oi.order_id = o.order_id
                 WHERE o.payment_status = \'paid\' AND o.status <> :unknown_cancelled_status
                   AND oi.cost_price IS NULL AND o.paid_at >= :unknown_start_at AND o.paid_at < :unknown_end_at) AS unknown_cost_items,
                (SELECT COALESCE(SUM(amount), 0) FROM operating_expenses
                 WHERE expense_date >= :start_date AND expense_date < :end_date) AS expenses,
                (SELECT COALESCE(SUM(total_price), 0) FROM orders
                 WHERE payment_status = \'unpaid\' AND status <> :outstanding_cancelled_status) AS outstanding'
                . ', (SELECT COUNT(*) FROM orders
                 WHERE payment_status = \'unpaid\' AND status <> :outstanding_count_cancelled_status) AS outstanding_orders'
        );
        $totals->execute([
            ':sales_cancelled_status' => 'キャンセル',
            ':sales_start_at' => $startDate . ' 00:00:00',
            ':sales_end_at' => $endDate . ' 00:00:00',
            ':cost_cancelled_status' => 'キャンセル',
            ':cost_start_at' => $startDate . ' 00:00:00',
            ':cost_end_at' => $endDate . ' 00:00:00',
            ':unknown_cancelled_status' => 'キャンセル',
            ':unknown_start_at' => $startDate . ' 00:00:00',
            ':unknown_end_at' => $endDate . ' 00:00:00',
            ':outstanding_cancelled_status' => 'キャンセル',
            ':outstanding_count_cancelled_status' => 'キャンセル',
            ':start_date' => $startDate,
            ':end_date' => $endDate,
        ]);

        $expenseRows = $pdo->prepare(
            'SELECT expense_id, expense_date, category, description, amount
             FROM operating_expenses
             WHERE expense_date >= :start_date AND expense_date < :end_date
             ORDER BY expense_date DESC, expense_id DESC'
        );
        $expenseRows->execute([':start_date' => $startDate, ':end_date' => $endDate]);

        return [
            'paid' => $paid->fetchAll(PDO::FETCH_ASSOC),
            'orders' => $orders->fetchAll(PDO::FETCH_ASSOC),
            'expenses_by_period' => $expenses->fetchAll(PDO::FETCH_ASSOC),
            'totals' => $totals->fetch(PDO::FETCH_ASSOC),
            'expenses' => $expenseRows->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    /** @return array<int, array<int, mixed>> */
    public static function exportRows(string $startDate, string $endDate): array
    {
        $pdo = Database::getConnection();
        $rows = [];
        $orders = $pdo->prepare(
            'SELECT o.order_number, o.ordered_at, o.paid_at, o.total_price, o.payment_status, o.status,
                    COALESCE(SUM(oi.cost_price), 0) AS cost_of_goods,
                    SUM(oi.cost_price IS NULL) AS unknown_cost_count,
                    GROUP_CONCAT(DISTINCT p.name ORDER BY p.name SEPARATOR \' / \') AS product_names
             FROM orders o
             LEFT JOIN order_items oi ON oi.order_id = o.order_id
             LEFT JOIN products p ON p.product_id = oi.product_id
             WHERE (
                    (o.payment_status = \'paid\' AND o.paid_at >= :start_paid AND o.paid_at < :end_paid)
                    OR (o.payment_status = \'unpaid\' AND o.ordered_at >= :start_ordered AND o.ordered_at < :end_ordered)
                   )
               AND o.status <> :cancelled_status
             GROUP BY o.order_id
             ORDER BY COALESCE(o.paid_at, o.ordered_at), o.order_id'
        );
        $orders->execute([
            ':start_paid' => $startDate . ' 00:00:00',
            ':end_paid' => $endDate . ' 00:00:00',
            ':start_ordered' => $startDate . ' 00:00:00',
            ':end_ordered' => $endDate . ' 00:00:00',
            ':cancelled_status' => 'キャンセル',
        ]);
        foreach ($orders->fetchAll(PDO::FETCH_ASSOC) as $order) {
            $isPaid = $order['payment_status'] === 'paid';
            $rows[] = [
                $isPaid ? (string) $order['paid_at'] : (string) $order['ordered_at'],
                $isPaid ? '入金済み売上' : '未入金注文',
                (string) $order['order_number'],
                (string) ($order['product_names'] ?? ''),
                $isPaid ? (string) $order['total_price'] : '0.00',
                $isPaid && (int) $order['unknown_cost_count'] === 0 ? (string) $order['cost_of_goods'] : '',
                '0.00',
                '0.00',
                !$isPaid ? (string) $order['total_price'] : '0.00',
                (string) $order['payment_status'],
            ];
        }

        $expenses = $pdo->prepare(
            'SELECT expense_date, category, description, amount
             FROM operating_expenses
             WHERE expense_date >= :start_date AND expense_date < :end_date
             ORDER BY expense_date, expense_id'
        );
        $expenses->execute([':start_date' => $startDate, ':end_date' => $endDate]);
        foreach ($expenses->fetchAll(PDO::FETCH_ASSOC) as $expense) {
            $rows[] = [
                (string) $expense['expense_date'],
                '経費',
                '',
                (string) $expense['category'] . ': ' . (string) $expense['description'],
                '0.00',
                '0.00',
                (string) $expense['amount'],
                '0.00',
                '0.00',
                '',
            ];
        }

        $purchases = $pdo->prepare(
            'SELECT purchase_date, record_type, product_id, quantity, unit_cost, total_cost, notes
             FROM inventory_purchases
             WHERE purchase_date >= :start_date AND purchase_date < :end_date
             ORDER BY purchase_date, purchase_id'
        );
        $purchases->execute([':start_date' => $startDate, ':end_date' => $endDate]);
        foreach ($purchases->fetchAll(PDO::FETCH_ASSOC) as $purchase) {
            $rows[] = [
                (string) $purchase['purchase_date'],
                $purchase['record_type'] === 'opening_balance' ? '期首在庫原価' : '仕入',
                '',
                '商品ID ' . (int) $purchase['product_id'] . ' / ' . (int) $purchase['quantity'] . '点 / 単価 ' . (string) $purchase['unit_cost'] . ' / ' . (string) ($purchase['notes'] ?? ''),
                '0.00',
                '0.00',
                '0.00',
                $purchase['record_type'] === 'purchase' ? (string) $purchase['total_cost'] : '0.00',
                '0.00',
                '',
            ];
        }

        usort($rows, static fn(array $a, array $b): int => strcmp((string) $a[0], (string) $b[0]));
        return $rows;
    }

    private static function isValidDate(string $date): bool
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }
}
