CREATE TABLE IF NOT EXISTS deployments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    type ENUM('cms', 'frontend', 'module', 'combined') NOT NULL DEFAULT 'cms',
    version_from VARCHAR(50) NULL,
    version_to VARCHAR(50) NULL,
    status ENUM('pending', 'running', 'success', 'failed', 'rolled_back') NOT NULL DEFAULT 'pending',
    log TEXT NULL,
    triggered_by VARCHAR(50) NOT NULL DEFAULT 'manual',
    started_at DATETIME NULL,
    finished_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_customer (customer_id),
    INDEX idx_status (status),
    INDEX idx_created (created_at),
    CONSTRAINT fk_deployments_customer FOREIGN KEY (customer_id)
        REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
