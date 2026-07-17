-- Redesign: site_settings von Row-basiert -> Key/Value
-- Bestehende Tabelle wird als Backup umbenannt.

RENAME TABLE site_settings TO site_settings_legacy;

CREATE TABLE IF NOT EXISTS site_settings (
  `key`   VARCHAR(190) NOT NULL,
  `value` TEXT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO site_settings (`key`,`value`) VALUES
('site_title', ''),
('site_tagline', ''),
('logo_media_id', ''),
('locale', 'de-DE'),
('timezone', 'Europe/Berlin');
