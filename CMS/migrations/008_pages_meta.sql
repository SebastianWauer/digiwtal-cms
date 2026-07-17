-- 008_pages_meta.sql
-- Frontend-Titel / Untertitel / Status direkt in pages speichern

ALTER TABLE pages
  ADD COLUMN frontend_title VARCHAR(190) NOT NULL DEFAULT '' AFTER title,
  ADD COLUMN subtitle VARCHAR(190) NOT NULL DEFAULT '' AFTER frontend_title,
  ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'live' AFTER subtitle;

CREATE INDEX idx_pages_status ON pages (status);
