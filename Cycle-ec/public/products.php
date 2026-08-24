<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Category.php';
require_once __DIR__ . '/../src/Product.php';

/**
 * products.php
 * -----------------------------------------------------------
 * 責務：
 *   1. GETパラメータの取得
 *   2. 検索条件としての整理（Product::searchへ渡す配列の組み立てのみ）
 *   3. 検索結果の表示
 * SQLはここに書かない。すべて Product.php / Category.php に委譲する。
 *
 * URLを検索状態の正とするため、ページを直接開いた場合でも
 * $_GET から検索条件を復元できる（JS側の状態管理には依存しない）。
 */

// ---- 1. GETパラメータ取得（未指定はnullのまま扱う） ----
$rawCategory = isset($_GET['category']) ? (string) $_GET['category'] : null;
$rawKeyword  = isset($_GET['keyword']) ? (string) $_GET['keyword'] : null;
$rawSort     = isset($_GET['sort']) ? (string) $_GET['sort'] : null;
$rawPage     = isset($_GET['page']) ? (string) $_GET['page'] : null;

// ---- 2. 検索条件を整理 ----
// 空文字は「指定なし」として扱うため、Product::search に渡す前に取り除く。
$criteria = array_filter([
    'category' => $rawCategory !== null && trim($rawCategory) !== '' ? $rawCategory : null,
    'keyword'  => $rawKeyword !== null && trim($rawKeyword) !== '' ? $rawKeyword : null,
    'sort'     => $rawSort !== null && trim($rawSort) !== '' ? $rawSort : null,
    'page'     => $rawPage !== null && ctype_digit($rawPage) ? (int) $rawPage : null,
], static fn ($value) => $value !== null);

// ---- 3. 検索実行 ----
$result = Product::search($criteria);
$categories = Category::all();

/** 表示用エスケープヘルパー */
function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/** ページネーションリンク・フォーム再送信用に、現在のGETパラメータを引き継いだURLを作る */
function buildQuery(array $overrides): string
{
    $query = array_merge($_GET, $overrides);
    // 空文字・nullのパラメータはURLに残さない
    $query = array_filter($query, static fn ($v) => $v !== null && $v !== '');
    return 'products.php' . ($query ? '?' . http_build_query($query) : '');
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>商品一覧 | OVERHAUL</title>
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="products.css">
</head>
<body>

<header class="main-header">
  <div class="container d-flex align-items-center justify-content-between">
    <a href="index.php" class="logo">
      <span class="logo-main">OVERHAUL</span>
      <span class="logo-sub">USED ROAD BIKE SPECIALIST</span>
    </a>
  </div>
</header>

<main>
  <section class="products-section">
    <div class="container">

      <!-- ================= 検索フォーム ================= -->
      <form class="search-form-panel" method="get" action="products.php" id="productSearchForm">
        <div class="search-field">
          <label for="category">カテゴリ</label>
          <select name="category" id="category">
            <option value="">すべてのカテゴリ</option>
            <?php foreach ($categories as $cat): ?>
              <option
                value="<?= h($cat['name']) ?>"
                <?= (isset($criteria['category']) && Category::resolveCategoryId((string) $criteria['category']) === (int) $cat['category_id']) ? 'selected' : '' ?>
              >
                <?= h($cat['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="search-field search-field--keyword">
          <label for="keyword">キーワード</label>
          <input
            type="text"
            name="keyword"
            id="keyword"
            placeholder="例：shimano, 105, カーボンフレーム"
            value="<?= h($rawKeyword) ?>"
          >
        </div>

        <div class="search-field search-field--sort">
          <label for="sort">並び替え</label>
          <select name="sort" id="sort">
            <option value="newest" <?= ($rawSort === null || $rawSort === 'newest') ? 'selected' : '' ?>>新着順</option>
            <option value="price_asc" <?= $rawSort === 'price_asc' ? 'selected' : '' ?>>価格が安い順</option>
            <option value="price_desc" <?= $rawSort === 'price_desc' ? 'selected' : '' ?>>価格が高い順</option>
          </select>
        </div>

        <button type="submit" class="btn-hero search-submit">検索する</button>
      </form>

      <!-- ================= 検索結果ヘッダー ================= -->
      <div class="section-head d-flex align-items-center justify-content-between">
        <h2>
          商品一覧
          <span class="result-count">（<?= (int) $result['total'] ?>件）</span>
        </h2>
      </div>

      <!-- ================= 商品グリッド ================= -->
      <?php if (empty($result['items'])): ?>
        <p class="no-result">条件に一致する商品が見つかりませんでした。検索条件を変えてお試しください。</p>
      <?php else: ?>
        <div class="row prod-grid">
          <?php foreach ($result['items'] as $item): ?>
            <div class="col-6 col-md-4 col-lg-3">
              <a href="product-detail.php?id=<?= (int) $item['product_id'] ?>" class="prod-card">
                <div class="prod-thumb">
                  <span class="thumb-icon" aria-hidden="true">&#128690;</span>
                  <?php if (!empty($item['status']) && $item['status'] === 'new_arrival'): ?>
                    <span class="badge-new">NEW</span>
                  <?php endif; ?>
                </div>
                <div class="prod-info">
                  <p class="prod-name"><?= h($item['category_name']) ?> / <?= h($item['name']) ?></p>
                  <p class="prod-price">
                    <?= number_format((float) $item['price']) ?>円
                    <span class="tax">(税込)</span>
                  </p>
                </div>
              </a>
            </div>
          <?php endforeach; ?>
        </div>

        <!-- ================= ページネーション ================= -->
        <?php if ($result['total_pages'] > 1): ?>
          <nav class="pagination" aria-label="ページネーション">
            <?php for ($p = 1; $p <= $result['total_pages']; $p++): ?>
              <a
                href="<?= h(buildQuery(['page' => $p])) ?>"
                class="page-link <?= $p === $result['page'] ? 'is-current' : '' ?>"
              ><?= $p ?></a>
            <?php endfor; ?>
          </nav>
        <?php endif; ?>
      <?php endif; ?>

    </div>
  </section>
</main>

<script src="products.js"></script>
</body>
</html>
