-- app/migrations/005_site_settings.sql
-- Site-Settings (DB-only) + Default-Row

CREATE TABLE IF NOT EXISTS site_settings (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(190) NOT NULL DEFAULT 'Site',
  locale VARCHAR(50) NOT NULL DEFAULT 'de-DE',
  timezone VARCHAR(80) NOT NULL DEFAULT 'Europe/Berlin',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Genau eine Default-Row sicherstellen (id=1)
INSERT INTO site_settings (id, name, locale, timezone)
VALUES (1, 'Site', 'de-DE', 'Europe/Berlin')
ON DUPLICATE KEY UPDATE
  name = name;
