-- 019_permissions_media.sql
-- RBAC: Media-Module Permissions (minimal + konsistent)
-- Regeln:
--  - Restore gehört zu delete
--  - Keine neuen überlappenden Rechte

INSERT INTO `permissions` (`key`, `label`, `group_key`) VALUES
  ('media.edit',   'Medien bearbeiten (Upload/Ordner/Details)', 'media'),
  ('media.delete', 'Medien löschen (inkl. Restore)', 'media')
ON DUPLICATE KEY UPDATE
  `label` = VALUES(`label`),
  `group_key` = VALUES(`group_key`);
