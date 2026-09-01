-- Optionales Seiten-Icon fuer Navigation und verlinkende Frontend-Komponenten.
-- Leer bedeutet: Das Frontend verwendet das globale Favicon.

SET @has_col := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pages'
    AND COLUMN_NAME = 'page_icon_media_id'
);
SET @sql := IF(
  @has_col = 0,
  'ALTER TABLE `pages` ADD COLUMN `page_icon_media_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `nav_label`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
