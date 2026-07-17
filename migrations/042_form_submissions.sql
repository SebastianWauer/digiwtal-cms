-- 042_form_submissions.sql

CREATE TABLE IF NOT EXISTS `form_submissions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `form_id` VARCHAR(64) NOT NULL,
  `data_json` JSON NOT NULL,
  `ip` VARCHAR(64) NOT NULL DEFAULT '',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_form_submissions_form_created` (`form_id`, `created_at`),
  KEY `idx_form_submissions_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `permissions` (`key`,`label`,`group_key`) VALUES
('forms.view','Formulareingaben sehen','forms')
ON DUPLICATE KEY UPDATE
  `label` = VALUES(`label`),
  `group_key` = VALUES(`group_key`);

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM roles r
JOIN permissions p
WHERE r.`key` = 'admin'
  AND p.`key` IN ('forms.view');
