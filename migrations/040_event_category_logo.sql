-- 040_event_category_logo.sql
-- Adds optional logo media reference per event category (series logo)
-- MySQL < 8 compatible idempotent pattern

SET @has_col := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'event_categories'
    AND COLUMN_NAME = 'logo_media_id'
);
SET @sql := IF(
  @has_col = 0,
  'ALTER TABLE `event_categories` ADD COLUMN `logo_media_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `color_hex`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
