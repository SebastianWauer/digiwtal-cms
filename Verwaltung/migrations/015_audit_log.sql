CREATE TABLE IF NOT EXISTS audit_log (
    id          BIGINT AUTO_INCREMENT PRIMARY KEY,
    admin_id    INT          NULL,
    admin_email VARCHAR(255) NOT NULL DEFAULT '',
    action      VARCHAR(100) NOT NULL,
    entity      VARCHAR(50)  NOT NULL DEFAULT '',
    entity_id   INT          NULL,
    detail      TEXT         NULL,
    ip          VARCHAR(45)  NOT NULL DEFAULT '',
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_action    (action),
    INDEX idx_entity    (entity, entity_id),
    INDEX idx_admin     (admin_id),
    INDEX idx_created   (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
