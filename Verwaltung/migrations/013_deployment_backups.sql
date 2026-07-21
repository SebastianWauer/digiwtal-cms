CREATE TABLE IF NOT EXISTS deployment_backups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    deployment_id INT NOT NULL,
    customer_id INT NOT NULL,
    backup_path VARCHAR(500) NOT NULL,
    file_count INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_deployment (deployment_id),
    INDEX idx_customer (customer_id),
    CONSTRAINT fk_backup_deployment FOREIGN KEY (deployment_id)
        REFERENCES deployments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
