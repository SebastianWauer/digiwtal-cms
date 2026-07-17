-- 003_admin_user_prefs.sql
-- User Preferences (z.B. theme: light/dark) pro eingeloggtem Admin-User

CREATE TABLE `admin_user_prefs` (
  `user_id` BIGINT UNSIGNED NOT NULL,
  `pref_key` VARCHAR(64) NOT NULL,
  `pref_value` VARCHAR(190) NOT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`, `pref_key`),
  CONSTRAINT `fk_admin_user_prefs_user`
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
