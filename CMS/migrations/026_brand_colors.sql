-- Brand colors and public logo URL in site_settings (KV)
-- Adds keys needed by GET /api/v1/settings/public

INSERT IGNORE INTO site_settings (`key`, `value`) VALUES
('brand_color_primary',   '#2563eb'),
('brand_color_secondary', '#64748b'),
('brand_color_tertiary',  '#f59e0b'),
('logo_url',              '');
