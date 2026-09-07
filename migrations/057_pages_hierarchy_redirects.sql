-- Unterseiten (Eltern-Kind-Hierarchie) und Weiterleitungs-Seiten.
--
-- `slug` bleibt wie bisher der volle, berechnete Pfad (z.B. "/leistungen/beratung")
-- und wird weiterhin fuer Anzeige, Sitemap, Eindeutigkeit und Routing benutzt.
-- `slug_segment` ist das eigene Pfadstueck, das im Editor gepflegt wird (kein "/").
-- Bei Seiten ohne Elternseite ist slug_segment der bisherige Slug ohne die
-- fuehrenden/abschliessenden Schraegstriche.

SET @has_col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pages' AND COLUMN_NAME = 'parent_id'
);
SET @sql := IF(@has_col = 0,
  'ALTER TABLE `pages` ADD COLUMN `parent_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `id`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pages' AND COLUMN_NAME = 'slug_segment'
);
SET @sql := IF(@has_col = 0,
  'ALTER TABLE `pages` ADD COLUMN `slug_segment` VARCHAR(190) NOT NULL DEFAULT '''' AFTER `slug`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pages' AND COLUMN_NAME = 'redirect_type'
);
SET @sql := IF(@has_col = 0,
  'ALTER TABLE `pages` ADD COLUMN `redirect_type` ENUM(''none'',''page'',''url'') NOT NULL DEFAULT ''none'' AFTER `slug_segment`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pages' AND COLUMN_NAME = 'redirect_target_page_id'
);
SET @sql := IF(@has_col = 0,
  'ALTER TABLE `pages` ADD COLUMN `redirect_target_page_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `redirect_type`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pages' AND COLUMN_NAME = 'redirect_target_url'
);
SET @sql := IF(@has_col = 0,
  'ALTER TABLE `pages` ADD COLUMN `redirect_target_url` VARCHAR(500) NULL DEFAULT NULL AFTER `redirect_target_page_id`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_idx := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pages' AND INDEX_NAME = 'idx_pages_parent_id'
);
SET @sql := IF(@has_idx = 0,
  'ALTER TABLE `pages` ADD INDEX `idx_pages_parent_id` (`parent_id`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Bestandsseiten: eigenes Pfadstueck aus dem heutigen Slug ableiten.
UPDATE pages SET slug_segment = TRIM(BOTH '/' FROM slug) WHERE slug_segment = '' AND slug <> '/';
