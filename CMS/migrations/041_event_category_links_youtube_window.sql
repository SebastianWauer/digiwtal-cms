-- 041_event_category_links_youtube_window.sql
-- Adds optional start/end datetime window for YouTube links per event-category link
-- MySQL < 8 compatible idempotent pattern

SET @has_start := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'event_category_links'
    AND COLUMN_NAME = 'youtube_start_at'
);
SET @sql_start := IF(
  @has_start = 0,
  'ALTER TABLE `event_category_links` ADD COLUMN `youtube_start_at` DATETIME NULL DEFAULT NULL AFTER `pdf_media_id`',
  'SELECT 1'
);
PREPARE stmt_start FROM @sql_start;
EXECUTE stmt_start;
DEALLOCATE PREPARE stmt_start;

SET @has_end := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'event_category_links'
    AND COLUMN_NAME = 'youtube_end_at'
);
SET @sql_end := IF(
  @has_end = 0,
  'ALTER TABLE `event_category_links` ADD COLUMN `youtube_end_at` DATETIME NULL DEFAULT NULL AFTER `youtube_start_at`',
  'SELECT 1'
);
PREPARE stmt_end FROM @sql_end;
EXECUTE stmt_end;
DEALLOCATE PREPARE stmt_end;
