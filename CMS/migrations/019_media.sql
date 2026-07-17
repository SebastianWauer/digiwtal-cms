-- 019_media.sql
-- Medienverwaltung: Ordner + Medien + Verwendungen (idempotent)

-- ------------------------------------------------------------
-- 1) Ordnerstruktur (Tree links)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `media_folders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id` INT UNSIGNED NULL,
  `name` VARCHAR(120) NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_media_folders_parent` (`parent_id`, `sort_order`, `name`),
  CONSTRAINT `fk_media_folders_parent`
    FOREIGN KEY (`parent_id`) REFERENCES `media_folders`(`id`)
    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Root-Ordner (idempotent, feste ID=1)
INSERT INTO `media_folders` (`id`, `parent_id`, `name`, `sort_order`)
VALUES (1, NULL, 'Root', 0)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `sort_order` = VALUES(`sort_order`);

-- ------------------------------------------------------------
-- 2) Medien (Datei + Metadaten)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `media_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `folder_id` INT UNSIGNED NULL,

  `original_filename` VARCHAR(255) NOT NULL,
  `display_filename`  VARCHAR(255) NOT NULL,
  `storage_filename`  VARCHAR(255) NOT NULL,

  `ext`  VARCHAR(16)  NOT NULL,
  `mime` VARCHAR(120) NOT NULL,
  `size_bytes` BIGINT UNSIGNED NOT NULL DEFAULT 0,

  `width`  INT UNSIGNED NULL,
  `height` INT UNSIGNED NULL,

  `title` VARCHAR(255) NULL,
  `alt_text` TEXT NULL,
  `description` TEXT NULL,

  `focus_x` TINYINT UNSIGNED NULL,
  `focus_y` TINYINT UNSIGNED NULL,

  `usage_count` INT UNSIGNED NOT NULL DEFAULT 0,

  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL,

  `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  `deleted_at` DATETIME NULL,

  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_media_items_storage_filename` (`storage_filename`),
  KEY `idx_media_items_folder` (`folder_id`, `is_deleted`, `created_at`),
  KEY `idx_media_items_type` (`ext`, `is_deleted`, `created_at`),
  KEY `idx_media_items_usage` (`usage_count`, `is_deleted`),

  CONSTRAINT `fk_media_items_folder`
    FOREIGN KEY (`folder_id`) REFERENCES `media_folders`(`id`)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Volltextsuche für "Dateiname, Titel, Beschreibung..."
-- Hinweis: FULLTEXT ist auf InnoDB/utf8mb4 ok (MySQL >= 5.6).
ALTER TABLE `media_items`
  ADD FULLTEXT KEY `ft_media_items_search` (`display_filename`, `title`, `description`);

-- ------------------------------------------------------------
-- 3) Verwendungen (Delete-Sperre / Usage-Count / Detailtabelle)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `media_usages` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `media_id` INT UNSIGNED NOT NULL,

  `entity_type` VARCHAR(40) NOT NULL,
  `entity_id` INT UNSIGNED NOT NULL,
  `field_key` VARCHAR(120) NOT NULL,

  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_media_usages_unique` (`media_id`, `entity_type`, `entity_id`, `field_key`),
  KEY `idx_media_usages_entity` (`entity_type`, `entity_id`),
  KEY `idx_media_usages_media` (`media_id`),

  CONSTRAINT `fk_media_usages_media`
    FOREIGN KEY (`media_id`) REFERENCES `media_items`(`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 4) Permission: media.view (Sidebar erwartet diesen Key bereits)
-- ------------------------------------------------------------
INSERT INTO `permissions` (`key`, `label`, `group_key`) VALUES
  ('media.view', 'Medien sehen', 'media')
ON DUPLICATE KEY UPDATE
  `label` = VALUES(`label`),
  `group_key` = VALUES(`group_key`);
