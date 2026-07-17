-- 002_navigation.sql
-- Navigation-Items DB-backed

CREATE TABLE `navigation_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `label` VARCHAR(190) NOT NULL,
  `url` VARCHAR(190) NOT NULL,
  `enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `show_in_header` TINYINT(1) NOT NULL DEFAULT 0,
  `show_in_footer` TINYINT(1) NOT NULL DEFAULT 0,
  `sort_order` INT NOT NULL DEFAULT 0,
  `visible_on_json` JSON NULL,
  `hidden_on_json` JSON NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_nav_enabled` (`enabled`),
  KEY `idx_nav_sort` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
