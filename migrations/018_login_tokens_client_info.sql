-- 018_login_tokens_client_info.sql
-- login_tokens um Client-Infos erweitern (ip, user_agent)
-- Hintergrund: admin_auth.php schreibt diese Werte beim Login in login_tokens.

SET @db := DATABASE();

-- ip
SET @has_ip := (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = @db AND table_name = 'login_tokens' AND column_name = 'ip'
);
SET @sql_ip := IF(
  @has_ip = 0,
  'ALTER TABLE login_tokens ADD COLUMN ip VARCHAR(64) NULL AFTER expires_at',
  'SELECT 1'
);
PREPARE stmt FROM @sql_ip; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- user_agent
SET @has_ua := (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = @db AND table_name = 'login_tokens' AND column_name = 'user_agent'
);
SET @sql_ua := IF(
  @has_ua = 0,
  'ALTER TABLE login_tokens ADD COLUMN user_agent VARCHAR(255) NULL AFTER ip',
  'SELECT 1'
);
PREPARE stmt FROM @sql_ua; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- created_at existiert bereits, aber falls legacy: sicherstellen
SET @has_created := (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = @db AND table_name = 'login_tokens' AND column_name = 'created_at'
);
SET @sql_created := IF(
  @has_created = 0,
  'ALTER TABLE login_tokens ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
  'SELECT 1'
);
PREPARE stmt FROM @sql_created; EXECUTE stmt; DEALLOCATE PREPARE stmt;
