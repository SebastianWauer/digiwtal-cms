-- Extend site_settings KV with additional global keys

INSERT IGNORE INTO site_settings (`key`,`value`) VALUES
('domain', ''),

('cms_logo_light_media_id', ''),
('cms_logo_dark_media_id', ''),
('favicon_media_id', ''),

('contact_name', ''),
('contact_email', ''),
('contact_phone', ''),
('contact_address', ''),
('contact_postal_city', ''),

('social_facebook', ''),
('social_instagram', ''),
('social_youtube', ''),
('social_tiktok', ''),
('social_x', '');
