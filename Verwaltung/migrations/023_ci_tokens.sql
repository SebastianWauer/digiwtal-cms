-- 023_ci_tokens.sql
-- Token, mit dem eine CI-Pipeline die Deploy-Zugangsdaten eines Kunden abholt.
--
-- Hintergrund: Auf IONOS-Webspace kann die Verwaltung nicht selbst uebertragen
-- (keine ssh2-Erweiterung, keine Shell, FTP-Port geschlossen). Das Ausrollen
-- uebernimmt GitHub Actions. Damit die Zugangsdaten nicht ein zweites Mal in
-- GitHub gepflegt werden muessen, holt die Pipeline sie hier ab - die
-- Verwaltung bleibt die einzige Quelle.
--
-- Gespeichert wird nur der SHA-256-Hash: Der Klartext wird einmal bei der
-- Erstellung angezeigt und danach nicht wieder herstellbar.

CREATE TABLE IF NOT EXISTS ci_tokens (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    label        VARCHAR(100) NOT NULL DEFAULT '',
    token_hash   CHAR(64) NOT NULL,
    last_used_at DATETIME NULL,
    last_used_ip VARCHAR(45) NOT NULL DEFAULT '',
    revoked_at   DATETIME NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ci_tokens_hash (token_hash),
    KEY idx_ci_tokens_revoked (revoked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
