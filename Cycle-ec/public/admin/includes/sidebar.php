<aside class="admin-sidebar" id="admin-sidebar">
    <div class="admin-sidebar-heading">MENU</div>
    <nav class="nav flex-column gap-1" aria-label="管理メニュー">
        <a class="admin-nav-link <?= $activeMenu === 'dashboard' ? 'active' : '' ?>" href="<?= $baseUrl ?>admin/index.php"><span class="admin-nav-icon">⌂</span>ダッシュボード</a>
        <a class="admin-nav-link <?= $activeMenu === 'products' ? 'active' : '' ?>" href="<?= $baseUrl ?>admin/products/index.php"><span class="admin-nav-icon">▣</span>商品管理</a>
        <a class="admin-nav-link <?= $activeMenu === 'categories' ? 'active' : '' ?>" href="<?= $baseUrl ?>admin/categories.php"><span class="admin-nav-icon">◆</span>カテゴリ管理</a>
        <a class="admin-nav-link <?= $activeMenu === 'orders' ? 'active' : '' ?>" href="<?= $baseUrl ?>admin/orders.php"><span class="admin-nav-icon">≡</span>注文管理</a>
        <a class="admin-nav-link <?= $activeMenu === 'discounts' ? 'active' : '' ?>" href="<?= $baseUrl ?>admin/discounts.php"><span class="admin-nav-icon">%</span>割引管理</a>
    </nav>
    <div class="admin-sidebar-divider"></div>
    <div class="admin-sidebar-heading">その他</div>
    <nav class="nav flex-column gap-1">
        <a class="admin-nav-link <?= $activeMenu === 'mail' ? 'active' : '' ?>" href="<?= $baseUrl ?>pages/mail_test.php"><span class="admin-nav-icon">@</span>メール送信テスト</a>
        <span class="admin-nav-link disabled"><span class="admin-nav-icon">?</span>問い合わせ <small>準備中</small></span>
    </nav>
</aside>