CREATE TABLE admin_login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_time (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE admin_login_attempts
    ADD COLUMN attempt_type VARCHAR(10) NOT NULL DEFAULT 'login' AFTER ip_address;
