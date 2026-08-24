<!-- TOP BAR -->
<div class="topbar">
  <div class="container d-flex align-items-center justify-content-between">
    <div class="shop-tag">中古ロードバイク・自転車パーツ専門店 OVERHAUL</div>

  </div>
</div>

<!-- HEADER -->
<header class="main-header sticky-top">
  <div class="container d-flex align-items-center gap-4">

    <!-- ロゴ -->
    <a href="#" class="logo">
      <span class="logo-main">OVERHAUL</span>
      <span class="logo-sub">USED BIKE &amp; PARTS</span>
    </a>

    <!-- 検索 -->
    <form class="search-form d-none d-lg-flex" id="search-form">
      <input
        type="text"
        class="form-control"
        placeholder="キーワードで検索"
        id="search-input"
      >
      <button type="submit" class="btn" aria-label="検索">
        🔍
      </button>
    </form>

    <!-- PCナビ -->
    <nav class="gnav d-none d-lg-block flex-grow-1">

      <!-- 会員・カート -->
      <ul class="user-nav d-flex justify-content-end align-items-center gap-4 mb-2 list-unstyled">

        <li>
          <a href="#" id="register-link">
            新規登録
          </a>
        </li>

        <li>
          <a href="#" id="login-link">
            ログイン
          </a>
        </li>

        <li>
          <a href="#" id="cart-link-top">
            カート
            <span class="cart-badge" id="cart-count-top">0</span>
          </a>
        </li>

      </ul>

      <!-- カテゴリ -->
      <ul class="d-flex justify-content-end gap-4 mb-0 list-unstyled">
        <li><a href="#" data-cat="all">商品一覧</a></li>
        <li><a href="#" data-cat="road">ロードバイク</a></li>
        <li><a href="#" data-cat="wheel">ホイール</a></li>
        <li><a href="#" data-cat="component">コンポーネント</a></li>
        <li><a href="#" data-cat="parts">パーツ</a></li>
        <li><a href="#" data-cat="wear">ウェア・用品</a></li>
        <li><a href="#" data-cat="junk">ジャンク倉庫</a></li>
        <li><a href="#">お問い合わせ</a></li>
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
    <h5 class="offcanvas-title" id="mobileNavLabel">
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

      <li>
        <a href="#" id="mobile-register-link">
          新規登録
        </a>
      </li>

      <li>
        <a href="#" id="mobile-login-link">
          ログイン
        </a>
      </li>

      <li>
        <a href="#" id="mobile-cart-link">
          カート
          <span class="cart-badge" id="cart-count-mobile">0</span>
        </a>
      </li>

    </ul>

    <hr>

    <!-- カテゴリ -->
    <ul class="mobile-category-nav list-unstyled">

      <li>
        <a href="#" data-cat="all">商品一覧</a>
      </li>

      <li>
        <a href="#" data-cat="road">ロードバイク</a>
      </li>

      <li>
        <a href="#" data-cat="wheel">ホイール</a>
      </li>

      <li>
        <a href="#" data-cat="component">コンポーネント</a>
      </li>

      <li>
        <a href="#" data-cat="parts">パーツ</a>
      </li>

      <li>
        <a href="#" data-cat="wear">ウェア・用品</a>
      </li>

      <li>
        <a href="#" data-cat="junk">ジャンク倉庫</a>
      </li>

      <li>
        <a href="#">お問い合わせ</a>
      </li>

    </ul>

  </div>
</div>