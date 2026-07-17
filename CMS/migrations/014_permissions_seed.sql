-- XXX_permissions_seed.sql
-- RBAC: permissions + role_permissions + Seed Keys (idempotent)

CREATE TABLE IF NOT EXISTS `permissions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `key` VARCHAR(190) NOT NULL,
  `label` VARCHAR(190) NOT NULL,
  `group_key` VARCHAR(64) NOT NULL DEFAULT 'general',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_permissions_key` (`key`),
  KEY `idx_permissions_group` (`group_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `role_permissions` (
  `role_id` BIGINT UNSIGNED NOT NULL,
  `permission_id` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`role_id`, `permission_id`),
  CONSTRAINT `fk_role_permissions_role` FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_role_permissions_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `permissions` (`key`,`label`,`group_key`) VALUES
('dashboard.view','Dashboard sehen','dashboard'),

('pages.view','Seiten sehen','pages'),
('pages.create','Seiten anlegen','pages'),
('pages.edit','Seiten bearbeiten','pages'),
('pages.status.edit','Seitenstatus ändern','pages'),
('pages.delete','Seiten löschen (inkl. Restore)','pages'),

('users.view','Benutzer sehen','users'),
('users.create','Benutzer anlegen','users'),
('users.edit','Benutzer bearbeiten','users'),
('users.delete','Benutzer löschen (inkl. Restore)','users'),
('users.password.reset','Passwort anderer Benutzer zurücksetzen','users'),
('users.roles.edit.self','Eigene Rollen ändern','users'),
('users.roles.edit.other','Rollen anderer Benutzer ändern','users'),

('roles.view','Rollen sehen','roles'),
('roles.create','Rollen anlegen','roles'),
('roles.edit','Rollen bearbeiten','roles'),
('roles.delete','Rollen löschen (inkl. Restore)','roles'),
('roles.permissions.edit','Rollenrechte bearbeiten','roles'),

('system.migrate','Migrationen ausführen','system')
ON DUPLICATE KEY UPDATE
  `label` = VALUES(`label`),
  `group_key` = VALUES(`group_key`);

-- Admin bekommt immer alles (idempotent)
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM roles r
JOIN permissions p
WHERE r.`key` = 'admin';
