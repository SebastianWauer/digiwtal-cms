-- 010_users_roles_soft_delete.sql
-- Soft-Delete für users + roles (wie bei pages)

SET @db := DATABASE();

-- USERS: is_deleted + deleted_at
SET @has_users_is_deleted := (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = @db AND table_name = 'users' AND column_name = 'is_deleted'
);
SET @sql_users_is_deleted := IF(
  @has_users_is_deleted = 0,
  'ALTER TABLE users ADD COLUMN is_deleted TINYINT(1) NOT NULL DEFAULT 0',
  'SELECT 1'
);
PREPARE stmt FROM @sql_users_is_deleted; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_users_deleted_at := (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = @db AND table_name = 'users' AND column_name = 'deleted_at'
);
SET @sql_users_deleted_at := IF(
  @has_users_deleted_at = 0,
  'ALTER TABLE users ADD COLUMN deleted_at DATETIME NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql_users_deleted_at; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ROLES: is_deleted + deleted_at
SET @has_roles_is_deleted := (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = @db AND table_name = 'roles' AND column_name = 'is_deleted'
);
SET @sql_roles_is_deleted := IF(
  @has_roles_is_deleted = 0,
  'ALTER TABLE roles ADD COLUMN is_deleted TINYINT(1) NOT NULL DEFAULT 0',
  'SELECT 1'
);
PREPARE stmt FROM @sql_roles_is_deleted; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_roles_deleted_at := (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = @db AND table_name = 'roles' AND column_name = 'deleted_at'
);
SET @sql_roles_deleted_at := IF(
  @has_roles_deleted_at = 0,
  'ALTER TABLE roles ADD COLUMN deleted_at DATETIME NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql_roles_deleted_at; EXECUTE stmt; DEALLOCATE PREPARE stmt;
