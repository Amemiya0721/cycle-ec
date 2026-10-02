<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';

/** 注文処理で起こる「利用者に見せてよい」エラー（在庫切れなど） */
final class OrderException extends RuntimeException
{
}

/**
 * Order
 * -----------------------------------------------------------
 * 注文の作成（購入者・送付先のスナップショット保存）と、
 * 管理画面向けの一覧・詳細取得。
 *
 * 送付先は orders.shipping_* にコピーして保存する。
 * user_addresses を参照し続けると、後から住所を変更したときに
 * 過去の注文の送付先まで変わってしまうため。
 */
final class Order
{
    // ★ 実際に使っているステータス値があればここを合わせる
    public const STATUS_ORDERED  = '注文済み';
    public const STATUS_SHIPPED  = '発送済み';
    public const STATUS_CANCELED = 'キャンセル';

    public const STATUSES = [self::STATUS_ORDERED, self::STATUS_SHIPPED, self::STATUS_CANCELED];

    private const ADMIN_PER_PAGE = 20;

    /** 売り切れ判定（product-detail.php の isSoldOut() と同じ基準） */
    public static function isSoldOut(string $status, int $stockQuantity): bool
    {
        return in_array($status, ['売切れ', 'SOLD'], true) || $stockQuantity <= 0;
    }

    /**
     * 注文を確定する。orders / order_items / 在庫減算を1トランザクションで行う。
     *
     * @param int[] $productIds カート内の商品ID（1商品1点）
     * @param array<string, string> $shipping UserAddress::normalize() 済みの送付先
     * @return int order_id
     * @throws OrderException 売り切れ等、利用者に伝えるべき失敗
     */
    public static function create(int $userId, array $productIds, array $shipping): int
    {
        if ($userId <= 0) {
            throw new OrderException('ログイン状態を確認してから、もう一度お試しください。');
        }
        foreach ($productIds as $productId) {
            if ((!is_int($productId) && !(is_string($productId) && ctype_digit($productId)))
                || (int) $productId <= 0) {
                throw new OrderException('カート内の商品情報が正しくありません。');
            }
        }
        $productIds = array_values(array_unique(array_map('intval', $productIds)));
        sort($productIds); // ロック順を固定してデッドロックを避ける
        if ($productIds === []) {
            throw new OrderException('カートに商品がありません。');
        }

        $pdo = Database::getConnection();

        try {
            $pdo->beginTransaction();

            // 対象商品を行ロックして在庫を確定させる（同時購入による二重販売を防ぐ）
            $in = implode(',', array_fill(0, count($productIds), '?'));
            $stmt = $pdo->prepare(
                "SELECT product_id, name, price, status, stock_quantity
                 FROM products
                 WHERE product_id IN ({$in}) AND is_deleted = 0
                 ORDER BY product_id
                 FOR UPDATE"
            );
            $stmt->execute($productIds);

            $rows = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $rows[(int) $row['product_id']] = $row;
            }

            $unavailable = [];
            $totalCents = 0;
            foreach ($productIds as $productId) {
                $row = $rows[$productId] ?? null;
                if ($row === null) {
                    throw new OrderException('カート内の商品が見つからないか、購入できなくなりました。');
                }
                if (!in_array((string) $row['status'], ['販売中', 'on_sale'], true)) {
                    throw new OrderException((string) $row['name'] . ' は購入できない状態です。');
                }
                if (self::isSoldOut((string) $row['status'], (int) $row['stock_quantity'])) {
                    $unavailable[] = $row['name'] ?? ('商品ID ' . $productId);
                    continue;
                }
                $price = (string) $row['price'];
                if (!preg_match('/^\d{1,8}\.\d{2}$/D', $price)) {
                    throw new OrderException('商品価格が正しくないため、注文できません。');
                }
                $totalCents += (int) str_replace('.', '', $price);
                if ($totalCents > 9999999999) {
                    throw new OrderException('注文金額が上限を超えています。');
                }
            }
            if ($unavailable !== []) {
                throw new OrderException(
                    implode('、', $unavailable) . ' は売り切れ、または購入できなくなりました。'
                );
            }
            $total = sprintf('%d.%02d', intdiv($totalCents, 100), $totalCents % 100);

            $orderStmt = $pdo->prepare(
                'INSERT INTO orders
                    (user_id, total_price, status,
                     shipping_postal_code, shipping_prefecture, shipping_city,
                     shipping_address_line, shipping_building,
                     shipping_recipient_name, shipping_phone_number,
                     ordered_at, updated_at)
                 VALUES
                    (:user_id, :total_price, :status,
                     :postal_code, :prefecture, :city,
                     :address_line, :building,
                     :recipient_name, :phone_number,
                     NOW(), NOW())'
            );
            $orderStmt->execute([
                ':user_id'        => $userId,
                ':total_price'    => $total,
                ':status'         => self::STATUS_ORDERED,
                ':postal_code'    => $shipping['postal_code'],
                ':prefecture'     => $shipping['prefecture'],
                ':city'           => $shipping['city'],
                ':address_line'   => $shipping['address_line'],
                ':building'       => $shipping['building'],
                ':recipient_name' => $shipping['recipient_name'],
                ':phone_number'   => $shipping['phone_number'],
            ]);
            $orderId = (int) $pdo->lastInsertId();

            $itemStmt = $pdo->prepare(
                'INSERT INTO order_items (order_id, product_id, price)
                 VALUES (:order_id, :product_id, :price)'
            );
            $stockStmt = $pdo->prepare(
                'UPDATE products
                 SET stock_quantity = stock_quantity - 1
                 WHERE product_id = :product_id AND stock_quantity >= 1'
            );
            foreach ($productIds as $productId) {
                $itemStmt->execute([
                    ':order_id'   => $orderId,
                    ':product_id' => $productId,
                    ':price'      => $rows[$productId]['price'], // 注文時点の価格を保存
                ]);
                $stockStmt->execute([':product_id' => $productId]);
                if ($stockStmt->rowCount() !== 1) {
                    throw new OrderException('在庫の更新に失敗しました。もう一度お試しください。');
                }
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

    /** 注文済みの注文だけを発送済みまたはキャンセルへ遷移させる。 */
    public static function transitionStatus(int $orderId, string $targetStatus): bool
    {
        if ($orderId < 1 || !in_array($targetStatus, [self::STATUS_SHIPPED, self::STATUS_CANCELED], true)) {
            return false;
        }

        $pdo = Database::getConnection();
        try {
            $pdo->beginTransaction();

            $statusStmt = $pdo->prepare(
                'SELECT status FROM orders WHERE order_id = :order_id FOR UPDATE'
            );
            $statusStmt->execute([':order_id' => $orderId]);
            $currentStatus = $statusStmt->fetchColumn();
            if ($currentStatus !== self::STATUS_ORDERED) {
                $pdo->rollBack();
                return false;
            }

            if ($targetStatus === self::STATUS_CANCELED) {
                self::restoreOrderStock($pdo, $orderId);
            }

            $updateStmt = $pdo->prepare(
                'UPDATE orders
                 SET status = :status, updated_at = NOW()
                 WHERE order_id = :order_id AND status = :current_status'
            );
            $updateStmt->execute([
                ':status' => $targetStatus,
                ':order_id' => $orderId,
                ':current_status' => self::STATUS_ORDERED,
            ]);
            if ($updateStmt->rowCount() !== 1) {
                throw new RuntimeException('注文ステータスを更新できませんでした。');
            }

            $pdo->commit();
            return true;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** 明細1行を1点としてキャンセル注文の在庫を戻す。 */
    private static function restoreOrderStock(PDO $pdo, int $orderId): void
    {
        $itemsStmt = $pdo->prepare(
            'SELECT product_id, COUNT(*) AS quantity
             FROM order_items
             WHERE order_id = :order_id
             GROUP BY product_id
             ORDER BY product_id'
        );
        $itemsStmt->execute([':order_id' => $orderId]);

        $stockStmt = $pdo->prepare(
            'UPDATE products
                         SET stock_quantity = stock_quantity + :quantity_add
             WHERE product_id = :product_id
                             AND stock_quantity <= :max_stock - :quantity_limit'
        );
        foreach ($itemsStmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
            $quantity = (int) $item['quantity'];
            $stockStmt->execute([
                ':quantity_add' => $quantity,
                ':product_id' => (int) $item['product_id'],
                ':max_stock' => 4294967295,
                ':quantity_limit' => $quantity,
            ]);
            if ($stockStmt->rowCount() !== 1) {
                throw new RuntimeException('キャンセル注文の在庫を復元できませんでした。');
            }
        }
    }

    /**
     * 全注文（新しい順）。
     * 既存の admin/index.php（件数表示）と旧 admin/orders.php 互換のため残している。
     * user_name / email を含む。
     */
    public static function all(): array
    {
        $stmt = Database::getConnection()->query(
            'SELECT o.*, u.name AS user_name, u.email
             FROM orders o
             INNER JOIN users u ON u.user_id = o.user_id
             ORDER BY o.ordered_at DESC, o.order_id DESC'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** 注文に含まれる商品（注文時点の価格つき） */
    public static function items(int $orderId): array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT oi.order_item_id, oi.product_id, oi.price,
                    p.name, p.manufacturer
             FROM order_items oi
             LEFT JOIN products p ON p.product_id = oi.product_id
             WHERE oi.order_id = :order_id
             ORDER BY oi.order_item_id ASC'
        );
        $stmt->execute([':order_id' => $orderId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** 購入者本人の注文だけ取得する（注文完了画面用） */
    public static function findForUser(int $orderId, int $userId): ?array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT * FROM orders WHERE order_id = :order_id AND user_id = :user_id LIMIT 1'
        );
        $stmt->execute([':order_id' => $orderId, ':user_id' => $userId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($order === false) {
            return null;
        }
        $order['items'] = self::items($orderId);
        return $order;
    }

    /**
     * 管理画面の注文一覧。誰が買ったか（users）と送付先の概要を返す。
     *
     * @param array{status?: string, keyword?: string, page?: int} $criteria
     * @return array{items: array, total: int, page: int, per_page: int, total_pages: int}
     */
    public static function listForAdmin(array $criteria): array
    {
        $pdo = Database::getConnection();

        $where = ['1 = 1'];
        $params = [];
        $status = $criteria['status'] ?? '';
        if (is_string($status) && in_array($status, self::STATUSES, true)) {
            $where[] = 'o.status = :status';
            $params[':status'] = $status;
        }

        // 購入者名・メール・受取人名・注文番号で検索
        $keyword = trim((string) ($criteria['keyword'] ?? ''));
        if ($keyword !== '') {
            $like = '%' . addcslashes($keyword, '\\%_') . '%';
            $clauses = [
                'u.name LIKE :kw1',
                'u.email LIKE :kw2',
                'o.shipping_recipient_name LIKE :kw3',
            ];
            $params[':kw1'] = $like;
            $params[':kw2'] = $like;
            $params[':kw3'] = $like;
            if (ctype_digit($keyword)) {
                $clauses[] = 'o.order_id = :kw_id';
                $params[':kw_id'] = (int) $keyword;
            }
            $where[] = '(' . implode(' OR ', $clauses) . ')';
        }
        $whereSql = implode(' AND ', $where);

        $countStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM orders o
             INNER JOIN users u ON u.user_id = o.user_id
             WHERE {$whereSql}"
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $perPage = self::ADMIN_PER_PAGE;
        $totalPages = (int) max(1, ceil($total / $perPage));
        $page = min(max(1, (int) ($criteria['page'] ?? 1)), $totalPages);

        $stmt = $pdo->prepare(
            "SELECT o.order_id, o.total_price, o.status, o.ordered_at,
                    o.shipping_recipient_name, o.shipping_prefecture, o.shipping_city,
                    u.user_id AS buyer_id, u.name AS buyer_name, u.email AS buyer_email,
                    (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.order_id) AS item_count
             FROM orders o
             INNER JOIN users u ON u.user_id = o.user_id
             WHERE {$whereSql}
             ORDER BY o.ordered_at DESC, o.order_id DESC
             LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'items'       => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => $totalPages,
        ];
    }

    /**
     * ステータス別の注文件数（一覧ページの絞り込みタブ用）。
     *
     * @return array<string, int> ステータス => 件数
     */
    public static function statusCounts(): array
    {
        $rows = Database::getConnection()
            ->query('SELECT status, COUNT(*) AS cnt FROM orders GROUP BY status')
            ->fetchAll(PDO::FETCH_ASSOC);
        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row['status']] = (int) $row['cnt'];
        }
        return $counts;
    }

    /** 管理画面の注文詳細（購入者・送付先・商品） */
    public static function findForAdmin(int $orderId): ?array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT o.*, u.name AS buyer_name, u.email AS buyer_email
             FROM orders o
             INNER JOIN users u ON u.user_id = o.user_id
             WHERE o.order_id = :order_id
             LIMIT 1'
        );
        $stmt->execute([':order_id' => $orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($order === false) {
            return null;
        }
        $order['items'] = self::items($orderId);
        return $order;
    }
}
