INSERT INTO admin_users (email, password_hash, totp_secret, is_active, created_at)
VALUES ('info@digiwtal.de', '$2y$12$IbbN0AdtamHXzUqSzzTOGuGdGls4jnru.i/0rpLigR5SwFVa2bBQu', NULL, 1, CURRENT_TIMESTAMP)
ON DUPLICATE KEY UPDATE
    is_active = 1,
    password_hash = IF(password_hash IS NULL OR password_hash = '', VALUES(password_hash), password_hash);
