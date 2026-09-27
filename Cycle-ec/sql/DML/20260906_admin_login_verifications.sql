ALTER TABLE users
    ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER password;

CREATE TABLE admin_login_verifications (
    verification_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    code_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_admin_login_verifications_user
        FOREIGN KEY (user_id)
        REFERENCES users (user_id)
        ON DELETE CASCADE,
    INDEX idx_admin_login_verifications_user (user_id),
    INDEX idx_admin_login_verifications_expiry (expires_at)
) ENGINE=InnoDB;