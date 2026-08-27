-- Token, mit dem sich eine Instanz beim Melden ausweist.
--
-- Zwei Spalten fuer denselben Wert, mit Absicht: Der Hash dient dem Nachschlagen
-- in einer Abfrage, die verschluesselte Kopie dem Wiederanzeigen. Ohne die
-- Kopie muesste bei jedem Rollout ein neues Token erzeugt werden, und die
-- bestehende .env auf dem Kundenserver wuerde nicht mehr passen.
ALTER TABLE server_access
    ADD COLUMN support_token_hash  CHAR(64) NULL,
    ADD COLUMN support_token_enc   TEXT NULL,
    ADD COLUMN support_token_nonce VARCHAR(64) NOT NULL DEFAULT '',
    ADD COLUMN support_token_tag   VARCHAR(64) NOT NULL DEFAULT '',
    ADD INDEX idx_server_access_support_token (support_token_hash);
