-- ============================================================
-- Migration: 商品ステータスを英語コードに統一
-- 作成日: 2026-11-03
--
-- 商品ステータスを on_sale / sold_out / draft に統一する。
-- ============================================================

USE cycle_ec;

UPDATE products
SET status = CASE
    WHEN status IN ('販売中', 'new_arrival') THEN 'on_sale'
    WHEN status IN ('売切れ', 'SOLD') THEN 'sold_out'
    WHEN status = '下書き' THEN 'draft'
    ELSE status
END
WHERE status IN ('販売中', 'new_arrival', '売切れ', 'SOLD', '下書き');
