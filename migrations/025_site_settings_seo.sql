-- Globale SEO-Defaults in site_settings

INSERT IGNORE INTO site_settings (`key`, `value`) VALUES
('seo_meta_title_default',       ''),
('seo_meta_description_default', ''),
('seo_robots_default',           'index,follow'),
('seo_canonical_base',           ''),
('seo_og_image_url',             '');
