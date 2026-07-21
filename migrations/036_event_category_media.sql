-- 036_event_category_media.sql
-- Per-event image per category

CREATE TABLE IF NOT EXISTS `event_category_media` (
  `event_id` BIGINT UNSIGNED NOT NULL,
  `category_id` BIGINT UNSIGNED NOT NULL,
  `media_id` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`event_id`, `category_id`),
  KEY `idx_event_category_media_media` (`media_id`),
  CONSTRAINT `fk_event_category_media_event`
    FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_event_category_media_category`
    FOREIGN KEY (`category_id`) REFERENCES `event_categories`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_event_category_media_media`
    FOREIGN KEY (`media_id`) REFERENCES `media_items`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Optional backfill: if event already has a base image, assign it to all mapped categories.
INSERT IGNORE INTO `event_category_media` (`event_id`, `category_id`, `media_id`)
SELECT ecm.event_id, ecm.category_id, e.image_media_id
FROM event_category_map ecm
JOIN events e ON e.id = ecm.event_id
WHERE e.image_media_id IS NOT NULL
  AND e.image_media_id > 0;

