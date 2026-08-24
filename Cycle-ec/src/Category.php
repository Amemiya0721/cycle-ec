<?php
declare(strict_types=1);

require_once __DIR__ . '/Database.php';

/**
 * Category
 * -----------------------------------------------------------
 * カテゴリの検索・解決処理を集約する。
 *
 * 現行ER図の CATEGORIES は (category_id, name) のみを持ち、
 * URL用のslugカラムは存在しない。
 * そのため、URLパラメータ category（例: "road-bike"）を
 * category_id へ解決する処理をこのクラスに閉じ込め、
 * 将来 slug カラムがDBへ追加された場合も
 * products.php 側のコードを変更せずに済むようにしている。
 */
class Category
{
    /**
     * URLパラメータの category 値を category_id に解決する。
     *
     * 解決の優先順位：
     *   1. 数値のみ → category_id とみなす
     *   2. config/category_slugs.php のマップに一致 → マップ先の名前で検索
     *   3. 上記以外 → 入力値そのものをカテゴリ名として検索（日本語名の直接指定に対応）
     *
     * @return int|null 該当カテゴリが存在しない場合は null
     */
    public static function resolveCategoryId(string $categoryParam): ?int
    {
        $categoryParam = trim($categoryParam);
        if ($categoryParam === '') {
            return null;
        }

        // 1. 数値指定（category_idの直接指定）
        if (ctype_digit($categoryParam)) {
            return self::findIdByColumn('category_id', (int) $categoryParam);
        }

        // 2. スラッグマップ経由での名前解決
        $slugMap = self::slugMap();
        $categoryName = $slugMap[$categoryParam] ?? $categoryParam;

        // 3. カテゴリ名で検索（マップに無ければ入力値をそのまま名前として扱う）
        return self::findIdByColumn('name', $categoryName);
    }

    /**
     * カテゴリ一覧を取得する（検索フォームのプルダウン等に使用）。
     *
     * @return array<int, array{category_id:int, name:string}>
     */
    public static function all(): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query('SELECT category_id, name FROM CATEGORIES ORDER BY category_id ASC');
        return $stmt->fetchAll();
    }

    /** @return array<string, string> slug => カテゴリ名 */
    private static function slugMap(): array
    {
        static $map = null;
        if ($map === null) {
            $map = require __DIR__ . '/../config/category_slugs.php';
        }
        return $map;
    }

    private static function findIdByColumn(string $column, int|string $value): ?int
    {
        // $column はこのクラス内の固定値のみを渡す想定（外部入力を直接渡さない）
        $allowedColumns = ['category_id', 'name'];
        if (!in_array($column, $allowedColumns, true)) {
            throw new InvalidArgumentException('Invalid column for category lookup.');
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT category_id FROM CATEGORIES WHERE {$column} = :value LIMIT 1");
        $stmt->execute([':value' => $value]);
        $row = $stmt->fetch();

        return $row ? (int) $row['category_id'] : null;
    }
}
