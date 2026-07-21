-- 043_form_submissions_status.sql

SET @has_status := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'form_submissions'
    AND COLUMN_NAME = 'status'
);
SET @sql_status := IF(
  @has_status = 0,
  "ALTER TABLE `form_submissions` ADD COLUMN `status` VARCHAR(20) NOT NULL DEFAULT 'new' AFTER `ip`",
  'SELECT 1'
);
PREPARE stmt_status FROM @sql_status;
EXECUTE stmt_status;
DEALLOCATE PREPARE stmt_status;

SET @has_updated := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'form_submissions'
    AND COLUMN_NAME = 'updated_at'
);
SET @sql_updated := IF(
  @has_updated = 0,
  "ALTER TABLE `form_submissions` ADD COLUMN `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`",
  'SELECT 1'
);
PREPARE stmt_updated FROM @sql_updated;
EXECUTE stmt_updated;
DEALLOCATE PREPARE stmt_updated;

UPDATE `form_submissions`
SET `status` = 'new'
WHERE `status` IS NULL OR TRIM(`status`) = '';

INSERT INTO `permissions` (`key`,`label`,`group_key`) VALUES
('forms.edit','Formulareingaben bearbeiten','forms')
ON DUPLICATE KEY UPDATE
  `label` = VALUES(`label`),
  `group_key` = VALUES(`group_key`);

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM roles r
JOIN permissions p
WHERE r.`key` = 'admin'
  AND p.`key` IN ('forms.edit');
