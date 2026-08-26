-- 044_news_module.sql

CREATE TABLE IF NOT EXISTS `news_categories` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(190) NOT NULL,
  `slug` VARCHAR(190) NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 100,
  `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_news_categories_slug` (`slug`),
  KEY `idx_news_categories_active` (`is_deleted`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `news` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL,
  `teaser` TEXT NULL,
  `content_json` LONGTEXT NULL,
  `category_id` BIGINT UNSIGNED NULL,
  `image_media_id` INT UNSIGNED NULL,
  `is_published` TINYINT(1) NOT NULL DEFAULT 1,
  `published_at` DATETIME NULL,
  `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_news_slug` (`slug`),
  KEY `idx_news_active` (`is_deleted`, `is_published`, `published_at`),
  KEY `idx_news_category` (`category_id`),
  CONSTRAINT `fk_news_category` FOREIGN KEY (`category_id`) REFERENCES `news_categories`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_news_image_media` FOREIGN KEY (`image_media_id`) REFERENCES `media_items`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `permissions` (`key`,`label`,`group_key`) VALUES
('news.view','News sehen','news'),
('news.create','News anlegen','news'),
('news.edit','News bearbeiten','news'),
('news.delete','News loeschen (inkl. Restore)','news')
ON DUPLICATE KEY UPDATE
  `label` = VALUES(`label`),
  `group_key` = VALUES(`group_key`);

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM roles r
JOIN permissions p
WHERE r.`key` = 'admin'
  AND p.`key` IN ('news.view','news.create','news.edit','news.delete');
