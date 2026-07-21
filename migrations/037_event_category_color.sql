-- 037_event_category_color.sql
-- Adds configurable color per event category for calendar/event highlighting
-- MySQL < 8 compatible idempotent pattern

SET @has_col := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'event_categories'
    AND COLUMN_NAME = 'color_hex'
);
SET @sql := IF(
  @has_col = 0,
  'ALTER TABLE `event_categories` ADD COLUMN `color_hex` VARCHAR(7) NULL DEFAULT NULL AFTER `slug`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
