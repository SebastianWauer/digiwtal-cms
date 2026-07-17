-- Entfernt ungenutzte Permission-Keys inkl. Cleanup in role_permissions
-- Keys:
--  - pages.restore
--  - pages.publish
--  - users.restore
--  - system.migrate.run

SET @db := DATABASE();

-- ---------- permissions existiert? ----------
SET @has_permissions := (
  SELECT COUNT(*)
  FROM information_schema.tables
  WHERE table_schema = @db AND table_name = 'permissions'
);

-- ---------- role_permissions existiert? ----------
SET @has_role_permissions := (
  SELECT COUNT(*)
  FROM information_schema.tables
  WHERE table_schema = @db AND table_name = 'role_permissions'
);

-- ---------- Cleanup role_permissions (falls vorhanden) ----------
SET @sql := IF(@has_permissions = 1 AND @has_role_permissions = 1,
'DELETE rp
   FROM role_permissions rp
   JOIN permissions p ON p.id = rp.permission_id
  WHERE p.`key` IN (''pages.restore'',''pages.publish'',''users.restore'',''system.migrate.run'')',
'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------- Delete permissions ----------
SET @sql := IF(@has_permissions = 1,
'DELETE FROM permissions
  WHERE `key` IN (''pages.restore'',''pages.publish'',''users.restore'',''system.migrate.run'')',
'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
