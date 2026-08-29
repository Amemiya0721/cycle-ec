<?php

$config = require __DIR__ . '/../config/config.php';

$baseUrl = $config['app']['base_url'];

$pageCss = 'index.css';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/nav.php';

?>

<!-- HERO -->
<section class="hero">
    <div class="hero-bike" aria-hidden="true">
        <svg viewBox="0 0 640 440" width="100%" height="100%">
            <g fill="none" stroke="#e8b923" stroke-width="5" opacity="0.55">
                <circle cx="150" cy="330" r="95"/>
                <circle cx="470" cy="330" r="95"/>
                <path d="M150 330 L300 150 L470 330 L300 330 L220 200 L390 200"/>
                <path d="M300 150 L340 150"/>
                <path d="M150 330 L60 330"/>
            </g>
        </svg>
    </div>

    <div class="container h-100 d-flex align-items-center">
        <div class="hero-inner">

            <h1>
                ここにタイトル<br>
                ここにサブタイトル
            </h1>

            <p>
                OVERHAULは、使えるものを必要な人へつなぐ<br>
                中古ロードバイク・自転車パーツ専門店です。
            </p>

            <div class="d-flex gap-3 hero-cta">
                <a
                    href="<?= $baseUrl ?>pages/products.php?category=junk"
                    class="btn btn-outline-light btn-hero"
                >
                    ジャンク倉庫を見る
                </a>
            </div>

        </div>
    </div>
</section>


<!-- NEWS -->
<div class="news-bar">
    <div class="container d-flex align-items-center gap-3">

        <span class="news-label">NEWS</span>

        <span class="news-text flex-grow-1">
            <span class="news-date">2026.08.20</span>
            新着商品を15点追加しました！
        </span>

        <a
            href="<?= $baseUrl ?>pages/news.php"
            class="more-link"
            id="news-more"
        >
            一覧を見る &gt;
        </a>

    </div>
</div>


<!-- CATEGORY -->
<section id="category">
    <div class="container">

        <h2 class="cat-title">
            カテゴリから探す
        </h2>

        <div
            class="row g-3"
            id="cat-grid"
        ></div>

        <div class="cat-more">
            <a
                href="<?= $baseUrl ?>pages/products.php"
            >
                すべてのカテゴリを見る &gt;
            </a>
        </div>

    </div>
</section>


<!-- NEW PRODUCTS -->
<section id="new-products">
    <div class="container">

        <div class="section-head d-flex align-items-baseline justify-content-between">

            <h2>新着商品</h2>

            <a
                href="<?= $baseUrl ?>pages/products.php?sort=new"
                class="more-link"
            >
                一覧を見る &gt;
            </a>

        </div>

        <div
            class="row g-3"
            id="new-grid"
        ></div>

    </div>
</section>


<!-- PRICE DOWN -->
<section id="pricedown">
    <div class="container">

        <div class="section-head d-flex align-items-baseline justify-content-between">

            <h2>値下げしました</h2>

            <a
                href="<?= $baseUrl ?>pages/products.php?sort=price_down"
                class="more-link"
            >
                一覧を見る &gt;
            </a>

        </div>

        <div
            class="row g-3"
            id="down-grid"
        ></div>

    </div>
</section>


<!-- CONDITION -->
<section>
    <div class="container">

        <h2 class="cond-title">
            商品状態について
        </h2>

        <div
            class="row g-4 g-lg-0"
            id="cond-grid"
        >

            <div class="col-6 col-lg cond-item">
                <div class="cond-rank">Sランク</div>
                <div class="cond-desc">未使用・新品同等</div>
                <div class="cond-sub">
                    使用感がほとんどない<br>
                    非常にきれいな状態
                </div>
            </div>

            <div class="col-6 col-lg cond-item">
                <div class="cond-rank">Aランク</div>
                <div class="cond-desc">使用感が少ない良品</div>
                <div class="cond-sub">
                    小さな傷はあるが<br>
                    全体的にきれいな状態
                </div>
            </div>

            <div class="col-6 col-lg cond-item">
                <div class="cond-rank">Bランク</div>
                <div class="cond-desc">通常使用の中古品</div>
                <div class="cond-sub">
                    使用に伴う傷・汚れが<br>
                    あるが使用に問題なし
                </div>
            </div>

            <div class="col-6 col-lg cond-item">
                <div class="cond-rank">Cランク</div>
                <div class="cond-desc">傷・使用感が目立つ</div>
                <div class="cond-sub">
                    目立つ傷や汚れがあり<br>
                    使用感のある状態
                </div>
            </div>

            <div class="col-6 col-lg cond-item junk">
                <div class="cond-rank">JUNK</div>
                <div class="cond-desc">動作保証なし・現状販売</div>
                <div class="cond-sub">
                    返品・返金対象外<br>
                    &nbsp;
                </div>
                <div class="cond-warn">
                    返品・返金対象外
                </div>
            </div>

        </div>

    </div>
</section>


<!-- ABOUT / INFO -->
<section>
    <div class="container">

        <div class="row g-5">

            <div class="col-lg-6">

                <h3>OVERHAULについて</h3>

                <p>
                    OVERHAULは、中古ロードバイク・自転車パーツを専門に取り扱うショップです。
                    一つひとつ丁寧に検品・撮影し、商品の状態をできるだけわかりやすく掲載しています。
                    一点物の出会いを、ぜひお楽しみください。
                </p>

                <a
                    href="<?= $baseUrl ?>pages/about.php"
                    class="detail-link"
                >
                    詳しく見る &gt;
                </a>

            </div>


            <div class="col-lg-6">

                <h3>INFORMATION</h3>

                <div class="row g-4">

                    <div class="col-6 info-item">
                        <div class="info-icon">🕐</div>
                        <div>
                            <h4>中古商品について</h4>
                            <p>商品の状態についてのご案内</p>
                        </div>
                    </div>

                    <div class="col-6 info-item">
                        <div class="info-icon">🚚</div>
                        <div>
                            <h4>配送について</h4>
                            <p>配送方法・送料について</p>
                        </div>
                    </div>

                    <div class="col-6 info-item">
                        <div class="info-icon">☀</div>
                        <div>
                            <h4>ジャンク品について</h4>
                            <p>ジャンク品の注意事項はこちら</p>
                        </div>
                    </div>

                    <div class="col-6 info-item">
                        <div class="info-icon">↩</div>
                        <div>
                            <h4>返品・返金について</h4>
                            <p>返品・返金ポリシーはこちら</p>
                        </div>
                    </div>

                </div>

            </div>

        </div>

    </div>
</section>


<script src="<?= $baseUrl ?>assets/js/Samplescript.js"></script>
<script src="<?= $baseUrl ?>assets/js/fuwatto-animation.js"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>