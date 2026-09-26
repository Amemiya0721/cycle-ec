<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

$config = require __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Category.php';

$baseUrl = $config['app']['base_url'];
$isLoggedIn = !empty($_SESSION['user_id']);
$loggedInName = (string) ($_SESSION['user_name'] ?? '');
$categories = [];
try {
  $categories = Category::all();
} catch (Throwable $exception) {
  error_log('Navigation category loading failed: ' . $exception->getMessage());
}

?>

<!-- TOP BAR -->
<div class="topbar">
  <div class="container d-flex align-items-center justify-content-between">
    <div class="shop-tag">
      中古ロードバイク・自転車パーツ専門店 OVERHAUL
    </div>
  </div>
</div>


<!-- HEADER -->
<header class="main-header sticky-top">
  <div class="container d-flex align-items-center gap-4">

    <!-- ロゴ -->
    <a href="<?= $baseUrl ?>index.php" class="logo">
      <span class="logo-main">OVERHAUL</span>
      <span class="logo-sub">USED BIKE &amp; PARTS</span>
    </a>


    <!-- 検索 -->
    <form
      class="search-form d-none d-lg-flex"
      id="search-form"
      action="<?= $baseUrl ?>pages/products.php"
      method="GET"
    >
      <input
        type="text"
        class="form-control"
        name="keyword"
        placeholder="キーワードで検索"
        id="search-input"
      >

      <button
        type="submit"
        class="btn"
        aria-label="検索"
      >
        🔍
      </button>
    </form>


    <!-- PCナビ -->
    <nav class="gnav d-none d-lg-block flex-grow-1">

      <!-- 会員・カート -->
      <ul class="user-nav d-flex justify-content-end align-items-center gap-4 mb-2 list-unstyled">

        <?php if ($isLoggedIn): ?>
          <li class="text-muted"><?= htmlspecialchars($loggedInName, ENT_QUOTES, 'UTF-8') ?> さん</li>
          <li>
            <a href="<?= $baseUrl ?>pages/logout.php" id="logout-link">ログアウト</a>
          </li>
        <?php else: ?>
          <li>
            <a href="<?= $baseUrl ?>pages/register.php" id="register-link">新規登録</a>
          </li>
          <li>
            <a href="<?= $baseUrl ?>pages/login.php" id="login-link">ログイン</a>
          </li>
        <?php endif; ?>

        <li>
          <a
            href="<?= $baseUrl ?>pages/cart.php"
            id="cart-link-top"
          >
            カート
            <span class="cart-badge" id="cart-count-top">0</span>
          </a>
        </li>

      </ul>


      <!-- カテゴリ -->
      <ul class="d-flex justify-content-end gap-4 mb-0 list-unstyled">

        <li>
          <a
            href="<?= $baseUrl ?>pages/products.php"
            data-cat="all"
          >
            商品一覧
          </a>
        </li>

        <?php foreach ($categories as $category): ?>
          <li>
            <a href="<?= $baseUrl ?>pages/products.php?category=<?= (int) $category['category_id'] ?>" data-cat="<?= (int) $category['category_id'] ?>">
              <?= htmlspecialchars((string) $category['name'], ENT_QUOTES, 'UTF-8') ?>
            </a>
          </li>
        <?php endforeach; ?>

        <li>
          <a href="<?= $baseUrl ?>pages/contact.php">
            お問い合わせ
          </a>
        </li>

        <li>
          <a href="<?= $baseUrl ?>pages/terms.php">
            利用規約
          </a>
        </li>

      </ul>

    </nav>


    <!-- モバイル：ハンバーガー -->
    <button
      class="navbar-toggler hamburger d-lg-none ms-auto"
      id="hamburger"
      aria-label="メニュー"
      data-bs-toggle="offcanvas"
      data-bs-target="#mobileNav"
    >
      <span class="navbar-toggler-icon-custom"></span>
    </button>

  </div>
</header>


<!-- =========================
     モバイルメニュー
========================= -->
<div
  class="offcanvas offcanvas-end"
  tabindex="-1"
  id="mobileNav"
  aria-labelledby="mobileNavLabel"
>

  <div class="offcanvas-header">

    <h5
      class="offcanvas-title"
      id="mobileNavLabel"
    >
      OVERHAUL
    </h5>

    <button
      type="button"
      class="btn-close"
      data-bs-dismiss="offcanvas"
      aria-label="閉じる"
    ></button>

  </div>


  <div class="offcanvas-body">

    <!-- 会員メニュー -->
    <ul class="mobile-user-nav list-unstyled">

      <?php if ($isLoggedIn): ?>
        <li class="text-muted"><?= htmlspecialchars($loggedInName, ENT_QUOTES, 'UTF-8') ?> さん</li>
        <li><a href="<?= $baseUrl ?>pages/logout.php" id="mobile-logout-link">ログアウト</a></li>
      <?php else: ?>
        <li><a href="<?= $baseUrl ?>pages/register.php" id="mobile-register-link">新規登録</a></li>
        <li><a href="<?= $baseUrl ?>pages/login.php" id="mobile-login-link">ログイン</a></li>
      <?php endif; ?>

      <li>
        <a
          href="<?= $baseUrl ?>pages/cart.php"
          id="mobile-cart-link"
        >
          カート
          <span
            class="cart-badge"
            id="cart-count-mobile"
          >
            0
          </span>
        </a>
      </li>

    </ul>


    <hr>


    <!-- カテゴリ -->
    <ul class="mobile-category-nav list-unstyled">

      <li>
        <a
          href="<?= $baseUrl ?>pages/products.php"
          data-cat="all"
        >
          商品一覧
        </a>
      </li>

      <?php foreach ($categories as $category): ?>
        <li>
          <a href="<?= $baseUrl ?>pages/products.php?category=<?= (int) $category['category_id'] ?>" data-cat="<?= (int) $category['category_id'] ?>">
            <?= htmlspecialchars((string) $category['name'], ENT_QUOTES, 'UTF-8') ?>
          </a>
        </li>
      <?php endforeach; ?>

      <li>
        <a href="<?= $baseUrl ?>pages/contact.php">
          お問い合わせ
        </a>
      </li>

      <li>
        <a href="<?= $baseUrl ?>pages/terms.php">
          利用規約
        </a>
      </li>

    </ul>

  </div>
</div>