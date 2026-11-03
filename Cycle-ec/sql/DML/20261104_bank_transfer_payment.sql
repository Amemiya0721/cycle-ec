-- 既存注文も未入金として移行する。発送前に管理画面で入金確認が必要。
ALTER TABLE orders
    ADD COLUMN payment_method VARCHAR(32) NOT NULL DEFAULT 'bank_transfer' AFTER status,
    ADD COLUMN payment_status VARCHAR(20) NOT NULL DEFAULT 'unpaid' AFTER payment_method,
    ADD COLUMN payment_due_at DATETIME NULL AFTER payment_status,
    ADD COLUMN paid_at DATETIME NULL AFTER payment_due_at,
    ADD INDEX idx_orders_payment_status_due_at (payment_status, payment_due_at);

UPDATE orders
SET payment_due_at = DATE_ADD(ordered_at, INTERVAL 3 DAY)
WHERE payment_due_at IS NULL;

UPDATE orders
SET payment_status = 'cancelled'
WHERE HEX(status) = 'E382ADE383A3E383B3E382BBE383AB';

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
