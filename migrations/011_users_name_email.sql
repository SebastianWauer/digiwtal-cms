-- 011_users_name_email.sql
-- Name + E-Mail für Benutzer (nullable), E-Mail unique wenn gesetzt

SET @db := DATABASE();

-- name
SET @has_name := (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = @db AND table_name = 'users' AND column_name = 'name'
);
SET @sql_name := IF(
  @has_name = 0,
  "ALTER TABLE users ADD COLUMN name VARCHAR(190) NOT NULL DEFAULT ''",
  "SELECT 1"
);
PREPARE stmt FROM @sql_name; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- email
SET @has_email := (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = @db AND table_name = 'users' AND column_name = 'email'
);
SET @sql_email := IF(
  @has_email = 0,
  "ALTER TABLE users ADD COLUMN email VARCHAR(190) NULL",
  "SELECT 1"
);
PREPARE stmt FROM @sql_email; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- unique index auf email (aber nur sinnvoll, wenn Spalte existiert)
SET @has_email_idx := (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = @db AND table_name = 'users' AND index_name = 'uniq_users_email'
);
SET @sql_email_idx := IF(
  @has_email_idx = 0,
  "CREATE UNIQUE INDEX uniq_users_email ON users (email)",
  "SELECT 1"
);
PREPARE stmt FROM @sql_email_idx; EXECUTE stmt; DEALLOCATE PREPARE stmt;
