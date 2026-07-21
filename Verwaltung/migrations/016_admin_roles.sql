-- Rolle-Spalte zu admin_users hinzufügen
ALTER TABLE admin_users
    ADD COLUMN role ENUM('superadmin', 'operator') NOT NULL DEFAULT 'operator'
    AFTER email;

-- Ersten Admin zum Superadmin machen
UPDATE admin_users SET role = 'superadmin' WHERE id = 1;
