-- Changelog-Tabelle: speichert CMS- und Modul-Versionshistorie

CREATE TABLE IF NOT EXISTS changelogs (
    id          INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    version     VARCHAR(50)      NOT NULL,
    type        VARCHAR(20)      NOT NULL DEFAULT 'cms'  COMMENT 'cms | module',
    module_key  VARCHAR(100)     NULL     DEFAULT NULL,
    content_md  TEXT             NOT NULL,
    released_at DATETIME         NOT NULL,
    created_at  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_version     (version),
    INDEX idx_released_at (released_at),
    INDEX idx_type_module (type, module_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed: erster Beispieleintrag
INSERT INTO changelogs (version, type, module_key, content_md, released_at) VALUES (
    '2.1.1',
    'cms',
    NULL,
    '## Was ist neu\n\n- Health-Check-Endpunkt `/api/health` eingeführt\n- Internes Deploy-API (`/api/internal/create-backup`, `/api/internal/run-migrations`)\n- Brand-Farben über `site_settings` verwaltbar\n- Öffentliche Settings-API `/api/v1/settings/public`\n\n## Fehlerbehebungen\n\n- Keine bekannten Regressions.',
    '2026-02-24 00:00:00'
);
