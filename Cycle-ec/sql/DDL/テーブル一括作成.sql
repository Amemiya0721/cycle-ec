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

    INDEX idx_user_addresses_user_id (user_id)
) ENGINE=InnoDB;


-- ============================================================
-- CATEGORIES
-- 商品カテゴリ
-- ============================================================

CREATE TABLE categories (
    category_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    name VARCHAR(100) NOT NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_categories_name (name)
) ENGINE=InnoDB;


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

    status VARCHAR(50) NOT NULL DEFAULT 'on_sale',
    product_condition VARCHAR(50) NOT NULL DEFAULT 'used',

    is_deleted BOOLEAN NOT NULL DEFAULT FALSE,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_products_category
        FOREIGN KEY (category_id)
        REFERENCES categories (category_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    INDEX idx_products_category_id (category_id),
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

    total_price DECIMAL(10,2) NOT NULL,

    status VARCHAR(50) NOT NULL DEFAULT 'pending',

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

    INDEX idx_orders_user_id (user_id),
    INDEX idx_orders_status (status),
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