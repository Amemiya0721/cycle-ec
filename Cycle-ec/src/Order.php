<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';

class Order
{
    public static function create(int $userId, array $shipping, array $productIds): int
    {
        if ($productIds === []) {
            throw new InvalidArgumentException('注文商品がありません。');
        }

        $pdo = Database::getConnection();
        $productStmt = $pdo->prepare(
            'SELECT product_id, price FROM products
             WHERE product_id = :product_id AND is_deleted = 0
               AND status = :status FOR UPDATE'
        );

        try {
            $pdo->beginTransaction();
            $items = [];
            $totalPrice = 0.0;
            foreach (array_unique($productIds) as $productId) {
                $productStmt->execute([
                    ':product_id' => (int) $productId,
                    ':status' => '販売中',
                ]);
                $product = $productStmt->fetch();
                if ($product === false) {
                    throw new InvalidArgumentException('注文できない商品が含まれています。');
                }
                $items[] = $product;
                $totalPrice += (float) $product['price'];
            }

            $orderStmt = $pdo->prepare(
                'INSERT INTO orders
                    (user_id, total_price, status, shipping_postal_code,
                     shipping_prefecture, shipping_city, shipping_address_line,
                     shipping_building, shipping_recipient_name,
                     shipping_phone_number)
                 VALUES
                    (:user_id, :total_price, :status, :postal_code,
                     :prefecture, :city, :address_line, :building,
                     :recipient_name, :phone_number)'
            );
            $orderStmt->execute([
                ':user_id' => $userId,
                ':total_price' => $totalPrice,
                ':status' => 'pending',
                ':postal_code' => $shipping['postal_code'],
                ':prefecture' => $shipping['prefecture'],
                ':city' => $shipping['city'],
                ':address_line' => $shipping['address_line'],
                ':building' => $shipping['building'] ?? null,
                ':recipient_name' => $shipping['recipient_name'],
                ':phone_number' => $shipping['phone_number'],
            ]);
            $orderId = (int) $pdo->lastInsertId();

            $itemStmt = $pdo->prepare(
                'INSERT INTO order_items (order_id, product_id, price)
                 VALUES (:order_id, :product_id, :price)'
            );
            foreach ($items as $item) {
                $itemStmt->execute([
                    ':order_id' => $orderId,
                    ':product_id' => $item['product_id'],
                    ':price' => $item['price'],
                ]);
            }

            $soldStmt = $pdo->prepare(
                'UPDATE products SET status = :sold_status
                 WHERE product_id = :product_id AND is_deleted = 0'
            );
            foreach ($items as $item) {
                $soldStmt->execute([
                    ':sold_status' => '売切れ',
                    ':product_id' => $item['product_id'],
                ]);
            }

            $pdo->commit();
            return $orderId;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public static function findById(int $orderId, int $userId): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT order_id, user_id, total_price, status,
                    shipping_postal_code, shipping_prefecture, shipping_city,
                    shipping_address_line, shipping_building,
                    shipping_recipient_name, shipping_phone_number,
                    ordered_at, updated_at
             FROM orders WHERE order_id = :order_id
               AND user_id = :user_id LIMIT 1'
        );
        $stmt->execute([':order_id' => $orderId, ':user_id' => $userId]);
        $order = $stmt->fetch();
        if ($order === false) {
            return null;
        }

        $itemStmt = $pdo->prepare(
            'SELECT oi.order_item_id, oi.product_id, oi.price,
                    p.name, c.name AS category_name
             FROM order_items oi
             INNER JOIN products p ON p.product_id = oi.product_id
             INNER JOIN categories c ON c.category_id = p.category_id
             WHERE oi.order_id = :order_id
             ORDER BY oi.order_item_id ASC'
        );
        $itemStmt->execute([':order_id' => $orderId]);
        $order['items'] = $itemStmt->fetchAll();
        return $order;
    }

    public static function all(): array
    {
        $stmt = Database::getConnection()->query(
            'SELECT o.order_id, o.user_id, u.name AS user_name,
                    u.email, o.total_price, o.status, o.ordered_at
             FROM orders o INNER JOIN users u ON u.user_id = o.user_id
             ORDER BY o.ordered_at DESC, o.order_id DESC'
        );
        return $stmt->fetchAll();
    }
}
