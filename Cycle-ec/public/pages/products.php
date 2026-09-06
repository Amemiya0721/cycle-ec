<?php

$config = require __DIR__ . '/../../config/config.php';

$baseUrl = $config['app']['base_url'];

$pageCss = 'products.css';

require_once __DIR__ . '/../../src/Product.php';
require_once __DIR__ . '/../../src/Category.php';
$productConditions = require __DIR__ . '/../../config/product_conditions.php';


/**
 * products.php
 * -----------------------------------------------------------
 * 商品一覧画面。
 *
 * 責務：
 *   1. GETパラメータの取得
 *   2. 検索条件の整理
 *   3. Product::search() による商品取得
 *   4. Category::all() によるカテゴリ取得
 *   5. HTML表示
 *
 * SQLはこのファイルには記述しない。
 */


/*
|--------------------------------------------------------------------------
| 1. GETパラメータ取得
|--------------------------------------------------------------------------
*/

$category = isset($_GET['category']) && is_string($_GET['category'])
    ? trim($_GET['category'])
    : null;

$keyword = isset($_GET['keyword']) && is_string($_GET['keyword'])
    ? trim($_GET['keyword'])
    : null;

$sort = isset($_GET['sort']) && is_string($_GET['sort'])
    ? trim($_GET['sort'])
    : null;

$selectedConditions = [];
if (isset($_GET['condition'])) {
    $conditionInput = is_array($_GET['condition'])
        ? $_GET['condition']
        : [$_GET['condition']];
    foreach ($conditionInput as $conditionValue) {
        if (is_string($conditionValue) && array_key_exists($conditionValue, $productConditions)) {
            $selectedConditions[] = $conditionValue;
        }
    }
    $selectedConditions = array_values(array_unique($selectedConditions));
}

$page = isset($_GET['page'])
    ? (int) $_GET['page']
    : 1;


/*
|--------------------------------------------------------------------------
| 2. 検索条件を整理
|--------------------------------------------------------------------------
*/

$criteria = [];

if ($category !== null && $category !== '') {
    $criteria['category'] = $category;
}

if ($keyword !== null && $keyword !== '') {
    $criteria['keyword'] = $keyword;
}

if ($sort !== null && $sort !== '') {
    $criteria['sort'] = $sort;
}

if ($selectedConditions) {
    $criteria['conditions'] = $selectedConditions;
}

$criteria['page'] = max(1, $page);


/*
|--------------------------------------------------------------------------
| 3. データ取得
|--------------------------------------------------------------------------
*/

$result = Product::search($criteria);

$categories = Category::all();


/*
|--------------------------------------------------------------------------
| 4. 表示用ヘルパー
|--------------------------------------------------------------------------
*/

function scalarValue(mixed $value): string
{
    return is_scalar($value) ? (string) $value : '';
}


function h(mixed $value): string
{
    return htmlspecialchars(
        scalarValue($value),
        ENT_QUOTES,
        'UTF-8'
    );
}


function buildQuery(array $overrides = []): string
{
    $query = array_merge(
        $_GET,
        $overrides
    );

    $query = array_filter(
        $query,
        static fn ($value): bool =>
            $value !== null && $value !== ''
    );

    if (
        isset($query['page'])
        && (int) $query['page'] === 1
    ) {
        unset($query['page']);
    }

    return 'products.php'
        . (
            $query
                ? '?' . http_build_query($query)
                : ''
        );
}


function isSelectedCategory(
    string $currentCategory,
    array $category
): bool {

    if ($currentCategory === '') {
        return false;
    }

    return (
        $currentCategory
        === scalarValue($category['category_id'] ?? null)
    )
    || (
        $currentCategory
        === scalarValue($category['name'] ?? null)
    );
}


function currentSort(?string $sort): string
{
    return match ($sort) {
        'condition'  => 'condition',
        'price_asc'  => 'price_asc',
        'price_desc' => 'price_desc',
        'oldest'     => 'oldest',
        default      => 'newest',
    };
}


$currentSort = currentSort($sort);


/*
|--------------------------------------------------------------------------
| 5. 共通レイアウト
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/nav.php';

?>


<main>

    <section class="products-section">

        <div class="container">


            <!--
            ================================================================
            検索フォーム
            ================================================================
            -->

            <form
                class="search-form-panel"
                method="get"
                action="products.php"
                id="productSearchForm"
            >

                <!-- カテゴリ -->

                <div class="search-field">

                    <label for="category">
                        カテゴリ
                    </label>

                    <select
                        name="category"
                        id="category"
                    >

                        <option value="">
                            すべてのカテゴリ
                        </option>

                        <?php foreach ($categories as $cat): ?>

                            <option
                                value="<?= h($cat['name'] ?? null) ?>"
                                <?= isSelectedCategory(scalarValue($category), $cat)
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= h($cat['name'] ?? null) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- キーワード -->

                <fieldset class="search-field search-field--condition">
                    <legend>コンディション</legend>
                    <div class="condition-options">
                        <?php foreach ($productConditions as $conditionValue => $condition): ?>
                            <label class="condition-option">
                                <input type="checkbox" name="condition[]" value="<?= h($conditionValue) ?>" <?= in_array($conditionValue, $selectedConditions, true) ? 'checked' : '' ?> >
                                <span><?= h($condition['label'] ?? $conditionValue) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>

                <!-- キーワード -->

                <div class="
                    search-field
                    search-field--keyword
                ">

                    <label for="keyword">
                        キーワード
                    </label>

                    <input
                        type="text"
                        name="keyword"
                        id="keyword"
                        placeholder="例：shimano, 105, カーボンフレーム"
                        value="<?= h($keyword) ?>"
                    >

                </div>


                <!-- ソート -->

                <div class="
                    search-field
                    search-field--sort
                ">

                    <label for="sort">
                        並び替え
                    </label>

                    <select
                        name="sort"
                        id="sort"
                    >

                        <option
                            value="condition"
                            <?= $currentSort === 'condition'
                                ? 'selected'
                                : '' ?>
                        >
                            コンディション順
                        </option>

                        <option
                            value="newest"
                            <?= $currentSort === 'newest'
                                ? 'selected'
                                : '' ?>
                        >
                            新着順
                        </option>

                        <option
                            value="oldest"
                            <?= $currentSort === 'oldest'
                                ? 'selected'
                                : '' ?>
                        >
                            古い順
                        </option>

                        <option
                            value="price_asc"
                            <?= $currentSort === 'price_asc'
                                ? 'selected'
                                : '' ?>
                        >
                            価格が安い順
                        </option>

                        <option
                            value="price_desc"
                            <?= $currentSort === 'price_desc'
                                ? 'selected'
                                : '' ?>
                        >
                            価格が高い順
                        </option>

                    </select>

                </div>


                <button
                    type="submit"
                    class="btn-hero search-submit"
                >
                    検索する
                </button>

            </form>


            <!--
            ================================================================
            検索結果ヘッダー
            ================================================================
            -->

            <div class="
                section-head
                d-flex
                align-items-center
                justify-content-between
            ">

                <h2>

                    商品一覧

                    <span class="result-count">
                        （<?= (int) $result['total'] ?>件）
                    </span>

                </h2>

            </div>


            <!--
            ================================================================
            商品一覧
            ================================================================
            -->

            <?php if (empty($result['items'])): ?>

                <p class="no-result">
                    条件に一致する商品が見つかりませんでした。
                    検索条件を変えてお試しください。
                </p>


            <?php else: ?>

                <div class="row prod-grid">


                    <?php foreach (
                        $result['items']
                        as $item
                    ): ?>

                        <div class="
                            col-6
                            col-md-4
                            col-lg-3
                        ">

                            <a
                                href="product-detail.php?id=<?= (int) $item['product_id'] ?>"
                                class="prod-card"
                            >

                                <!-- 商品画像 -->

                                <div class="prod-thumb">

                                    <?php if (
                                        !empty($item['image_url'])
                                    ): ?>

                                        <img
                                            src="<?= h(
                                                (string) $item['image_url']
                                            ) ?>"
                                            alt="<?= h(
                                                (string) $item['name']
                                            ) ?>"
                                            class="prod-image"
                                        >

                                    <?php else: ?>

                                        <span
                                            class="thumb-icon"
                                            aria-hidden="true"
                                        >
                                            &#128690;
                                        </span>

                                    <?php endif; ?>


                                    <?php if (
                                        !empty($item['status'])
                                        && $item['status']
                                            === 'new_arrival'
                                    ): ?>

                                        <span class="badge-new">
                                            NEW
                                        </span>

                                    <?php endif; ?>

                                </div>


                                <!-- 商品情報 -->

                                <div class="prod-info">

                                    <p class="prod-name">

                                        <?= h(
                                            (string) $item['category_name']
                                        ) ?>

                                        /

                                        <?= h(
                                            (string) $item['name']
                                        ) ?>

                                    </p>


                                    <p class="prod-price">

                                        <?= number_format(
                                            (float) $item['price']
                                        ) ?>円

                                        <span class="tax">
                                            (税込)
                                        </span>

                                    </p>

                                </div>

                            </a>

                        </div>

                    <?php endforeach; ?>

                </div>


                <!--
                ================================================================
                ページネーション
                ================================================================
                -->

                <?php if (
                    $result['total_pages'] > 1
                ): ?>

                    <nav
                        class="pagination"
                        aria-label="ページネーション"
                    >

                        <?php for (
                            $p = 1;
                            $p <= $result['total_pages'];
                            $p++
                        ): ?>

                            <a
                                href="<?= h(
                                    buildQuery([
                                        'page' => $p
                                    ])
                                ) ?>"
                                class="
                                    page-link
                                    <?= $p === $result['page']
                                        ? 'is-current'
                                        : '' ?>
                                "
                            >
                                <?= $p ?>
                            </a>

                        <?php endfor; ?>

                    </nav>

                <?php endif; ?>


            <?php endif; ?>


        </div>

    </section>

</main>


<script src="/assets/js/products.js"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
