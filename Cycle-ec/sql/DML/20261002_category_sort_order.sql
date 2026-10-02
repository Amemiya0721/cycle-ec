-- ============================================================
-- Migration: カテゴリ表示順
-- 作成日: 2026-10-02
--
-- 既存カテゴリは現在の category_id 昇順を初期表示順として引き継ぐ。
-- category_id 自体や商品との関連は変更しない。
-- ============================================================

USE cycle_ec;

ALTER TABLE categories
    ADD COLUMN sort_order INT UNSIGNED NOT NULL DEFAULT 0 AFTER name;

UPDATE categories
SET sort_order = category_id;

ALTER TABLE categories
    ADD INDEX idx_categories_sort_order (sort_order, category_id);

-- ロールバック用（通常実行しない）
-- ALTER TABLE categories DROP INDEX idx_categories_sort_order, DROP COLUMN sort_order;