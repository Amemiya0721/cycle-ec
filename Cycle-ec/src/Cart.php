<?php
declare(strict_types=1);

/**
 * OVERHAUL - Cart
 *
 * Cookie + MySQLによるカート管理
 * Cookie : ランダムな識別トークン
 * MySQL  : カートの商品ID
 * Session: checkout.phpとの互換性維持
 */
final class Cart
{
    private const COOKIE_NAME = 'overhaul_cart';
    private const COOKIE_LIFETIME = 60 * 60 * 24 * 30;
    private const MAX_ITEMS = 50;

    private PDO $pdo;
    private int $cartId;
    private ?string $lastError = null;

    public function __construct(PDO $pdo)
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (headers_sent()) {
            throw new RuntimeException(
                'HTML出力前にCartを初期化してください。'
            );
        }

        $this->pdo = $pdo;
        $this->cartId = $this->loadOrCreateCart();

        // 旧セッションカートを初回のみDBへ移行
        if (!isset($_SESSION['cart_db_initialized'])) {
            $oldCart = $_SESSION['cart'] ?? [];

            if (is_array($oldCart)) {
                foreach ($oldCart as $id) {
                    $productId = filter_var(
                        $id,
                        FILTER_VALIDATE_INT,
                        ['options' => ['min_range' => 1]]
                    );

                    if ($productId !== false) {
                        $this->add((int) $productId);
                    }
                }
            }

            $_SESSION['cart_db_initialized'] = true;
        }

        $this->syncSession();
    }

    /**
     * Cookieからカートを復元
     * 存在しなければ新規作成
     */
    private function loadOrCreateCart(): int
    {
        $token = $_COOKIE[self::COOKIE_NAME] ?? null;

        if (
            is_string($token) &&
            preg_match('/^[a-f0-9]{64}$/D', $token)
        ) {
            $hash = hash('sha256', $token);

            $stmt = $this->pdo->prepare(
                'SELECT cart_id
                 FROM carts
                 WHERE token_hash = ?
                   AND expires_at > NOW()'
            );

            $stmt->execute([$hash]);
            $cartId = $stmt->fetchColumn();

            if ($cartId !== false) {
                $this->refreshCookie($token, (int) $cartId);
                return (int) $cartId;
            }
        }

        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);

        $stmt = $this->pdo->prepare(
            'INSERT INTO carts (
                token_hash,
                expires_at
             ) VALUES (
                ?,
                DATE_ADD(NOW(), INTERVAL 30 DAY)
             )'
        );

        $stmt->execute([$hash]);

        $cartId = (int) $this->pdo->lastInsertId();

        $this->refreshCookie($token, $cartId);

        return $cartId;
    }

    /**
     * CookieとDBの有効期限を更新
     */
    private function refreshCookie(
        string $token,
        int $cartId
    ): void {
        $stmt = $this->pdo->prepare(
            'UPDATE carts
             SET expires_at = DATE_ADD(
                 NOW(), INTERVAL 30 DAY
             )
             WHERE cart_id = ?'
        );

        $stmt->execute([$cartId]);

        $secure = !empty($_SERVER['HTTPS'])
            && $_SERVER['HTTPS'] !== 'off';

        setcookie(self::COOKIE_NAME, $token, [
            'expires'  => time() + self::COOKIE_LIFETIME,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        $_COOKIE[self::COOKIE_NAME] = $token;
    }

    /**
     * カート内の商品ID一覧を取得
     */
    public function ids(): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT product_id
             FROM cart_items
             WHERE cart_id = ?
             ORDER BY created_at ASC, product_id ASC'
        );

        $stmt->execute([$this->cartId]);

        return array_map(
            'intval',
            $stmt->fetchAll(PDO::FETCH_COLUMN)
        );
    }

    /**
     * 最後に発生したエラーメッセージを取得
     */
    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * 商品をカートに追加
     */
    public function add(int $productId): bool
    {
        $this->lastError = null;

        if ($productId <= 0) {
            $this->lastError = '商品IDが不正です。';
            return false;
        }

        $ids = $this->ids();

        if (in_array($productId, $ids, true)) {
            return true;
        }

        if (count($ids) >= self::MAX_ITEMS) {
            $this->lastError =
                'カートに追加できる商品は50件までです。';
            return false;
        }

        $stmt = $this->pdo->prepare(
            'SELECT
                product_id,
                status,
                stock_quantity,
                is_deleted
             FROM products
             WHERE product_id = ?'
        );

        $stmt->execute([$productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            $this->lastError =
                '商品が見つかりませんでした。';
            return false;
        }

        if ((int) $product['is_deleted'] === 1) {
            $this->lastError =
                'この商品は現在取り扱っていません。';
            return false;
        }

        if ($product['status'] !== '販売中') {
            $this->lastError =
                'この商品は現在販売されていません。';
            return false;
        }

        if ((int) $product['stock_quantity'] <= 0) {
            $this->lastError =
                'この商品は売り切れです。';
            return false;
        }

        // 販売中かつ在庫がある商品だけ追加
        $stmt = $this->pdo->prepare(
            "INSERT IGNORE INTO cart_items (
                cart_id,
                product_id
             )
             SELECT ?, product_id
             FROM products
             WHERE product_id = ?
               AND status = '販売中'
               AND stock_quantity > 0
               AND is_deleted = 0"
        );

        $stmt->execute([
            $this->cartId,
            $productId
        ]);

        $this->syncSession();

        if (!in_array($productId, $this->ids(), true)) {
            $this->lastError =
                '商品をカートに追加できませんでした。時間をおいて再度お試しください。';
            return false;
        }

        return true;
    }

    /**
     * 商品をカートから削除
     */
    public function remove(int $productId): void
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM cart_items
             WHERE cart_id = ?
               AND product_id = ?'
        );

        $stmt->execute([
            $this->cartId,
            $productId
        ]);

        $this->syncSession();
    }

    /**
     * カートを空にする
     */
    public function clear(): void
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM cart_items
             WHERE cart_id = ?'
        );

        $stmt->execute([$this->cartId]);

        $this->syncSession();
    }

    /**
     * カート内の商品数を取得
     */
    public function count(): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM cart_items
             WHERE cart_id = ?'
        );

        $stmt->execute([$this->cartId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * DBのカート情報をSessionへ同期
     */
    public function syncSession(): void
    {
        $_SESSION['cart'] = $this->ids();
    }
}
