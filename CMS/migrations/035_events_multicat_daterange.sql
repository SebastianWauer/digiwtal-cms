-- 035_events_multicat_daterange.sql
-- Events: date range (from/to without time) + multiple categories

-- Add date range columns (idempotent, MySQL < 8 compatible)
SET @has_col := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'events'
    AND COLUMN_NAME = 'event_date_from'
);
SET @sql := IF(
  @has_col = 0,
  'ALTER TABLE `events` ADD COLUMN `event_date_from` DATE NULL AFTER `description`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_col := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'events'
    AND COLUMN_NAME = 'event_date_to'
);
SET @sql := IF(
  @has_col = 0,
  'ALTER TABLE `events` ADD COLUMN `event_date_to` DATE NULL AFTER `event_date_from`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Backfill from old datetime column if available
UPDATE `events`
SET
  `event_date_from` = COALESCE(`event_date_from`, DATE(`event_date`)),
  `event_date_to` = COALESCE(`event_date_to`, DATE(`event_date`))
WHERE `event_date` IS NOT NULL;

-- M:N mapping table event <-> categories
CREATE TABLE IF NOT EXISTS `event_category_map` (
  `event_id` BIGINT UNSIGNED NOT NULL,
  `category_id` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`event_id`, `category_id`),
  KEY `idx_event_category_map_category` (`category_id`, `event_id`),
  CONSTRAINT `fk_event_category_map_event`
    FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_event_category_map_category`
    FOREIGN KEY (`category_id`) REFERENCES `event_categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Backfill mapping from legacy single category column
INSERT IGNORE INTO `event_category_map` (`event_id`, `category_id`)
SELECT `id`, `category_id`
FROM `events`
WHERE `category_id` IS NOT NULL;
