CREATE TABLE IF NOT EXISTS health_checks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    checked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status ENUM('healthy', 'degraded', 'down', 'timeout') NOT NULL DEFAULT 'down',
    cms_version VARCHAR(50) NULL,
    frontend_version VARCHAR(50) NULL,
    php_version VARCHAR(50) NULL,
    response_ms INT DEFAULT 0,
    raw_response JSON NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_customer_checked (customer_id, checked_at),
    INDEX idx_status (status),
    CONSTRAINT fk_health_checks_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
