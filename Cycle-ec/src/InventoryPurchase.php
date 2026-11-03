<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';

final class InventoryPurchase
{
    public static function recordPurchase(
        int $productId,
        int $quantity,
        string $unitCost,
        string $purchaseDate,
        ?string $notes
    ): void {
        self::validate($productId, $quantity, $unitCost, $purchaseDate, $notes);
        $pdo = Database::getConnection();

        try {
            $pdo->beginTransaction();
            $product = self::lockProduct($pdo, $productId);
            $stock = (int) $product['stock_quantity'];
            $currentCost = $product['average_cost'];
            if ($stock > 0 && $currentCost === null) {
                throw new InvalidArgumentException('既存在庫の初期仕入原価を先に登録してください。');
            }
            if ($stock > 4294967295 - $quantity) {
                throw new InvalidArgumentException('仕入後の在庫数が上限を超えます。');
            }

            $insert = $pdo->prepare(
                'INSERT INTO inventory_purchases
                    (product_id, record_type, quantity, unit_cost, total_cost, purchase_date, notes, created_at)
                 VALUES
                    (:product_id, \'purchase\', :quantity, :unit_cost, ROUND(:total_cost_unit * :total_cost_quantity, 2), :purchase_date, :notes, NOW())'
            );
            $insert->execute([
                ':product_id' => $productId,
                ':quantity' => $quantity,
                ':unit_cost' => $unitCost,
                ':total_cost_unit' => $unitCost,
                ':total_cost_quantity' => $quantity,
                ':purchase_date' => $purchaseDate,
                ':notes' => $notes,
            ]);

            $update = $pdo->prepare(
                'UPDATE products
                 SET average_cost = ROUND((stock_quantity * COALESCE(average_cost, 0)
                         + (:cost_unit * :cost_quantity)) / (stock_quantity + :quantity_for_average), 2),
                     status = CASE WHEN status = \'sold_out\' THEN \'on_sale\' ELSE status END,
                     stock_quantity = :stock_quantity,
                     updated_at = NOW()
                 WHERE product_id = :product_id'
            );
            $update->execute([
                ':stock_quantity' => $stock + $quantity,
                ':cost_unit' => $unitCost,
                ':cost_quantity' => $quantity,
                ':quantity_for_average' => $quantity,
                ':product_id' => $productId,
            ]);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public static function recordOpeningBalance(
        int $productId,
        string $unitCost,
        string $purchaseDate,
        ?string $notes
    ): void {
        self::validate($productId, 1, $unitCost, $purchaseDate, $notes);
        $pdo = Database::getConnection();

        try {
            $pdo->beginTransaction();
            $product = self::lockProduct($pdo, $productId);
            $stock = (int) $product['stock_quantity'];
            if ($stock === 0) {
                throw new InvalidArgumentException('在庫がある商品にのみ初期原価を設定できます。');
            }

            $existing = $pdo->prepare(
                'SELECT COUNT(*) FROM inventory_purchases
                 WHERE product_id = :product_id AND record_type = \'opening_balance\''
            );
            $existing->execute([':product_id' => $productId]);
            if ((int) $existing->fetchColumn() > 0) {
                throw new InvalidArgumentException('この商品の初期在庫原価はすでに登録されています。');
            }
            $purchases = $pdo->prepare(
                'SELECT COUNT(*) FROM inventory_purchases
                 WHERE product_id = :product_id AND record_type = \'purchase\''
            );
            $purchases->execute([':product_id' => $productId]);
            if ((int) $purchases->fetchColumn() > 0) {
                throw new InvalidArgumentException('通常仕入の前に初期在庫原価を登録してください。');
            }

            $insert = $pdo->prepare(
                'INSERT INTO inventory_purchases
                    (product_id, record_type, quantity, unit_cost, total_cost, purchase_date, notes, created_at)
                 VALUES
                    (:product_id, \'opening_balance\', :quantity, :unit_cost, ROUND(:total_cost_unit * :total_cost_quantity, 2), :purchase_date, :notes, NOW())'
            );
            $insert->execute([
                ':product_id' => $productId,
                ':quantity' => $stock,
                ':unit_cost' => $unitCost,
                ':total_cost_unit' => $unitCost,
                ':total_cost_quantity' => $stock,
                ':purchase_date' => $purchaseDate,
                ':notes' => $notes,
            ]);

            $update = $pdo->prepare(
                'UPDATE products SET average_cost = :average_cost, updated_at = NOW()
                 WHERE product_id = :product_id'
            );
            $update->execute([':average_cost' => $unitCost, ':product_id' => $productId]);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @return array{items: array<int, array<string, mixed>>, products: array<int, array<string, mixed>>} */
    public static function adminData(): array
    {
        $pdo = Database::getConnection();
        $items = $pdo->query(
            'SELECT ip.purchase_id, ip.product_id, p.name AS product_name, ip.record_type,
                    ip.quantity, ip.unit_cost, ip.total_cost, ip.purchase_date, ip.notes, ip.created_at
             FROM inventory_purchases ip
             JOIN products p ON p.product_id = ip.product_id
             ORDER BY ip.purchase_date DESC, ip.purchase_id DESC
             LIMIT 100'
        )->fetchAll(PDO::FETCH_ASSOC);
        $products = $pdo->query(
            'SELECT product_id, name, stock_quantity, average_cost
             FROM products WHERE is_deleted = 0 ORDER BY name, product_id'
        )->fetchAll(PDO::FETCH_ASSOC);
        return ['items' => $items, 'products' => $products];
    }

    private static function lockProduct(PDO $pdo, int $productId): array
    {
        $stmt = $pdo->prepare(
            'SELECT product_id, stock_quantity, average_cost
             FROM products WHERE product_id = :product_id AND is_deleted = 0
             FOR UPDATE'
        );
        $stmt->execute([':product_id' => $productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($product === false) {
            throw new InvalidArgumentException('商品が見つかりません。');
        }
        return $product;
    }

    private static function validate(
        int $productId,
        int $quantity,
        string $unitCost,
        string $purchaseDate,
        ?string $notes
    ): void {
        if ($productId < 1 || $quantity < 1 || $quantity > 4294967295) {
            throw new InvalidArgumentException('商品または数量が正しくありません。');
        }
        if (preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', $unitCost) !== 1) {
            throw new InvalidArgumentException('仕入単価は0円以上、1億円未満で入力してください。');
        }
        if (!self::isValidDate($purchaseDate)) {
            throw new InvalidArgumentException('仕入日を正しく入力してください。');
        }
        if ($notes !== null && mb_strlen($notes) > 500) {
            throw new InvalidArgumentException('備考は500文字以内で入力してください。');
        }
    }

    private static function isValidDate(string $date): bool
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }

}
