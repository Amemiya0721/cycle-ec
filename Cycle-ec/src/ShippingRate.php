<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';

final class ShippingRateException extends RuntimeException
{
}

final class ShippingRate
{
    /** @return array<int, array<string, mixed>> */
    public static function all(): array
    {
        $stmt = Database::getConnection()->query(
            'SELECT shipping_rate_id, shipping_size, sort_order, shipping_fee, is_active
             FROM shipping_rates
             ORDER BY sort_order ASC, shipping_rate_id ASC'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<int, array<string, mixed>> */
    public static function active(): array
    {
        $stmt = Database::getConnection()->query(
            'SELECT shipping_rate_id, shipping_size, sort_order, shipping_fee, is_active
             FROM shipping_rates
             WHERE is_active = 1
             ORDER BY sort_order ASC, shipping_rate_id ASC'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function isActiveSize(string $shippingSize): bool
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT 1 FROM shipping_rates
             WHERE shipping_size = :shipping_size AND is_active = 1
             LIMIT 1'
        );
        $stmt->execute([':shipping_size' => $shippingSize]);
        return $stmt->fetchColumn() !== false;
    }

    public static function create(array $data): int
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO shipping_rates (shipping_size, sort_order, shipping_fee, is_active)
             VALUES (:shipping_size, :sort_order, :shipping_fee, :is_active)'
        );
        $stmt->execute([
            ':shipping_size' => $data['shipping_size'],
            ':sort_order' => $data['sort_order'],
            ':shipping_fee' => $data['shipping_fee'],
            ':is_active' => $data['is_active'] ? 1 : 0,
        ]);
        return (int) $pdo->lastInsertId();
    }

    public static function update(int $shippingRateId, array $data): bool
    {
        $pdo = Database::getConnection();
        $pdo->beginTransaction();
        try {
            $existsStmt = $pdo->prepare(
                'SELECT shipping_rate_id FROM shipping_rates
                 WHERE shipping_rate_id = :shipping_rate_id
                 FOR UPDATE'
            );
            $existsStmt->execute([':shipping_rate_id' => $shippingRateId]);
            if ($existsStmt->fetchColumn() === false) {
                $pdo->commit();
                return false;
            }

            $stmt = $pdo->prepare(
                'UPDATE shipping_rates
                 SET shipping_size = :shipping_size,
                     sort_order = :sort_order,
                     shipping_fee = :shipping_fee,
                     is_active = :is_active
                 WHERE shipping_rate_id = :shipping_rate_id'
            );
            $stmt->execute([
                ':shipping_rate_id' => $shippingRateId,
                ':shipping_size' => $data['shipping_size'],
                ':sort_order' => $data['sort_order'],
                ':shipping_fee' => $data['shipping_fee'],
                ':is_active' => $data['is_active'] ? 1 : 0,
            ]);
            $pdo->commit();
            return true;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    /**
     * Delete a rate only when no product uses its size.
     *
     * @return array<int, array{product_id:int, name:string}> Products that block deletion.
     */
    public static function delete(int $shippingRateId): array
    {
        $pdo = Database::getConnection();
        $pdo->beginTransaction();
        try {
            $rateStmt = $pdo->prepare(
                'SELECT shipping_size FROM shipping_rates
                 WHERE shipping_rate_id = :shipping_rate_id
                 FOR UPDATE'
            );
            $rateStmt->execute([':shipping_rate_id' => $shippingRateId]);
            $shippingSize = $rateStmt->fetchColumn();
            if ($shippingSize === false) {
                throw new RuntimeException('送料サイズが見つかりません。');
            }

            $productsStmt = $pdo->prepare(
                'SELECT product_id, name
                 FROM products
                 WHERE shipping_size = :shipping_size
                 ORDER BY product_id ASC'
            );
            $productsStmt->execute([':shipping_size' => $shippingSize]);
            $products = array_map(
                static fn (array $product): array => [
                    'product_id' => (int) $product['product_id'],
                    'name' => (string) $product['name'],
                ],
                $productsStmt->fetchAll(PDO::FETCH_ASSOC)
            );
            if ($products !== []) {
                $pdo->commit();
                return $products;
            }

            $deleteStmt = $pdo->prepare(
                'DELETE FROM shipping_rates WHERE shipping_rate_id = :shipping_rate_id'
            );
            $deleteStmt->execute([':shipping_rate_id' => $shippingRateId]);
            $pdo->commit();
            return [];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    /**
     * Resolve the largest active size for a set of products.
     *
     * @param array<int, array<string, mixed>> $products
     * @return array{shipping_rate_id:int, shipping_size:string, shipping_fee:string, sort_order:int}
     */
    public static function quoteForProducts(PDO $pdo, array $products, bool $forUpdate = false): array
    {
        if ($products === []) {
            throw new ShippingRateException('送料を計算する商品がありません。');
        }

        $sizes = [];
        foreach ($products as $product) {
            $size = trim((string) ($product['shipping_size'] ?? ''));
            if ($size === '') {
                throw new ShippingRateException('商品に配送サイズが設定されていません。');
            }
            $sizes[$size] = true;
        }

        $placeholders = [];
        $params = [];
        foreach (array_keys($sizes) as $index => $size) {
            $placeholder = ':size_' . $index;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $size;
        }

        $stmt = $pdo->prepare(
            'SELECT shipping_rate_id, shipping_size, sort_order, shipping_fee, is_active
             FROM shipping_rates
             WHERE shipping_size IN (' . implode(', ', $placeholders) . ')
             ORDER BY sort_order ASC, shipping_rate_id ASC'
             . ($forUpdate ? ' FOR UPDATE' : '')
        );
        $stmt->execute($params);
        $rates = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $rate) {
            $rates[(string) $rate['shipping_size']] = $rate;
        }

        $largest = null;
        foreach (array_keys($sizes) as $size) {
            $rate = $rates[$size] ?? null;
            if ($rate === null || (int) $rate['is_active'] !== 1) {
                throw new ShippingRateException(
                    '配送サイズ「' . $size . '」の送料が未登録または無効です。管理者へお問い合わせください。'
                );
            }
            if ($largest === null || (int) $rate['sort_order'] > (int) $largest['sort_order']) {
                $largest = $rate;
            }
        }

        if ($largest === null) {
            throw new ShippingRateException('送料を計算できませんでした。');
        }

        return [
            'shipping_rate_id' => (int) $largest['shipping_rate_id'],
            'shipping_size' => (string) $largest['shipping_size'],
            'shipping_fee' => (string) $largest['shipping_fee'],
            'sort_order' => (int) $largest['sort_order'],
        ];
    }
}
