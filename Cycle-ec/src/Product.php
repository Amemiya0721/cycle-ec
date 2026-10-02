<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Category.php';

/**
 * Product
 * -----------------------------------------------------------
 * 商品検索処理を集約するクラス。
 *
 * products.php からは Product::search() を呼び出すだけで、
 * SQLやデータベース処理を意識せずに商品情報を取得できる。
 *
 * 対象テーブル：
 *     products
 *
 * products テーブル：
 *     product_id
 *     category_id
 *     name
 *     description
 *     price
 *     tax_rate
 *     status
 *     product_condition
 *     is_deleted
 *     created_at
 *     updated_at
 *
 * 関連テーブル：
 *     categories
 */
class Product
{
    private const AVAILABLE_STATUSES = ['販売中', 'on_sale'];

    /** Convert a stored product upload path to a URL under the configured app base URL. */
    public static function publicImageUrl(?string $storedPath): ?string
    {
        if (!is_string($storedPath)) {
            return null;
        }

        $path = parse_url($storedPath, PHP_URL_PATH);
        if (!is_string($path)
            || !preg_match('#\A/uploads/products/[1-9][0-9]*/[a-f0-9]{32}\.(?:jpg|png|webp)\z#iD', $path)) {
            return null;
        }

        $config = require __DIR__ . '/../config/config.php';
        $baseUrl = trim((string) ($config['app']['base_url'] ?? '/'));
        if ($baseUrl === '/' && getenv('BASE_URL') === false) {
            $baseUrl = self::inferApplicationBasePath();
        }
        if ($baseUrl === '' || $baseUrl === '/') {
            return $path;
        }

        $baseParts = parse_url($baseUrl);
        if ($baseParts === false
            || isset($baseParts['user'])
            || isset($baseParts['pass'])
            || isset($baseParts['query'])
            || isset($baseParts['fragment'])) {
            return null;
        }

        $origin = '';
        if (isset($baseParts['scheme']) || isset($baseParts['host'])) {
            if (!isset($baseParts['scheme'], $baseParts['host'])
                || !in_array(strtolower($baseParts['scheme']), ['http', 'https'], true)) {
                return null;
            }
            $origin = strtolower($baseParts['scheme']) . '://' . $baseParts['host'];
            if (isset($baseParts['port'])) {
                $origin .= ':' . $baseParts['port'];
            }
        }

        $basePath = (string) ($baseParts['path'] ?? '');
        if ($origin === '' && $basePath !== '' && !str_starts_with($basePath, '/')) {
            return null;
        }

        return $origin . rtrim($basePath, '/') . $path;
    }

    private static function inferApplicationBasePath(): string
    {
        $scriptPath = parse_url((string) ($_SERVER['SCRIPT_NAME'] ?? ''), PHP_URL_PATH);
        if (!is_string($scriptPath) || $scriptPath === '') {
            return '/';
        }

        foreach (['/pages/', '/admin/'] as $routePrefix) {
            $position = strpos($scriptPath, $routePrefix);
            if ($position !== false) {
                $prefix = rtrim(substr($scriptPath, 0, $position), '/');
                return $prefix === '' ? '/' : $prefix . '/';
            }
        }

        if (basename($scriptPath) === 'index.php') {
            $lastSlash = strrpos($scriptPath, '/');
            if ($lastSlash === false || $lastSlash === 0) {
                return '/';
            }
            return substr($scriptPath, 0, $lastSlash + 1);
        }

        return '/';
    }

    public static function isAvailableStatus(string $status): bool
    {
        return in_array($status, self::AVAILABLE_STATUSES, true);
    }

    private static function assertAvailableStatusHasStock(array $data): void
    {
        if (self::isAvailableStatus((string) ($data['status'] ?? ''))
            && (int) ($data['stock_quantity'] ?? 0) <= 0) {
            throw new InvalidArgumentException('販売中の商品には在庫を1点以上設定してください。');
        }
    }

    /**
     * 管理画面のテキストエリア入力（1行1項目、"キー:値"形式）をスペック配列に変換する。
     * 例: "モデル:CAAD13\n年式:2024" → [['spec_key'=>'モデル','spec_value'=>'CAAD13'], ...]
     * create.php / edit.php の両方で使うため重複実装を避けてここに集約する。
     *
     * @return array<int, array{spec_key:string, spec_value:string}>
     */
    public static function parseSpecLines(string $raw): array
    {
        $specs = [];
        foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
            $line = trim($line);
            if ($line === '' || !str_contains($line, ':')) {
                continue;
            }
            [$key, $value] = explode(':', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if ($key === '' || $value === '') {
                continue;
            }
            $specs[] = ['spec_key' => $key, 'spec_value' => $value];
        }
        return $specs;
    }

    /**
     * 管理画面のテキストエリア入力（1行1項目）を付属品配列に変換する。
     *
     * @return array<int, string>
     */
    public static function parseAccessoryLines(string $raw): array
    {
        $accessories = [];
        foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
            $line = trim($line);
            if ($line !== '') {
                $accessories[] = $line;
            }
        }
        return $accessories;
    }

    /**
     * スペック配列を編集フォーム表示用のテキストに変換する（parseSpecLinesの逆変換）。
     */
    public static function specsToLines(array $specs): string
    {
        $lines = [];
        foreach ($specs as $spec) {
            $lines[] = $spec['spec_key'] . ':' . $spec['spec_value'];
        }
        return implode("\n", $lines);
    }

    /**
     * 付属品配列を編集フォーム表示用のテキストに変換する。
     */
    public static function accessoriesToLines(array $accessories): string
    {
        $lines = [];
        foreach ($accessories as $accessory) {
            $lines[] = $accessory['content'];
        }
        return implode("\n", $lines);
    }

    public static function findById(int $productId): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT
                p.product_id,
                p.category_id,
                p.manufacturer,
                p.name,
                p.description,
                p.price,
                p.tax_rate,
                p.status,
                p.product_condition,
                p.stock_quantity,
                p.staff_comment,
                p.is_deleted,
                p.is_recommended,
                p.created_at,
                p.updated_at,
                c.name AS category_name
             FROM products p
             INNER JOIN categories c ON c.category_id = p.category_id
             WHERE p.product_id = :product_id
               AND p.is_deleted = 0
             LIMIT 1'
        );
        $stmt->execute([':product_id' => $productId]);
        $product = $stmt->fetch();

        if ($product === false) {
            return null;
        }

        // メイン画像（従来通り。image_type='main' のみ、既存データは全てこちらに分類される）
        $imageStmt = $pdo->prepare(
            'SELECT image_id, product_id, image_url, image_type, sort_order, created_at
             FROM product_images
             WHERE product_id = :product_id
               AND image_type = :image_type
             ORDER BY sort_order ASC, image_id ASC'
        );
        $imageStmt->execute([':product_id' => $productId, ':image_type' => 'main']);
        $product['images'] = $imageStmt->fetchAll();

        // 傷・使用感の写真（商品状態タブ用）
        $conditionImageStmt = $pdo->prepare(
            'SELECT image_id, product_id, image_url, image_type, sort_order, created_at
             FROM product_images
             WHERE product_id = :product_id
               AND image_type = :image_type
             ORDER BY sort_order ASC, image_id ASC'
        );
        $conditionImageStmt->execute([':product_id' => $productId, ':image_type' => 'condition']);
        $product['condition_images'] = $conditionImageStmt->fetchAll();

        $product['specs'] = self::specs($productId);
        $product['accessories'] = self::accessories($productId);

        return $product;
    }

    /**
     * 商品スペック一覧（モデル・年式・サイズ 等のkey-value）を取得する。
     *
     * @return array<int, array{spec_key:string, spec_value:string}>
     */
    public static function specs(int $productId): array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT spec_key, spec_value
             FROM product_specs
             WHERE product_id = :product_id
             ORDER BY sort_order ASC, spec_id ASC'
        );
        $stmt->execute([':product_id' => $productId]);
        return $stmt->fetchAll();
    }

    /**
     * 付属品一覧を取得する。
     *
     * @return array<int, array{content:string}>
     */
    public static function accessories(int $productId): array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT content
             FROM product_accessories
             WHERE product_id = :product_id
             ORDER BY sort_order ASC, accessory_id ASC'
        );
        $stmt->execute([':product_id' => $productId]);
        return $stmt->fetchAll();
    }

    /**
     * 関連商品を取得する（同カテゴリの他商品。新しい順）。
     *
     * 一覧検索用の search() は条件構築が複雑なため、
     * 関連商品専用のシンプルなクエリとして分離する
     * （search() を流用すると余計な検索条件・ページングを
     *   持ち込むことになり、かえって複雑になるため）。
     *
     * @return array<int, array<string, mixed>>
     */
    public static function relatedByCategory(int $productId, int $categoryId, int $limit = 8): array
    {
        $limit = max(1, min($limit, 20));
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT p.product_id, p.name, p.price, c.name AS category_name,
                    pi.image_url
             FROM products p
             INNER JOIN categories c ON c.category_id = p.category_id
             LEFT JOIN product_images pi
                 ON pi.product_id = p.product_id
                AND pi.image_type = "main"
                AND pi.sort_order = 1
             WHERE p.is_deleted = 0
               AND p.category_id = :category_id
               AND p.product_id != :product_id
             ORDER BY p.created_at DESC, p.product_id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
        $stmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function create(array $data): int
    {
        self::assertAvailableStatusHasStock($data);
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO products
                (category_id, manufacturer, name, description, price, tax_rate, status,
                 product_condition, stock_quantity, staff_comment, is_deleted, is_recommended)
             VALUES
                (:category_id, :manufacturer, :name, :description, :price, :tax_rate, :status,
                 :product_condition, :stock_quantity, :staff_comment, 0, :is_recommended)'
        );
        $stmt->execute([
            ':category_id' => $data['category_id'],
            ':manufacturer' => $data['manufacturer'] ?? null,
            ':name' => $data['name'],
            ':description' => $data['description'] ?? null,
            ':price' => $data['price'],
            ':tax_rate' => $data['tax_rate'],
            ':status' => $data['status'],
            ':product_condition' => $data['product_condition'],
            ':stock_quantity' => (int) ($data['stock_quantity'] ?? 0),
            ':staff_comment' => $data['staff_comment'] ?? null,
            ':is_recommended' => !empty($data['is_recommended']) ? 1 : 0,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function update(int $productId, array $data): bool
    {
        self::assertAvailableStatusHasStock($data);
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'UPDATE products
             SET category_id = :category_id,
                 manufacturer = :manufacturer,
                 name = :name,
                 description = :description,
                 price = :price,
                 tax_rate = :tax_rate,
                 status = :status,
                 product_condition = :product_condition,
                 stock_quantity = :stock_quantity,
                 staff_comment = :staff_comment,
                 is_recommended = :is_recommended
             WHERE product_id = :product_id
               AND is_deleted = 0'
        );

        return $stmt->execute([
            ':product_id' => $productId,
            ':category_id' => $data['category_id'],
            ':manufacturer' => $data['manufacturer'] ?? null,
            ':name' => $data['name'],
            ':description' => $data['description'] ?? null,
            ':price' => $data['price'],
            ':tax_rate' => $data['tax_rate'],
            ':status' => $data['status'],
            ':product_condition' => $data['product_condition'],
            ':stock_quantity' => (int) ($data['stock_quantity'] ?? 0),
            ':staff_comment' => $data['staff_comment'] ?? null,
            ':is_recommended' => !empty($data['is_recommended']) ? 1 : 0,
        ]);
    }

    /**
     * スペックを全件洗い替えする（既存分は削除してから登録し直す）。
     *
     * @param array<int, array{spec_key:string, spec_value:string}> $specs
     */
    public static function replaceSpecs(int $productId, array $specs, ?PDO $pdo = null): void
    {
        $pdo ??= Database::getConnection();

        $deleteStmt = $pdo->prepare('DELETE FROM product_specs WHERE product_id = :product_id');
        $deleteStmt->execute([':product_id' => $productId]);

        if ($specs === []) {
            return;
        }

        $insertStmt = $pdo->prepare(
            'INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
             VALUES (:product_id, :spec_key, :spec_value, :sort_order)'
        );
        foreach (array_values($specs) as $index => $spec) {
            $insertStmt->execute([
                ':product_id' => $productId,
                ':spec_key' => $spec['spec_key'],
                ':spec_value' => $spec['spec_value'],
                ':sort_order' => $index,
            ]);
        }
    }

    /**
     * 付属品を全件洗い替えする。
     *
     * @param array<int, string> $accessories
     */
    public static function replaceAccessories(int $productId, array $accessories, ?PDO $pdo = null): void
    {
        $pdo ??= Database::getConnection();

        $deleteStmt = $pdo->prepare('DELETE FROM product_accessories WHERE product_id = :product_id');
        $deleteStmt->execute([':product_id' => $productId]);

        if ($accessories === []) {
            return;
        }

        $insertStmt = $pdo->prepare(
            'INSERT INTO product_accessories (product_id, content, sort_order)
             VALUES (:product_id, :content, :sort_order)'
        );
        foreach (array_values($accessories) as $index => $content) {
            $insertStmt->execute([
                ':product_id' => $productId,
                ':content' => $content,
                ':sort_order' => $index,
            ]);
        }
    }

    /**
     * 画像を1件登録する（メイン画像 / 傷・使用感写真）。
     *
     * sort_order は image_type ごとに独立して管理する
     * （メイン画像1枚目と傷写真1枚目が同じ sort_order=1 でも問題ない）。
     */
    public static function addImage(int $productId, string $imageUrl, string $imageType, int $sortOrder, ?PDO $pdo = null): int
    {
        $pdo ??= Database::getConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO product_images (product_id, image_url, image_type, sort_order)
             VALUES (:product_id, :image_url, :image_type, :sort_order)'
        );
        $stmt->execute([
            ':product_id' => $productId,
            ':image_url' => $imageUrl,
            ':image_type' => $imageType,
            ':sort_order' => $sortOrder,
        ]);
        return (int) $pdo->lastInsertId();
    }

    /**
     * 画像を1件削除する（管理画面からの個別削除用）。
     */
    public static function deleteImage(int $imageId, int $productId): bool
    {
        $stmt = Database::getConnection()->prepare(
            'DELETE FROM product_images WHERE image_id = :image_id AND product_id = :product_id'
        );
        return $stmt->execute([':image_id' => $imageId, ':product_id' => $productId]);
    }

        public static function recommended(int $limit = 5): array
        {
                $limit = max(1, min($limit, 20));
                $pdo = Database::getConnection();
                $stmt = $pdo->prepare(
                        'SELECT p.product_id, p.name, p.price, c.name AS category_name,
                                        pi.image_url
                         FROM products p
                         INNER JOIN categories c ON c.category_id = p.category_id
                         LEFT JOIN product_images pi
                             ON pi.product_id = p.product_id AND pi.image_type = "main" AND pi.sort_order = 1
                         WHERE p.is_deleted = 0
                             AND p.is_recommended = 1
                             AND p.status NOT IN (\'売切れ\', \'SOLD\')
                             AND p.stock_quantity > 0
                         ORDER BY p.updated_at DESC, p.product_id DESC
                         LIMIT :limit'
                );
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->execute();
                return $stmt->fetchAll();
        }

        public static function priceReduced(int $limit = 5): array
        {
                $limit = max(1, min($limit, 20));
                $pdo = Database::getConnection();
                $stmt = $pdo->prepare(
                        'SELECT p.product_id, p.name, p.price, c.name AS category_name,
                                        pi.image_url, ph.price AS old_price
                         FROM products p
                         INNER JOIN categories c ON c.category_id = p.category_id
                         LEFT JOIN product_images pi
                             ON pi.product_id = p.product_id AND pi.image_type = "main" AND pi.sort_order = 1
                         INNER JOIN product_price_history ph
                             ON ph.product_id = p.product_id
                            AND ph.created_at = (
                                    SELECT MAX(ph2.created_at)
                                    FROM product_price_history ph2
                                    WHERE ph2.product_id = p.product_id
                            )
                         WHERE p.is_deleted = 0
                             AND ph.price > p.price
                             AND p.status NOT IN (\'売切れ\', \'SOLD\')
                             AND p.stock_quantity > 0
                         ORDER BY (ph.price - p.price) DESC, p.updated_at DESC
                         LIMIT :limit'
                );
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->execute();
                return $stmt->fetchAll();
        }

    public static function delete(int $productId): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'UPDATE products
             SET is_deleted = 1
             WHERE product_id = :product_id
               AND is_deleted = 0'
        );

        return $stmt->execute([':product_id' => $productId]);
    }

    public static function priceHistory(int $productId): array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT price_history_id, product_id, price, tax_rate, created_at
             FROM product_price_history
             WHERE product_id = :product_id
             ORDER BY created_at DESC, price_history_id DESC'
        );
        $stmt->execute([':product_id' => $productId]);
        return $stmt->fetchAll();
    }

    public static function allForDiscountManagement(): array
    {
        $stmt = Database::getConnection()->query(
            'SELECT p.product_id, p.name, p.price, p.status,
                    c.name AS category_name
             FROM products p
             INNER JOIN categories c ON c.category_id = p.category_id
             WHERE p.is_deleted = 0
             ORDER BY p.updated_at DESC, p.product_id DESC'
        );
        return $stmt->fetchAll();
    }

    public static function updatePrice(int $productId, string $newPrice): bool
    {
        $pdo = Database::getConnection();
        try {
            $pdo->beginTransaction();
            $currentStmt = $pdo->prepare(
                'SELECT price, tax_rate FROM products
                 WHERE product_id = :product_id AND is_deleted = 0 FOR UPDATE'
            );
            $currentStmt->execute([':product_id' => $productId]);
            $current = $currentStmt->fetch();
            if ($current === false) {
                throw new RuntimeException('商品が見つかりません。');
            }

            if ((float) $current['price'] !== (float) $newPrice) {
                $historyStmt = $pdo->prepare(
                    'INSERT INTO product_price_history (product_id, price, tax_rate)
                     VALUES (:product_id, :price, :tax_rate)'
                );
                $historyStmt->execute([
                    ':product_id' => $productId,
                    ':price' => $current['price'],
                    ':tax_rate' => $current['tax_rate'],
                ]);
                $updateStmt = $pdo->prepare(
                    'UPDATE products SET price = :price WHERE product_id = :product_id'
                );
                $updateStmt->execute([
                    ':product_id' => $productId,
                    ':price' => $newPrice,
                ]);
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

    /**
     * キーワード検索の対象カラム。
     *
     * products：
     *     name
     *     description
     *     status
     *     product_condition
     *
     * categories：
     *     name
     */
    private const KEYWORD_COLUMNS = [
        'p.name',
        'p.description',
        'p.status',
        'p.product_condition',
        'c.name',
    ];

    /**
     * 許可するソート指定。
     *
     * 外部入力を直接 ORDER BY に渡さない。
     */
    private const SORT_MAP = [
        'condition'  => "CASE p.product_condition WHEN 'S' THEN 1 WHEN 'A' THEN 2 WHEN 'B' THEN 3 WHEN 'C' THEN 4 WHEN 'JUNK' THEN 5 WHEN '新品' THEN 1 WHEN '美品' THEN 2 WHEN '中古' THEN 3 WHEN 'ジャンク' THEN 5 ELSE 6 END ASC, p.updated_at DESC",
        'newest'     => 'p.created_at DESC',
        'oldest'     => 'p.created_at ASC',
        'price_asc'  => 'p.price ASC',
        'price_desc' => 'p.price DESC',
    ];

    /**
     * デフォルトソート。
     */
    private const DEFAULT_SORT = 'newest';

    /**
     * 1ページあたりの商品数。
     */
    private const DEFAULT_PER_PAGE = 12;

    /**
     * 商品を検索して一覧を取得する。
     *
     * 対応条件：
     *
     * category
     *     カテゴリのslug / 名前 / ID
     *
     * keyword
     *     商品名、説明、ステータス、商品状態、
     *     カテゴリ名を対象としたLIKE検索
     *
     * sort
     *     newest
     *     price_asc
     *     price_desc
     *
     * page
     *     1始まりのページ番号
     *
     * @param array<string, mixed> $criteria
     *
     * @return array{
     *     items: array<int, array<string, mixed>>,
     *     total: int,
     *     page: int,
     *     per_page: int,
     *     total_pages: int
     * }
     */
    public static function search(array $criteria): array
    {
        $pdo = Database::getConnection();

        /*
         * WHERE句とパラメータを作成
         */
        [
            $whereSql,
            $params,
            $categoryNotFound
        ] = self::buildWhereConditions($criteria);

        /*
         * 存在しないカテゴリの場合、
         * SQLを実行せず空の結果を返す。
         */
        if ($categoryNotFound) {
            return self::emptyResult($criteria);
        }

        /*
         * ページング
         */
        $perPage = self::DEFAULT_PER_PAGE;

        $page = max(
            1,
            (int) ($criteria['page'] ?? 1)
        );

        $offset = ($page - 1) * $perPage;

        /*
         * ソート
         */
        $sortKey = is_string($criteria['sort'] ?? null)
            ? $criteria['sort']
            : self::DEFAULT_SORT;

        $orderBy = self::SORT_MAP[$sortKey]
            ?? self::SORT_MAP[self::DEFAULT_SORT];

        /*
         * =======================================================
         * 件数取得
         * =======================================================
         */
        $countSql = "
            SELECT
                COUNT(*) AS total
            FROM products p
            LEFT JOIN categories c
                ON p.category_id = c.category_id
            WHERE {$whereSql}
        ";

        $countStmt = $pdo->prepare($countSql);

        $countStmt->execute($params);

        $total = (int) $countStmt->fetch()['total'];

        /*
         * =======================================================
         * 商品一覧取得
         * =======================================================
         */
        $listSql = "
    SELECT
        p.product_id,
        p.category_id,
        p.manufacturer,
        p.name,
        p.description,
        p.price,
        p.tax_rate,
        p.status,
        p.product_condition,
        p.is_deleted,
        p.created_at,
        p.updated_at,
        c.name AS category_name,
        pi.image_url
            FROM products p
                LEFT JOIN categories c
                ON p.category_id = c.category_id
                LEFT JOIN product_images pi
                ON p.product_id = pi.product_id
                AND pi.image_type = 'main'
                AND pi.sort_order = 1
        WHERE {$whereSql}
        ORDER BY {$orderBy}
        LIMIT :limit
        OFFSET :offset
";

        $listStmt = $pdo->prepare($listSql);

        /*
         * 検索条件をバインド
         */
        foreach ($params as $key => $value) {
            $listStmt->bindValue($key, $value);
        }

        /*
         * LIMIT / OFFSET は整数としてバインド
         */
        $listStmt->bindValue(
            ':limit',
            $perPage,
            PDO::PARAM_INT
        );

        $listStmt->bindValue(
            ':offset',
            $offset,
            PDO::PARAM_INT
        );

        $listStmt->execute();

        $items = $listStmt->fetchAll(PDO::FETCH_ASSOC);

        /*
         * =======================================================
         * 検索結果
         * =======================================================
         */
        return [
            'items'       => $items,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => (int) max(
                1,
                ceil($total / $perPage)
            ),
        ];
    }

    /**
     * 検索条件からWHERE句を作成する。
     *
     * @param array<string, mixed> $criteria
     *
     * @return array{
     *     0: string,
     *     1: array<string, mixed>,
     *     2: bool
     * }
     */
    private static function buildWhereConditions(
        array $criteria
    ): array {
        /*
         * 論理削除されていない商品だけを取得。
         */
        $conditions = [
            'p.is_deleted = 0'
        ];

        if (($criteria['exclude_sold_out'] ?? false) === true) {
            $conditions[] = "p.status NOT IN ('売切れ', 'SOLD')";
            $conditions[] = 'p.stock_quantity > 0';
        }

        $params = [];

        $categoryNotFound = false;

        /*
         * =======================================================
         * category
         * =======================================================
         */
        $category = $criteria['category'] ?? null;

        if (
            is_string($category)
            && trim($category) !== ''
        ) {
            $categoryId = Category::resolveCategoryId(
                $category
            );

            if ($categoryId === null) {
                /*
                 * 指定カテゴリが存在しない。
                 */
                $categoryNotFound = true;
            } else {
                $conditions[] =
                    'p.category_id = :category_id';

                $params[':category_id'] = $categoryId;
            }
        }

        $status = $criteria['status'] ?? null;
        if (is_string($status) && trim($status) !== '') {
            $conditions[] = 'p.status = :status';
            $params[':status'] = trim($status);
        }

        $selectedConditions = $criteria['conditions'] ?? [];
        if (is_array($selectedConditions)) {
            $selectedConditions = array_values(array_filter(
                $selectedConditions,
                static fn ($value): bool => is_string($value)
                    && in_array($value, ['S', 'A', 'B', 'C', 'JUNK'], true)
            ));
            if ($selectedConditions) {
                $placeholders = [];
                foreach ($selectedConditions as $index => $conditionValue) {
                    $placeholder = ':condition_' . $index;
                    $placeholders[] = $placeholder;
                    $params[$placeholder] = $conditionValue;
                }
                $conditions[] = 'p.product_condition IN (' . implode(', ', $placeholders) . ')';
            }
        }

        /*
         * =======================================================
         * keyword
         * =======================================================
         *
         * 以下を対象にLIKE検索：
         *
         * products.name
         * products.description
         * products.status
         * products.product_condition
         * categories.name
         */
        $keyword = $criteria['keyword'] ?? null;

        if (
            is_string($keyword)
            && trim($keyword) !== ''
        ) {
            $keyword = trim($keyword);

            $likeValue =
                '%'
                . self::escapeLikeValue($keyword)
                . '%';

            $likeClauses = [];

            foreach (
                self::KEYWORD_COLUMNS
                as $index => $column
            ) {
                $placeholder = ":kw{$index}";

                $likeClauses[] =
                    "{$column} LIKE {$placeholder}";

                $params[$placeholder] = $likeValue;
            }

            $conditions[] =
                '('
                . implode(' OR ', $likeClauses)
                . ')';
        }

        return [
            implode(' AND ', $conditions),
            $params,
            $categoryNotFound,
        ];
    }

    /**
     * 検索結果が0件の場合。
     *
     * @param array<string, mixed> $criteria
     */
    private static function emptyResult(
        array $criteria
    ): array {
        $perPage = self::DEFAULT_PER_PAGE;

        $page = max(
            1,
            (int) ($criteria['page'] ?? 1)
        );

        return [
            'items'       => [],
            'total'       => 0,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => 1,
        ];
    }

    /**
     * LIKE検索用のエスケープ処理。
     *
     * % と _ をワイルドカードとして扱わない。
     */
    private static function escapeLikeValue(
        string $value
    ): string {
        return addcslashes(
            $value,
            '\\%_'
        );
    }
}
