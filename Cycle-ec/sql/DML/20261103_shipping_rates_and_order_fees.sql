-- ============================================================
-- Migration: 商品配送サイズ・送料管理
-- 作成日: 2026-11-03
--
-- 商品は配送サイズを保持し、注文には確定時の送料とサイズを保存する。
-- ============================================================

USE cycle_ec;

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

ALTER TABLE products
    ADD COLUMN shipping_size VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '大' AFTER tax_rate,
    ADD INDEX idx_products_shipping_size (shipping_size),
    ADD CONSTRAINT fk_products_shipping_size
        FOREIGN KEY (shipping_size)
        REFERENCES shipping_rates (shipping_size)
        ON DELETE RESTRICT
        ON UPDATE CASCADE;

ALTER TABLE orders
    ADD COLUMN subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER user_id,
    ADD COLUMN shipping_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER subtotal,
    ADD COLUMN shipping_size VARCHAR(100) NULL AFTER shipping_fee,
    ADD COLUMN shipping_rate_id INT UNSIGNED NULL AFTER shipping_size,
    ADD INDEX idx_orders_shipping_rate_id (shipping_rate_id),
    ADD CONSTRAINT fk_orders_shipping_rate
        FOREIGN KEY (shipping_rate_id)
        REFERENCES shipping_rates (shipping_rate_id)
        ON DELETE SET NULL
        ON UPDATE CASCADE;

UPDATE orders SET subtotal = total_price;
