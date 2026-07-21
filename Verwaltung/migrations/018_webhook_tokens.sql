CREATE TABLE IF NOT EXISTS webhook_tokens (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    customer_id  INT NOT NULL,
    token_enc    VARCHAR(500) NOT NULL,
    token_nonce  VARCHAR(100) NOT NULL,
    token_tag    VARCHAR(100) NOT NULL,
    deploy_type  ENUM('cms', 'frontend', 'full') NOT NULL DEFAULT 'cms',
    label        VARCHAR(100) NOT NULL DEFAULT '',
    last_used_at DATETIME NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_customer (customer_id),
    CONSTRAINT fk_webhook_customer FOREIGN KEY (customer_id)
        REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
