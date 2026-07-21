CREATE TABLE IF NOT EXISTS page_revisions (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    page_id      BIGINT UNSIGNED NOT NULL,
    content_json LONGTEXT        NOT NULL,
    title        VARCHAR(255)    NOT NULL,
    created_by   BIGINT UNSIGNED NULL DEFAULT NULL,
    created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_page_revisions_page_id (page_id),
    KEY idx_page_revisions_created_at (created_at),
    CONSTRAINT fk_page_revisions_page
      FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
