-- 038_events_subtitle.sql
-- Adds optional subtitle per event (MySQL < 8 compatible idempotent pattern)

SET @has_col := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'events'
    AND COLUMN_NAME = 'subtitle'
);
SET @sql := IF(
  @has_col = 0,
  'ALTER TABLE `events` ADD COLUMN `subtitle` VARCHAR(255) NULL AFTER `title`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

