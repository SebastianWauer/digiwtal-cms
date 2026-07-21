ALTER TABLE server_access
    ADD COLUMN health_cms_url VARCHAR(255) NOT NULL DEFAULT '' AFTER canonical_base,
    ADD COLUMN health_frontend_url VARCHAR(255) NOT NULL DEFAULT '' AFTER health_cms_url;
