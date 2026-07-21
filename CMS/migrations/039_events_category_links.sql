-- 039_events_category_links.sql
-- Mehrere Links pro Event-Kategorie (mit Label + URL)

CREATE TABLE IF NOT EXISTS `event_category_links` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_id` BIGINT UNSIGNED NOT NULL,
  `category_id` BIGINT UNSIGNED NOT NULL,
  `link_type` VARCHAR(20) NOT NULL DEFAULT 'link',
  `label` VARCHAR(120) NOT NULL,
  `url` VARCHAR(2048) NULL DEFAULT NULL,
  `pdf_media_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `sort_order` INT NOT NULL DEFAULT 10,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_event_category_links_event` (`event_id`, `category_id`, `sort_order`),
  KEY `idx_event_category_links_pdf_media` (`pdf_media_id`),
  CONSTRAINT `fk_event_category_links_event`
    FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_event_category_links_category`
    FOREIGN KEY (`category_id`) REFERENCES `event_categories`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_event_category_links_pdf_media`
    FOREIGN KEY (`pdf_media_id`) REFERENCES `media_items`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
