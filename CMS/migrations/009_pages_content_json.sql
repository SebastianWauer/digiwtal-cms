-- 010_pages_content_json.sql
-- Adds pages.content_json if missing (MySQL/MariaDB safe-ish dynamic SQL)

SET @db := DATABASE();

SET @has_col := (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = @db
    AND table_name = 'pages'
    AND column_name = 'content_json'
);

SET @sql := IF(@has_col = 0,
  'ALTER TABLE pages ADD COLUMN content_json LONGTEXT NOT NULL',
  'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Optional: ensure default JSON for existing rows (only if column exists)
-- (Safe even if already filled; it only fills empty strings)
UPDATE pages
SET content_json = '{"blocks":[]}'
WHERE (content_json IS NULL OR content_json = '');
