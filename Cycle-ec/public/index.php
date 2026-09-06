<?php

$config = require __DIR__ . '/../config/config.php';

$baseUrl = $config['app']['base_url'];

require_once __DIR__ . '/../src/Category.php';
require_once __DIR__ . '/../src/Product.php';
$categories = [];
$recommendedProducts = [];
$newProducts = [];
$priceReducedProducts = [];
try {
    $categories = Category::all();
    $recommendedProducts = Product::recommended(5);
    $newProducts = Product::search(['sort' => 'newest', 'page' => 1])['items'];
    $priceReducedProducts = Product::priceReduced(5);
} catch (Throwable $e) {
    error_log('Top page category loading failed: ' . $e->getMessage());
}

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

$pageCss = 'index.css';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/nav.php';

?>

<!-- HERO -->
<section class="hero">
    <div class="hero-bike" aria-hidden="true">
        <svg viewBox="0 0 640 440" width="100%" height="100%">
            <g fill="none" stroke="#e8b923" stroke-width="5" opacity="0.55">
                <circle cx="150" cy="330" r="95" />
                <circle cx="470" cy="330" r="95" />
                <path d="M150 330 L300 150 L470 330 L300 330 L220 200 L390 200" />
                <path d="M300 150 L340 150" />
                <path d="M150 330 L60 330" />
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
                    class="btn btn-outline-light btn-hero">
                    ジャンク倉庫を見る
                </a>
            </div>

        </div>
    </div>
</section>


<!-- RECOMMEND -->
<section id="recommend">

    <div class="container">

        <div class="section-head d-flex align-items-baseline justify-content-between">

            <div>
                <span class="section-label">OVERHAUL PICKS</span>
                <h2>おすすめ商品</h2>
            </div>

            <a
                href="<?= $baseUrl ?>pages/products.php"
                class="more-link"
            >
                一覧を見る &gt;
            </a>
        </div>


        <!-- SWIPER -->
        <div class="swiper recommend-swiper">
            <div class="swiper-wrapper">
                <?php foreach ($recommendedProducts as $product): ?>
                    <div class="swiper-slide">
                        <a href="<?= $baseUrl ?>pages/product-detail.php?id=<?= (int) $product['product_id'] ?>" class="product-card">
                            <div class="product-image">
                                <span class="product-badge">PICK UP</span>
                                <?php if (!empty($product['image_url'])): ?>
                                    <img src="<?= h((string) $product['image_url']) ?>" alt="<?= h((string) $product['name']) ?>">
                                <?php else: ?>
                                    <div class="d-flex align-items-center justify-content-center h-100 text-muted">画像なし</div>
                                <?php endif; ?>
                            </div>
                            <div class="product-info">
                                <div class="product-category"><?= h((string) $product['category_name']) ?></div>
                                <h3 class="product-name"><?= h((string) $product['name']) ?></h3>
                                <div class="product-price">¥<?= number_format((float) $product['price']) ?></div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
                <?php if (!$recommendedProducts): ?>
                    <div class="swiper-slide"><p class="text-muted py-4">おすすめ商品は準備中です。</p></div>
                <?php endif; ?>
            </div>


            <!-- NAVIGATION -->
            <div class="swiper-button-prev"></div>
            <div class="swiper-button-next"></div>

            <!-- PAGINATION -->
            <div class="swiper-pagination"></div>

        </div>

    </div>

</section>

<!-- CATEGORY -->
<section id="category">
    <div class="container">

        <h2 class="cat-title">
            カテゴリから探す
        </h2>

        <div
            class="row g-3"
            id="cat-grid">
            <?php foreach ($categories as $category): ?>
                <div class="col-6 col-lg">
                    <a href="<?= $baseUrl ?>pages/products.php?category=<?= (int) $category['category_id'] ?>" class="cat-card" data-category-id="<?= (int) $category['category_id'] ?>">
                        <div class="cat-icon">
                            <?php if (!empty($category['icon_url'])): ?>
                                <img src="<?= htmlspecialchars((string) $category['icon_url'], ENT_QUOTES, 'UTF-8') ?>" alt="">
                            <?php else: ?>
                                <span aria-hidden="true">🚲</span>
                            <?php endif; ?>
                        </div>
                        <div class="cat-jp"><?= htmlspecialchars((string) $category['name'], ENT_QUOTES, 'UTF-8') ?></div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="cat-more">
            <a
                href="<?= $baseUrl ?>pages/products.php">
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
                href="<?= $baseUrl ?>pages/products.php?sort=newest"
                class="more-link">
                一覧を見る &gt;
            </a>

        </div>

        <div class="row g-3" id="new-grid">
            <?php foreach ($newProducts as $product): ?>
                <div class="col-6 col-md-4 col-lg">
                    <a href="<?= $baseUrl ?>pages/product-detail.php?id=<?= (int) $product['product_id'] ?>" class="prod-card">
                        <div class="prod-thumb">
                            <span class="badge-new">NEW</span>
                            <?php if (!empty($product['image_url'])): ?><img class="prod-image" src="<?= h($product['image_url']) ?>" alt="<?= h($product['name']) ?>"><?php else: ?><span class="thumb-icon">画像なし</span><?php endif; ?>
                        </div>
                        <div class="prod-info"><div class="prod-name"><?= h($product['name']) ?></div><div class="prod-price">¥<?= number_format((float) $product['price']) ?><span class="tax">税込</span></div></div>
                    </a>
                </div>
            <?php endforeach; ?>
            <?php if (!$newProducts): ?><p class="text-muted">商品はまだありません。</p><?php endif; ?>
        </div>

    </div>
</section>


<!-- PRICE DOWN -->
<section id="pricedown">
    <div class="container">

        <div class="section-head d-flex align-items-baseline justify-content-between">

            <h2>値下げしました</h2>

            <a
                href="<?= $baseUrl ?>pages/products.php?sort=price_desc"
                class="more-link">
                一覧を見る &gt;
            </a>

        </div>

        <div class="row g-3" id="down-grid">
            <?php foreach ($priceReducedProducts as $product): ?>
                <?php $discountRate = (int) round((1 - ((float) $product['price'] / (float) $product['old_price'])) * 100); ?>
                <div class="col-6 col-md-4 col-lg">
                    <a href="<?= $baseUrl ?>pages/product-detail.php?id=<?= (int) $product['product_id'] ?>" class="prod-card">
                        <div class="prod-thumb">
                            <span class="badge-down">PRICE DOWN</span><span class="off-badge"><?= $discountRate ?>%<br>OFF</span>
                            <?php if (!empty($product['image_url'])): ?><img class="prod-image" src="<?= h($product['image_url']) ?>" alt="<?= h($product['name']) ?>"><?php else: ?><span class="thumb-icon">画像なし</span><?php endif; ?>
                        </div>
                        <div class="prod-info"><div class="prod-name"><?= h($product['name']) ?></div><div><span class="price-old">¥<?= number_format((float) $product['old_price']) ?></span><span class="price-new">¥<?= number_format((float) $product['price']) ?></span><span class="tax">税込</span></div></div>
                    </a>
                </div>
            <?php endforeach; ?>
            <?php if (!$priceReducedProducts): ?><p class="text-muted">値下げ商品はありません。</p><?php endif; ?>
        </div>

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
            id="cond-grid">

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
<section id="about">
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
                    href="<?= $baseUrl ?>index.php#about"
                    class="detail-link">
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


<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

<script src="<?= $baseUrl ?>assets/js/index.js"></script>
<script src="<?= $baseUrl ?>assets/js/Samplescript.js"></script>
<script src="<?= $baseUrl ?>assets/js/fuwatto-animation.js"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>