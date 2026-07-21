-- 034_events_module.sql
-- Events + Categories + Permissions

CREATE TABLE IF NOT EXISTS `event_categories` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(190) NOT NULL,
  `slug` VARCHAR(190) NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 100,
  `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_event_categories_slug` (`slug`),
  KEY `idx_event_categories_active` (`is_deleted`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` BIGINT UNSIGNED NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `event_date` DATETIME NULL,
  `image_media_id` INT UNSIGNED NULL,
  `youtube_url` VARCHAR(2000) NULL,
  `sort_order` INT NOT NULL DEFAULT 100,
  `is_published` TINYINT(1) NOT NULL DEFAULT 1,
  `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_events_active` (`is_deleted`, `is_published`, `event_date`, `sort_order`),
  KEY `idx_events_category` (`category_id`),
  CONSTRAINT `fk_events_category` FOREIGN KEY (`category_id`) REFERENCES `event_categories`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_events_image_media` FOREIGN KEY (`image_media_id`) REFERENCES `media_items`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `permissions` (`key`,`label`,`group_key`) VALUES
('events.view','Events sehen','events'),
('events.create','Events anlegen','events'),
('events.edit','Events bearbeiten','events'),
('events.delete','Events loeschen (inkl. Restore)','events')
ON DUPLICATE KEY UPDATE
  `label` = VALUES(`label`),
  `group_key` = VALUES(`group_key`);

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM roles r
JOIN permissions p
WHERE r.`key` = 'admin'
  AND p.`key` IN ('events.view','events.create','events.edit','events.delete');
