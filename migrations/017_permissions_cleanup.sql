-- 010_permissions_cleanup.sql
SET @db := DATABASE();

-- keys die weg sollen
DELETE FROM role_permissions
WHERE permission_id IN (
  SELECT id FROM permissions
  WHERE `key` IN (
    'pages.restore',
    'pages.publish',
    'users.restore',
    'system.migrate.run',
    'system.health.view',
    'users.roles.edit.self',
    'roles.permissions.edit'
  )
);

DELETE FROM permissions
WHERE `key` IN (
  'pages.restore',
  'pages.publish',
  'users.restore',
  'system.migrate.run',
  'system.health.view',
  'users.roles.edit.self',
  'roles.permissions.edit'
);
