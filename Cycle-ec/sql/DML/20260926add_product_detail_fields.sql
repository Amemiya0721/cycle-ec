-- ============================================================
-- Migration: 商品詳細ページ拡張
-- 作成日: 2026-09-26
-- 目的: 商品詳細ページに必要な項目（メーカー、在庫数、
--       スタッフコメント、傷写真区分、スペック、付属品）を追加する。
--
-- 対象外（意図的に含めない）:
--   - レビュー・評価           … 今回のスコープ外
--   - 配送・保証情報           … 全商品共通のため config/shipping_warranty.php で対応。DB変更なし。
--
-- 既存データへの影響:
--   - products への3カラム追加は NULL / DEFAULT 付きのため、
--     既存行に対する更新処理は不要（既存レコードは自動的にデフォルト値になる）。
--   - product_images への image_type 追加は DEFAULT 'main' のため、
--     既存の全画像は自動的に「メイン画像」として扱われ、表示崩れは起きない。
-- ============================================================

USE cycle_ec;

-- ------------------------------------------------------------
-- products: メーカー・在庫数・スタッフコメントを追加
-- ------------------------------------------------------------
ALTER TABLE products
    ADD COLUMN manufacturer   VARCHAR(100) NULL              AFTER category_id,
    ADD COLUMN stock_quantity INT UNSIGNED NOT NULL DEFAULT 0 AFTER product_condition,
    ADD COLUMN staff_comment  TEXT NULL                       AFTER stock_quantity;

-- ------------------------------------------------------------
-- product_images: メイン画像 / 傷・使用感写真の区分を追加
--   'main'      … 通常の商品画像（既存データはすべてこちらに分類される）
--   'condition' … 商品状態タブに表示する傷・使用感の写真
-- ------------------------------------------------------------
ALTER TABLE product_images
    ADD COLUMN image_type VARCHAR(20) NOT NULL DEFAULT 'main' AFTER image_url;

ALTER TABLE product_images
    ADD INDEX idx_product_images_type (product_id, image_type, sort_order);

-- ------------------------------------------------------------
-- product_specs: スペック（モデル・年式・サイズ・素材・重量・対応規格 等）
--   key-value 形式にすることで、将来の項目追加時にカラム追加が不要。
-- ------------------------------------------------------------
CREATE TABLE product_specs (
    spec_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    product_id INT UNSIGNED NOT NULL,

    spec_key   VARCHAR(100) NOT NULL,
    spec_value VARCHAR(255) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_product_specs_product
        FOREIGN KEY (product_id)
        REFERENCES products (product_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    INDEX idx_product_specs_product_id (product_id, sort_order)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- product_accessories: 付属品（可変長リスト）
-- ------------------------------------------------------------
CREATE TABLE product_accessories (
    accessory_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    product_id INT UNSIGNED NOT NULL,

    content    VARCHAR(255) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_product_accessories_product
        FOREIGN KEY (product_id)
        REFERENCES products (product_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    INDEX idx_product_accessories_product_id (product_id, sort_order)
) ENGINE=InnoDB;

-- ============================================================
-- ロールバック用（切り戻しが必要な場合に使用。通常実行しない）
-- ============================================================
-- DROP TABLE IF EXISTS product_accessories;
-- DROP TABLE IF EXISTS product_specs;
-- ALTER TABLE product_images DROP INDEX idx_product_images_type, DROP COLUMN image_type;
-- ALTER TABLE products DROP COLUMN manufacturer, DROP COLUMN stock_quantity, DROP COLUMN staff_comment;
