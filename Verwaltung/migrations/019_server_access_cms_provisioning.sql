ALTER TABLE server_access
    ADD COLUMN db_host VARCHAR(255) NOT NULL DEFAULT '' AFTER html_path,
    ADD COLUMN db_port SMALLINT UNSIGNED NOT NULL DEFAULT 3306 AFTER db_host,
    ADD COLUMN db_name VARCHAR(190) NOT NULL DEFAULT '' AFTER db_port,
    ADD COLUMN db_user VARCHAR(190) NOT NULL DEFAULT '' AFTER db_name,
    ADD COLUMN db_password_enc TEXT NULL AFTER db_user,
    ADD COLUMN db_password_nonce VARCHAR(64) NOT NULL DEFAULT '' AFTER db_password_enc,
    ADD COLUMN db_password_tag VARCHAR(64) NOT NULL DEFAULT '' AFTER db_password_nonce,
    ADD COLUMN cms_admin_email VARCHAR(255) NOT NULL DEFAULT '' AFTER db_password_tag,
    ADD COLUMN cms_admin_password_enc TEXT NULL AFTER cms_admin_email,
    ADD COLUMN cms_admin_password_nonce VARCHAR(64) NOT NULL DEFAULT '' AFTER cms_admin_password_enc,
    ADD COLUMN cms_admin_password_tag VARCHAR(64) NOT NULL DEFAULT '' AFTER cms_admin_password_nonce,
    ADD COLUMN site_name VARCHAR(190) NOT NULL DEFAULT '' AFTER cms_admin_password_tag,
    ADD COLUMN canonical_base VARCHAR(255) NOT NULL DEFAULT '' AFTER site_name;
