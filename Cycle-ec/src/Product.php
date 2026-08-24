<?php
declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Category.php';

/**
 * Product
 * -----------------------------------------------------------
 * 商品検索処理を集約するクラス。
 * products.php からはこのクラスの search() のみを呼び出し、
 * SQLはこのファイル内に閉じ込める。
 *
 * 呼び出し例：
 *   Product::search([
 *       'category' => 'road-bike',
 *       'keyword'  => 'shimano',
 *   ]);
 *
 * 設計方針：
 *   ・URL仕様（category, keyword, ...）とDB内部の検索方式（LIKE等）を分離する。
 *     将来 LIKE → FULLTEXT 等へ変更する場合も、呼び出し側（products.php）や
 *     URL仕様は変更せず、このクラスの内部実装のみを差し替えればよい。
 *   ・検索条件は「まとめて扱える構造」とし、category / keyword を個別実装で
 *     分岐させない。新しい条件（min_price, sort, page 等）を追加する際は、
 *     buildWhereConditions() 内にフィルタを1つ追加するだけで拡張できる。
 */
class Product
{
    /**
     * キーワード検索の対象カラム。
     * 将来 PRODUCTS へ brand / model / model_number 等が追加された場合は、
     * ここに 'p.brand' のように追記するだけで検索対象を拡張できる。
     */
    private const KEYWORD_COLUMNS = [
        'p.name',
        'p.description',
        'p.status',
        'p.condition',
        'c.name', // CATEGORIES.name（カテゴリ名もキーワード検索対象に含める）
    ];

    /** 許可するソート指定（SQLインジェクション対策として直接ORDER BYへ外部入力を渡さない） */
    private const SORT_MAP = [
        'newest'     => 'p.created_at DESC',
        'price_asc'  => 'p.price ASC',
        'price_desc' => 'p.price DESC',
    ];

    private const DEFAULT_SORT = 'newest';
    private const DEFAULT_PER_PAGE = 12;

    /**
     * 検索条件に応じて商品一覧を取得する。
     *
     * 対応済み条件：
     *   - category : カテゴリのslug／名前／IDのいずれか（Category::resolveCategoryIdで解決）
     *   - keyword  : 商品情報全体（KEYWORD_COLUMNS）を対象としたLIKE検索
     *   - sort     : SORT_MAPで許可されたキーのみ
     *   - page     : 1始まりのページ番号
     *
     * 将来追加予定（現時点は未実装。呼び出し側の$criteriaにキーを渡しても無視される）：
     *   - min_price / max_price : p.price の範囲フィルタ
     *   - condition             : p.condition の完全一致フィルタ
     *   - status                : p.status の完全一致フィルタ
     * これらは buildWhereConditions() 内に個別ブロックを追加するだけで対応可能。
     *
     * @param array<string, mixed> $criteria
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, total_pages: int}
     */
    public static function search(array $criteria): array
    {
        $pdo = Database::getConnection();

        [$whereSql, $params, $categoryNotFound] = self::buildWhereConditions($criteria);

        // 存在しないカテゴリが指定された場合は、クエリを投げるまでもなく結果ゼロを返す
        if ($categoryNotFound) {
            return self::emptyResult($criteria);
        }

        $perPage = self::DEFAULT_PER_PAGE;
        $page = max(1, (int) ($criteria['page'] ?? 1));
        $offset = ($page - 1) * $perPage;

        $sortKey = is_string($criteria['sort'] ?? null) ? $criteria['sort'] : self::DEFAULT_SORT;
        $orderBy = self::SORT_MAP[$sortKey] ?? self::SORT_MAP[self::DEFAULT_SORT];

        // ---- 件数取得 ----
        $countSql = "
            SELECT COUNT(*) AS total
            FROM PRODUCTS p
            LEFT JOIN CATEGORIES c ON p.category_id = c.category_id
            WHERE {$whereSql}
        ";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetch()['total'];

        // ---- 一覧取得 ----
        $listSql = "
            SELECT
                p.product_id,
                p.category_id,
                p.name,
                p.description,
                p.price,
                p.tax_rate,
                p.status,
                p.condition,
                p.created_at,
                c.name AS category_name
            FROM PRODUCTS p
            LEFT JOIN CATEGORIES c ON p.category_id = c.category_id
            WHERE {$whereSql}
            ORDER BY {$orderBy}
            LIMIT :limit OFFSET :offset
        ";
        $listStmt = $pdo->prepare($listSql);
        foreach ($params as $key => $value) {
            $listStmt->bindValue($key, $value);
        }
        $listStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $listStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $listStmt->execute();
        $items = $listStmt->fetchAll();

        return [
            'items'       => $items,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => (int) max(1, ceil($total / $perPage)),
        ];
    }

    /**
     * WHERE句と対応するプレースホルダ値を組み立てる。
     * 新しい検索条件を追加する場合は、この関数内にブロックを1つ増やすだけでよい。
     *
     * @return array{0: string, 1: array<string, mixed>, 2: bool} [WHERE句, パラメータ, カテゴリ未存在フラグ]
     */
    private static function buildWhereConditions(array $criteria): array
    {
        $conditions = ['p.is_deleted = 0'];
        $params = [];
        $categoryNotFound = false;

        // ---- category ----
        $category = $criteria['category'] ?? null;
        if (is_string($category) && trim($category) !== '') {
            $categoryId = Category::resolveCategoryId($category);
            if ($categoryId === null) {
                $categoryNotFound = true;
            } else {
                $conditions[] = 'p.category_id = :category_id';
                $params[':category_id'] = $categoryId;
            }
        }

        // ---- keyword（商品情報全体を対象にしたLIKE検索） ----
        $keyword = $criteria['keyword'] ?? null;
        if (is_string($keyword) && trim($keyword) !== '') {
            $keyword = trim($keyword);
            $likeValue = '%' . self::escapeLikeValue($keyword) . '%';

            $likeClauses = [];
            foreach (self::KEYWORD_COLUMNS as $index => $column) {
                $placeholder = ":kw{$index}";
                $likeClauses[] = "{$column} LIKE {$placeholder}";
                $params[$placeholder] = $likeValue;
            }
            $conditions[] = '(' . implode(' OR ', $likeClauses) . ')';
        }

        // ---- 将来の拡張ポイント（例） ----
        // if (isset($criteria['min_price'])) {
        //     $conditions[] = 'p.price >= :min_price';
        //     $params[':min_price'] = (float) $criteria['min_price'];
        // }
        // if (isset($criteria['max_price'])) {
        //     $conditions[] = 'p.price <= :max_price';
        //     $params[':max_price'] = (float) $criteria['max_price'];
        // }
        // if (isset($criteria['condition'])) {
        //     $conditions[] = 'p.condition = :condition';
        //     $params[':condition'] = (string) $criteria['condition'];
        // }
        // if (isset($criteria['status'])) {
        //     $conditions[] = 'p.status = :status';
        //     $params[':status'] = (string) $criteria['status'];
        // }

        return [implode(' AND ', $conditions), $params, $categoryNotFound];
    }

    private static function emptyResult(array $criteria): array
    {
        $perPage = self::DEFAULT_PER_PAGE;
        $page = max(1, (int) ($criteria['page'] ?? 1));

        return [
            'items'       => [],
            'total'       => 0,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => 1,
        ];
    }

    /**
     * LIKE検索用に % と _ をエスケープする。
     * MySQLはデフォルトでバックスラッシュをLIKEのエスケープ文字として扱うため、
     * 明示的な ESCAPE 句を付けずとも安全に機能する。
     */
    private static function escapeLikeValue(string $value): string
    {
        return addcslashes($value, '\\%_');
    }
}
