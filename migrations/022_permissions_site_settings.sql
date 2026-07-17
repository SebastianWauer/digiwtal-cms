-- RBAC: Site Settings (ein Recht: sehen = bearbeiten)

INSERT INTO `permissions` (`key`, `label`, `group_key`) VALUES
  ('settings.view', 'Site Settings verwalten', 'settings')
ON DUPLICATE KEY UPDATE
  `label` = VALUES(`label`),
  `group_key` = VALUES(`group_key`);
