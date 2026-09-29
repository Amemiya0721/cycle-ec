-- ============================================================
-- Migration: 住所登録・注文フロー・管理画面の注文確認
-- 作成日: 2026-09-29
--
-- 1) user_addresses を「1ユーザー1住所」に固定する（UNIQUE制約）
-- 2) 管理画面の注文一覧用にインデックスを追加する
--
-- 実行前の確認（重複があると UNIQUE 追加が失敗する）:
--   SELECT user_id, COUNT(*) FROM user_addresses GROUP BY user_id HAVING COUNT(*) > 1;
-- ============================================================

USE cycle_ec;

ALTER TABLE user_addresses
    ADD UNIQUE INDEX uq_user_addresses_user_id (user_id);

ALTER TABLE orders
    ADD INDEX idx_orders_ordered_at (ordered_at),
    ADD INDEX idx_orders_status (status, ordered_at);

-- ロールバック用（通常実行しない）
-- ALTER TABLE orders DROP INDEX idx_orders_status, DROP INDEX idx_orders_ordered_at;
-- ALTER TABLE user_addresses DROP INDEX uq_user_addresses_user_id;
