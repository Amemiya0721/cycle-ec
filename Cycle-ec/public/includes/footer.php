    <?php
    require_once __DIR__ . '/../../src/Category.php';
    $footerCategories = [];
    try {
        $footerCategories = Category::all();
    } catch (Throwable $exception) {
        error_log('Footer category loading failed: ' . $exception->getMessage());
    }
    ?>
    <!-- FOOTER -->
    <footer>
        <div class="container">
            <div class="row g-4 footer-grid">
                <div class="col-6 col-lg-3">
                    <div class="footer-logo">OVERHAUL</div>
                    <div class="footer-sub">USED BIKE &amp; PARTS</div>
                    <p class="footer-desc">中古ロードバイク・自転車パーツ専門店。<br>使えるものを必要な人へ。</p>
                    <div class="sns d-flex gap-2">
                        <a href="#" aria-label="Instagram">IG</a>
                        <a href="#" aria-label="X">X</a>
                        <a href="#" aria-label="YouTube">YT</a>
                    </div>
                </div>
                <div class="col-6 col-lg-2 footer-col">
                    <h5>商品を探す</h5>
                    <ul class="list-unstyled">
                        <li><a href="<?= $baseUrl ?>pages/products.php">商品一覧</a></li>
                        <?php foreach ($footerCategories as $category): ?>
                            <li><a href="<?= htmlspecialchars(Category::productsUrl((int) $category['category_id'], $baseUrl), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $category['name'], ENT_QUOTES, 'UTF-8') ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="col-6 col-lg-2 footer-col">
                    <h5>サポート</h5>
                    <ul class="list-unstyled">
                        <li><a href="<?= $baseUrl ?>pages/contact.php">お問い合わせ</a></li>
                        <li><a href="<?= $baseUrl ?>pages/terms.php#shipping">配送について</a></li>
                        <li><a href="<?= $baseUrl ?>pages/terms.php#returns">返品・返金について</a></li>
                        <li><a href="<?= $baseUrl ?>pages/terms.php#used">中古商品について</a></li>
                        <li><a href="<?= $baseUrl ?>pages/terms.php#junk">ジャンク品について</a></li>
                        <li><a href="<?= $baseUrl ?>pages/terms.php">利用規約</a></li>
                    </ul>
                </div>
                <div class="col-6 col-lg-2 footer-col">
                    <h5>ABOUT</h5>
                    <ul class="list-unstyled">
                        <li><a href="<?= $baseUrl ?>index.php#about">OVERHAULについて</a></li>
                        <li><a href="#">ご利用ガイド</a></li>
                        <li><a href="#">特定商取引法に基づく表記</a></li>
                        <li><a href="#">プライバシーポリシー</a></li>
                        <li><a href="#">サイトマップ</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">© OVERHAUL All Rights Reserved.</div>
        </div>
    </footer>
    <!-- Bootstrap JS -->
    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
    </script>

    <!-- 共通JS -->
    <script src="/assets/js/main.js"></script>
    </body>

    </html>