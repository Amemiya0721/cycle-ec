USE cycle_ec;

ALTER TABLE products
    ADD COLUMN average_cost DECIMAL(10,2) NULL AFTER stock_quantity;

ALTER TABLE order_items
    ADD COLUMN cost_price DECIMAL(10,2) NULL AFTER price;

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

CREATE TABLE operating_expenses (
    expense_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    expense_date DATE NOT NULL,
    category VARCHAR(80) NOT NULL,
    description VARCHAR(255) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_operating_expenses_date (expense_date, expense_id)
) ENGINE=InnoDB;
