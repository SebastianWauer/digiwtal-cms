-- 009_permissions.sql
-- Permissions-Katalog (RBAC Basis)

CREATE TABLE IF NOT EXISTS `permissions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `key` VARCHAR(190) NOT NULL,
  `label` VARCHAR(190) NOT NULL,
  `group_key` VARCHAR(64) NOT NULL DEFAULT 'general',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_permissions_key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default Permissions (idempotent)
INSERT INTO `permissions` (`key`, `label`, `group_key`) VALUES
  ('dashboard.view', 'Dashboard sehen', 'dashboard'),

  ('pages.view', 'Seiten sehen', 'pages'),
  ('pages.create', 'Seiten anlegen', 'pages'),
  ('pages.edit', 'Seiten bearbeiten', 'pages'),
  ('pages.delete', 'Seiten löschen', 'pages'),
  ('pages.restore', 'Seiten wiederherstellen', 'pages'),
  ('pages.publish', 'Seitenstatus (Live/Draft) ändern', 'pages'),

  ('users.view', 'Benutzer sehen', 'users'),
  ('users.create', 'Benutzer anlegen', 'users'),
  ('users.edit', 'Benutzer bearbeiten', 'users'),
  ('users.delete', 'Benutzer löschen', 'users'),
  ('users.restore', 'Benutzer wiederherstellen', 'users'),
  ('users.password.reset', 'Passwort anderer Benutzer setzen', 'users'),

  ('roles.view', 'Rollen sehen', 'roles'),
  ('roles.create', 'Rollen anlegen', 'roles'),
  ('roles.edit', 'Rollen bearbeiten', 'roles'),
  ('roles.delete', 'Rollen löschen', 'roles'),
  ('roles.permissions.edit', 'Rollen-Berechtigungen ändern', 'roles'),

  ('system.health.view', 'System/Health ansehen', 'system'),
  ('system.migrate.run', 'Migrationen ausführen', 'system')
ON DUPLICATE KEY UPDATE
  `label` = VALUES(`label`),
  `group_key` = VALUES(`group_key`);
