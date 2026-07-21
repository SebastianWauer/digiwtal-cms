ALTER TABLE customers
    ADD COLUMN abo_status ENUM('active', 'cancelled', 'suspended') NOT NULL DEFAULT 'active' AFTER is_active,
    ADD COLUMN email VARCHAR(255) NOT NULL DEFAULT '' AFTER name,
    ADD COLUMN notes TEXT NULL AFTER email,
    ADD INDEX idx_abo_status (abo_status);
