-- SEO-Metadaten pro Entity (z.B. Seiten)

CREATE TABLE IF NOT EXISTS seo_meta (
  id               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  entity_type      VARCHAR(32)   NOT NULL DEFAULT 'page',
  entity_id        INT UNSIGNED  NOT NULL,
  meta_title       VARCHAR(255)  NOT NULL DEFAULT '',
  meta_description VARCHAR(500)  NOT NULL DEFAULT '',
  robots           VARCHAR(60)   NOT NULL DEFAULT '',
  canonical_url    VARCHAR(2000) NOT NULL DEFAULT '',
  og_title         VARCHAR(255)  NOT NULL DEFAULT '',
  og_description   VARCHAR(500)  NOT NULL DEFAULT '',
  og_image_url     VARCHAR(2000) NOT NULL DEFAULT '',
  created_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY ux_seo_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
