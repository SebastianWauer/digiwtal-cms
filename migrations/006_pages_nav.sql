-- 005_pages_nav.sql
-- Startseite + Navigation in pages integrieren

ALTER TABLE pages
  ADD COLUMN is_home TINYINT(1) NOT NULL DEFAULT 0 AFTER content_json,
  ADD COLUMN nav_visible TINYINT(1) NOT NULL DEFAULT 0 AFTER is_home,
  ADD COLUMN nav_label VARCHAR(190) NOT NULL DEFAULT '' AFTER nav_visible,
  ADD COLUMN nav_area VARCHAR(20) NOT NULL DEFAULT 'header' AFTER nav_label,
  ADD COLUMN nav_order INT NOT NULL DEFAULT 0 AFTER nav_area;

CREATE INDEX idx_pages_home ON pages (is_home);
CREATE INDEX idx_pages_nav ON pages (nav_visible, nav_area, nav_order, slug);
