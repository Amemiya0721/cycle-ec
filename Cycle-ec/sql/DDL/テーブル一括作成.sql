-- ============================================================
-- OVERHAUL / cycle-ec
-- Database Definition Language
-- MySQL 8.0+
-- ============================================================

CREATE DATABASE IF NOT EXISTS cycle_ec
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE cycle_ec;


-- ============================================================
-- USERS
-- ユーザー情報
-- ============================================================

CREATE TABLE users (
    user_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    name VARCHAR(100) NOT NULL,
    icon_url VARCHAR(500) NULL,
    email VARCHAR(255) NOT NULL,
    password VARCHAR(255) NOT NULL,
    is_admin TINYINT(1) NOT NULL DEFAULT 0,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_users_email (email)
) ENGINE=InnoDB;


-- ============================================================
-- USER_ADDRESSES
-- ユーザー配送先住所
-- ============================================================

CREATE TABLE user_addresses (
    address_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    user_id INT UNSIGNED NOT NULL,

    postal_code VARCHAR(10) NOT NULL,
    prefecture VARCHAR(50) NOT NULL,
    city VARCHAR(100) NOT NULL,
    address_line VARCHAR(255) NOT NULL,
    building VARCHAR(255),

    recipient_name VARCHAR(100) NOT NULL,
    phone_number VARCHAR(20) NOT NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_user_addresses_user
        FOREIGN KEY (user_id)
        REFERENCES users (user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    UNIQUE KEY uq_user_addresses_user_id (user_id)
) ENGINE=InnoDB;


-- ============================================================
-- CATEGORIES
-- 商品カテゴリ
-- ============================================================

CREATE TABLE categories (
    category_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    name VARCHAR(100) NOT NULL,
    icon_url VARCHAR(500) NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_categories_name (name),
    INDEX idx_categories_sort_order (sort_order, category_id)
) ENGINE=InnoDB;


-- ============================================================
-- SHIPPING_RATES
-- 配送サイズ別送料
-- ============================================================

CREATE TABLE shipping_rates (
    shipping_rate_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    shipping_size VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    shipping_fee DECIMAL(10,2) NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_shipping_rates_size (shipping_size),
    UNIQUE KEY uk_shipping_rates_sort_order (sort_order),
    INDEX idx_shipping_rates_order (sort_order, shipping_rate_id)
) ENGINE=InnoDB;

INSERT INTO shipping_rates (shipping_size, sort_order, shipping_fee, is_active)
VALUES
    ('小', 10, 700.00, TRUE),
    ('中', 20, 1200.00, TRUE),
    ('大', 30, 2000.00, TRUE);


-- ============================================================
-- PRODUCTS
-- 商品情報
-- ============================================================

CREATE TABLE products (
    product_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    category_id INT UNSIGNED NOT NULL,

    name VARCHAR(255) NOT NULL,
    description TEXT,

    price DECIMAL(10,2) NOT NULL,
    tax_rate DECIMAL(5,2) NOT NULL DEFAULT 10.00,

    shipping_size VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '大',
    status VARCHAR(50) NOT NULL DEFAULT 'on_sale',
    product_condition VARCHAR(50) NOT NULL DEFAULT 'used',
    stock_quantity INT UNSIGNED NOT NULL DEFAULT 0,
    average_cost DECIMAL(10,2) NULL,

    is_deleted BOOLEAN NOT NULL DEFAULT FALSE,
    is_recommended BOOLEAN NOT NULL DEFAULT FALSE,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_products_category
        FOREIGN KEY (category_id)
        REFERENCES categories (category_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT fk_products_shipping_size
        FOREIGN KEY (shipping_size)
        REFERENCES shipping_rates (shipping_size)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    INDEX idx_products_category_id (category_id),
    INDEX idx_products_shipping_size (shipping_size),
    INDEX idx_products_status (status),
    INDEX idx_products_is_deleted (is_deleted)
) ENGINE=InnoDB;


-- ============================================================
-- PRODUCT_IMAGES
-- 商品画像
-- ============================================================

CREATE TABLE product_images (
    image_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    product_id INT UNSIGNED NOT NULL,

    image_url VARCHAR(500) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_product_images_product
        FOREIGN KEY (product_id)
        REFERENCES products (product_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    INDEX idx_product_images_product_id (product_id),
    INDEX idx_product_images_sort_order (product_id, sort_order)
) ENGINE=InnoDB;


-- ============================================================
-- PRODUCT_PRICE_HISTORY
-- 商品価格履歴
-- ============================================================

CREATE TABLE product_price_history (
    price_history_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    product_id INT UNSIGNED NOT NULL,

    price DECIMAL(10,2) NOT NULL,
    tax_rate DECIMAL(5,2) NOT NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_product_price_history_product
        FOREIGN KEY (product_id)
        REFERENCES products (product_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    INDEX idx_product_price_history_product_id (product_id),
    INDEX idx_product_price_history_created_at (product_id, created_at)
) ENGINE=InnoDB;


-- ============================================================
-- ORDERS
-- 注文情報
-- ============================================================

CREATE TABLE orders (
    order_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    user_id INT UNSIGNED NOT NULL,

    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    shipping_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    shipping_size VARCHAR(100) NULL,
    shipping_rate_id INT UNSIGNED NULL,
    total_price DECIMAL(10,2) NOT NULL,

    status VARCHAR(50) NOT NULL DEFAULT 'pending',
    payment_method VARCHAR(32) NOT NULL DEFAULT 'bank_transfer',
    payment_status VARCHAR(20) NOT NULL DEFAULT 'unpaid',
    payment_due_at DATETIME NULL,
    paid_at DATETIME NULL,

    -- 注文時点の配送先を保存
    shipping_postal_code VARCHAR(10) NOT NULL,
    shipping_prefecture VARCHAR(50) NOT NULL,
    shipping_city VARCHAR(100) NOT NULL,
    shipping_address_line VARCHAR(255) NOT NULL,
    shipping_building VARCHAR(255),

    shipping_recipient_name VARCHAR(100) NOT NULL,
    shipping_phone_number VARCHAR(20) NOT NULL,

    ordered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_orders_user
        FOREIGN KEY (user_id)
        REFERENCES users (user_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT fk_orders_shipping_rate
        FOREIGN KEY (shipping_rate_id)
        REFERENCES shipping_rates (shipping_rate_id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

    INDEX idx_orders_user_id (user_id),
    INDEX idx_orders_shipping_rate_id (shipping_rate_id),
    INDEX idx_orders_status (status),
    INDEX idx_orders_payment_status_due_at (payment_status, payment_due_at),
    INDEX idx_orders_ordered_at (ordered_at)
) ENGINE=InnoDB;


-- ============================================================
-- ORDER_ITEMS
-- 注文明細
-- ============================================================

CREATE TABLE order_items (
    order_item_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    order_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,

    -- 注文時点の商品価格を保持
    price DECIMAL(10,2) NOT NULL,
    cost_price DECIMAL(10,2) NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_order_items_order
        FOREIGN KEY (order_id)
        REFERENCES orders (order_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_order_items_product
        FOREIGN KEY (product_id)
        REFERENCES products (product_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    INDEX idx_order_items_order_id (order_id),
    INDEX idx_order_items_product_id (product_id)
) ENGINE=InnoDB;


-- ============================================================
-- INVENTORY_PURCHASES
-- 仕入記録と期首在庫原価（仕入登録時に在庫と移動平均原価を更新）
-- ============================================================

CREATE TABLE inventory_purchases (
    purchase_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    record_type ENUM('opening_balance', 'purchase') NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    unit_cost DECIMAL(10,2) NOT NULL,
    total_cost DECIMAL(20,2) NOT NULL,
    purchase_date DATE NOT NULL,
    notes VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_inventory_purchases_product
        FOREIGN KEY (product_id)
        REFERENCES products (product_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    INDEX idx_inventory_purchases_date (purchase_date, purchase_id),
    INDEX idx_inventory_purchases_product (product_id, purchase_date)
) ENGINE=InnoDB;


-- ============================================================
-- OPERATING_EXPENSES
-- 商品仕入とは区別する運営経費
-- ============================================================

CREATE TABLE operating_expenses (
    expense_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    expense_date DATE NOT NULL,
    category VARCHAR(80) NOT NULL,
    description VARCHAR(255) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_operating_expenses_date (expense_date, expense_id)
) ENGINE=InnoDB;


-- ============================================================
-- BANK_TRANSFER_SETTINGS
-- 管理画面で設定する銀行振込先（購入者にはメールでのみ案内）
-- ============================================================

CREATE TABLE bank_transfer_settings (
    settings_id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    bank_name VARCHAR(100) NOT NULL DEFAULT '',
    branch_name VARCHAR(100) NOT NULL DEFAULT '',
    account_type VARCHAR(30) NOT NULL DEFAULT '',
    account_number VARCHAR(50) NOT NULL DEFAULT '',
    account_holder VARCHAR(100) NOT NULL DEFAULT '',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO bank_transfer_settings (settings_id)
VALUES (1);


-- ============================================================
-- PASSWORD_RESET_TOKENS
-- 期限付き・使い捨てのパスワード再設定トークン（ハッシュのみ保存）
-- ============================================================

CREATE TABLE password_reset_tokens (
    user_id INT UNSIGNED NOT NULL PRIMARY KEY,
    token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_password_reset_tokens_user
        FOREIGN KEY (user_id)
        REFERENCES users (user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    UNIQUE KEY uq_password_reset_tokens_hash (token_hash),
    INDEX idx_password_reset_tokens_expires_at (expires_at)
) ENGINE=InnoDB;