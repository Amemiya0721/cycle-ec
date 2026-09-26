<?php

declare(strict_types=1);

$config = require __DIR__ . '/../../config/config.php';
$baseUrl = $config['app']['base_url'];
$shippingWarranty = require __DIR__ . '/../../config/shipping_warranty.php';
$productConditions = require __DIR__ . '/../../config/product_conditions.php';

require_once __DIR__ . '/../../src/Product.php';

$productId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$product = null;
$relatedProducts = [];
$errorMessage = null;

if ($productId === false || $productId === null || $productId < 1) {
    http_response_code(400);
    $errorMessage = '商品IDが正しくありません。';
} else {
    try {
        $product = Product::findById($productId);
    } catch (PDOException $exception) {
        http_response_code(500);
        error_log('Product detail fetch failed: ' . $exception->getMessage());
        $errorMessage = '商品の取得に失敗しました。';
    }

    if ($product === null && $errorMessage === null) {
        http_response_code(404);
        $errorMessage = '商品が見つかりません。';
    }

    if ($product !== null) {
        try {
            $relatedProducts = Product::relatedByCategory(
                (int) $product['product_id'],
                (int) $product['category_id']
            );
        } catch (PDOException $exception) {
            error_log('Related products fetch failed: ' . $exception->getMessage());
            $relatedProducts = [];
        }
    }
}

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * 在庫表示文言を返す。
 * status が SOLD/売切れの場合は「売り切れ」を優先する。
 */
function stockLabel(array $product): string
{
    $status = (string) ($product['status'] ?? '');
    if (in_array($status, ['売切れ', 'SOLD'], true)) {
        return '売り切れ';
    }

    $stock = (int) ($product['stock_quantity'] ?? 0);
    if ($stock <= 0) {
        return '売り切れ';
    }
    if ($stock === 1) {
        return '在庫：1点';
    }
    return "在庫：{$stock}点";
}

function isSoldOut(array $product): bool
{
    $status = (string) ($product['status'] ?? '');
    if (in_array($status, ['売切れ', 'SOLD'], true)) {
        return true;
    }
    return (int) ($product['stock_quantity'] ?? 0) <= 0;
}

$pageCss = 'product-detail.css';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/nav.php';
?>

<main>
    <?php if ($errorMessage !== null): ?>

        <div class="container py-5">
            <h1 class="h3"><?= h($errorMessage) ?></h1>
            <p><a href="<?= h($baseUrl) ?>pages/products.php">商品一覧へ戻る</a></p>
        </div>

    <?php else: ?>

        <div class="container pd-wrap">

            <!-- パンくず -->
            <nav aria-label="パンくず" class="pd-breadcrumb">
                <a href="<?= h($baseUrl) ?>index.php">HOME</a>
                <span class="sep">&gt;</span>
                <a href="<?= h($baseUrl) ?>pages/products.php?category=<?= (int) $product['category_id'] ?>">
                    <?= h((string) $product['category_name']) ?>
                </a>
                <span class="sep">&gt;</span>
                <span><?= h((string) $product['name']) ?></span>
            </nav>

            <div class="pd-main row g-4">

                <!-- ============ 画像エリア ============ -->
                <div class="col-lg-7">
                    <div class="pd-gallery">

                        <?php if (!empty($product['product_condition'])): ?>
                            <span class="pd-condition-flag">
                                <?= h((string) ($productConditions[$product['product_condition']]['label'] ?? $product['product_condition'])) ?>
                            </span>
                        <?php endif; ?>

                        <?php if (!empty($product['images'])): ?>
                            <div class="pd-main-image" id="pdMainImage">
                                <img
                                    src="<?= h((string) $product['images'][0]['image_url']) ?>"
                                    alt="<?= h((string) $product['name']) ?>"
                                    id="pdMainImageTag"
                                >
                            </div>

                            <?php if (count($product['images']) > 1): ?>
                                <div class="pd-thumbs">
                                    <?php foreach ($product['images'] as $index => $image): ?>
                                        <button
                                            type="button"
                                            class="pd-thumb-btn <?= $index === 0 ? 'is-active' : '' ?>"
                                            data-image-url="<?= h((string) $image['image_url']) ?>"
                                        >
                                            <img src="<?= h((string) $image['image_url']) ?>" alt="">
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                        <?php else: ?>
                            <div class="pd-main-image pd-no-image">
                                <span class="thumb-icon" aria-hidden="true">&#128690;</span>
                            </div>
                        <?php endif; ?>

                    </div>
                </div>

                <!-- ============ 商品情報エリア ============ -->
                <div class="col-lg-5">
                    <div class="pd-info">

                        <p class="pd-manufacturer">
                            <?= h((string) ($product['manufacturer'] ?? $product['category_name'])) ?>
                        </p>

                        <h1 class="pd-name"><?= h((string) $product['name']) ?></h1>

                        <p class="pd-price">
                            &yen;<?= number_format((float) $product['price']) ?>
                            <span class="tax">（税込）</span>
                        </p>

                        <div class="pd-badges">
                            <?php if (!empty($product['product_condition'])): ?>
                                <span class="pd-badge">
                                    <?= h((string) ($productConditions[$product['product_condition']]['label'] ?? $product['product_condition'])) ?>
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($productConditions[$product['product_condition']]['description'] ?? null)): ?>
                                <span class="pd-badge pd-badge--muted">
                                    <?= h((string) $productConditions[$product['product_condition']]['description']) ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <p class="pd-stock <?= isSoldOut($product) ? 'is-soldout' : '' ?>">
                            <?= h(stockLabel($product)) ?>
                        </p>

                        <?php if (isSoldOut($product)): ?>
                            <p class="status-sold">SOLD OUT</p>
                        <?php else: ?>
                            <form method="post" action="<?= h($baseUrl) ?>pages/cart.php" class="pd-actions">
                                <input type="hidden" name="product_id" value="<?= (int) $product['product_id'] ?>">
                                <button class="btn btn-cart" type="submit">
                                    <span aria-hidden="true">&#128722;</span> カートに入れる
                                </button>
                                <button class="btn btn-buy" type="submit" formaction="<?= h($baseUrl) ?>pages/checkout.php">
                                    購入手続きへ
                                </button>
                            </form>
                        <?php endif; ?>

                        <ul class="pd-guarantees list-unstyled">
                            <li>&#128230; <?= h((string) $shippingWarranty['shipping_fee']) ?></li>
                            <li>&#9989; 安心の<?= h((string) $shippingWarranty['warranty']) ?></li>
                            <li>&#128257; 返品・交換について</li>
                        </ul>

                    </div>
                </div>

            </div>

            <!-- ============ タブ / アコーディオン ============ -->
            <div class="pd-tabs" id="pdTabs">

                <div class="pd-tab-nav d-none d-lg-flex" role="tablist">
                    <button class="pd-tab-btn is-active" data-target="pd-panel-about" type="button">商品について</button>
                    <button class="pd-tab-btn" data-target="pd-panel-condition" type="button">商品状態</button>
                    <button class="pd-tab-btn" data-target="pd-panel-specs" type="button">スペック</button>
                    <button class="pd-tab-btn" data-target="pd-panel-accessories" type="button">付属品</button>
                    <button class="pd-tab-btn" data-target="pd-panel-staff" type="button">スタッフコメント</button>
                    <button class="pd-tab-btn" data-target="pd-panel-shipping" type="button">配送・保証</button>
                    <?php if (!empty($relatedProducts)): ?>
                        <button class="pd-tab-btn" data-target="pd-panel-related" type="button">関連商品</button>
                    <?php endif; ?>
                </div>

                <!-- 商品について -->
                <section class="pd-panel is-active" id="pd-panel-about">
                    <h2 class="pd-panel-title d-lg-none">商品について</h2>
                    <?php if (trim((string) ($product['description'] ?? '')) !== ''): ?>
                        <p class="pd-description"><?= nl2br(h((string) $product['description'])) ?></p>
                    <?php else: ?>
                        <p class="pd-empty">商品説明は登録されていません。</p>
                    <?php endif; ?>
                </section>

                <!-- 商品状態 -->
                <section class="pd-panel" id="pd-panel-condition">
                    <h2 class="pd-panel-title d-lg-none">商品状態</h2>

                    <?php if (!empty($product['product_condition']) && isset($productConditions[$product['product_condition']])): ?>
                        <?php $cond = $productConditions[$product['product_condition']]; ?>
                        <p class="pd-condition-rank">
                            状態ランク：<span class="pd-condition-rank-badge"><?= h((string) $product['product_condition']) ?></span>
                        </p>
                        <?php if (!empty($cond['detail'])): ?>
                            <p class="pd-condition-detail"><?= nl2br(h((string) $cond['detail'])) ?></p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="pd-empty">商品状態の情報はありません。</p>
                    <?php endif; ?>

                    <?php if (!empty($product['condition_images'])): ?>
                        <div class="pd-condition-photos">
                            <?php foreach ($product['condition_images'] as $index => $photo): ?>
                                <figure class="pd-condition-photo">
                                    <img src="<?= h((string) $photo['image_url']) ?>" alt="傷・使用感の写真<?= $index + 1 ?>">
                                    <figcaption>写真 (<?= $index + 1 ?>)</figcaption>
                                </figure>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <!-- スペック -->
                <section class="pd-panel" id="pd-panel-specs">
                    <h2 class="pd-panel-title d-lg-none">スペック</h2>
                    <?php if (!empty($product['specs'])): ?>
                        <dl class="pd-spec-list">
                            <?php foreach ($product['specs'] as $spec): ?>
                                <dt><?= h((string) $spec['spec_key']) ?></dt>
                                <dd><?= h((string) $spec['spec_value']) ?></dd>
                            <?php endforeach; ?>
                        </dl>
                    <?php else: ?>
                        <p class="pd-empty">情報なし</p>
                    <?php endif; ?>
                </section>

                <!-- 付属品 -->
                <section class="pd-panel" id="pd-panel-accessories">
                    <h2 class="pd-panel-title d-lg-none">付属品</h2>
                    <?php if (!empty($product['accessories'])): ?>
                        <ul class="pd-accessory-list">
                            <?php foreach ($product['accessories'] as $accessory): ?>
                                <li><?= h((string) $accessory['content']) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="pd-empty">情報なし</p>
                    <?php endif; ?>
                </section>

                <!-- スタッフコメント -->
                <section class="pd-panel" id="pd-panel-staff">
                    <h2 class="pd-panel-title d-lg-none">スタッフコメント</h2>
                    <?php if (trim((string) ($product['staff_comment'] ?? '')) !== ''): ?>
                        <blockquote class="pd-staff-comment">
                            <?= nl2br(h((string) $product['staff_comment'])) ?>
                        </blockquote>
                    <?php else: ?>
                        <p class="pd-empty">コメントは登録されていません。</p>
                    <?php endif; ?>
                </section>

                <!-- 配送・保証 -->
                <section class="pd-panel" id="pd-panel-shipping">
                    <h2 class="pd-panel-title d-lg-none">配送・保証</h2>
                    <dl class="pd-spec-list">
                        <dt>送料</dt>
                        <dd><?= h((string) $shippingWarranty['shipping_fee']) ?></dd>
                        <dt>発送</dt>
                        <dd><?= h((string) $shippingWarranty['delivery_days']) ?></dd>
                        <dt>保証</dt>
                        <dd><?= h((string) $shippingWarranty['warranty']) ?></dd>
                        <dt>返品</dt>
                        <dd><?= h((string) $shippingWarranty['return_policy']) ?></dd>
                    </dl>
                </section>

                <!-- 関連商品 -->
                <?php if (!empty($relatedProducts)): ?>
                    <section class="pd-panel" id="pd-panel-related">
                        <h2 class="pd-panel-title d-lg-none">関連商品</h2>
                        <div class="pd-related-scroll">
                            <?php foreach ($relatedProducts as $related): ?>
                                <a class="pd-related-card" href="product-detail.php?id=<?= (int) $related['product_id'] ?>">
                                    <div class="prod-thumb">
                                        <?php if (!empty($related['image_url'])): ?>
                                            <img src="<?= h((string) $related['image_url']) ?>" alt="<?= h((string) $related['name']) ?>" class="prod-image">
                                        <?php else: ?>
                                            <span class="thumb-icon" aria-hidden="true">&#128690;</span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="pd-related-name"><?= h((string) $related['name']) ?></p>
                                    <p class="pd-related-price"><?= number_format((float) $related['price']) ?>円</p>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>

            </div>

        </div>

    <?php endif; ?>
</main>

<?php if ($errorMessage === null): ?>
<script src="<?= h($baseUrl) ?>assets/js/product-detail.js"></script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
