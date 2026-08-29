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
        'newest'     => 'p.created_at DESC',
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
