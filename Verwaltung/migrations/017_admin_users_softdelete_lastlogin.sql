-- last_login_at + Soft-Delete-Flag
ALTER TABLE admin_users
    ADD COLUMN last_login_at DATETIME NULL AFTER role,
    ADD COLUMN deleted_at    DATETIME NULL AFTER last_login_at;
