-- 007_migrate_navigation_items_to_pages.sql
-- Überträgt navigation_items -> pages (nav_visible/nav_label/nav_area/nav_order)
-- Match: pages.slug = normalisierte navigation_items.url

-- 1) Update: schreibe Navigation-Felder in pages
-- Fix 2026-03-06: explizites JOIN-Keyword (INNER JOIN) für klare MySQL-Syntax
UPDATE pages p
INNER JOIN (
  SELECT
    id,
    label,
    enabled,
    show_in_header,
    show_in_footer,
    sort_order,
    -- URL normalisieren:
    -- - nur Pfadteil
    -- - führenden Slash erzwingen
    -- - trailing slash entfernen (außer "/")
    CASE
      WHEN url IS NULL OR TRIM(url) = '' THEN ''
      ELSE
        CASE
          WHEN LEFT(
                 CASE
                   WHEN LOCATE('://', url) > 0 THEN SUBSTRING_INDEX(SUBSTRING_INDEX(url, '/', 3), '/', -1) /*dummy*/
                   ELSE url
                 END
               , 1) = '/' THEN
            CASE
              WHEN TRIM(url) = '/' THEN '/'
              ELSE TRIM(TRAILING '/' FROM TRIM(url))
            END
          ELSE
            CASE
              WHEN TRIM(url) = '/' THEN '/'
              ELSE CONCAT('/', TRIM(TRAILING '/' FROM TRIM(url)))
            END
        END
    END AS slug_norm
  FROM navigation_items
) n
  ON p.slug = n.slug_norm
SET
  p.nav_visible = CASE
                    WHEN n.enabled = 1 AND (n.show_in_header = 1 OR n.show_in_footer = 1) THEN 1
                    ELSE 0
                  END,
  p.nav_label = CASE
                  WHEN n.enabled = 1 AND (n.show_in_header = 1 OR n.show_in_footer = 1) THEN n.label
                  ELSE p.nav_label
                END,
  p.nav_area = CASE
                 WHEN n.enabled = 1 AND n.show_in_header = 1 AND n.show_in_footer = 1 THEN 'both'
                 WHEN n.enabled = 1 AND n.show_in_header = 1 THEN 'header'
                 WHEN n.enabled = 1 AND n.show_in_footer = 1 THEN 'footer'
                 ELSE p.nav_area
               END,
  p.nav_order = CASE
                  WHEN n.enabled = 1 AND (n.show_in_header = 1 OR n.show_in_footer = 1) THEN n.sort_order
                  ELSE p.nav_order
                END
WHERE p.is_deleted = 0;

-- 2) Report: Nav-Items, die keine passende Page haben
SELECT
  ni.id,
  ni.label,
  ni.url,
  ni.enabled,
  ni.show_in_header,
  ni.show_in_footer,
  ni.sort_order
FROM navigation_items ni
LEFT JOIN pages p
  ON p.slug = CASE
                WHEN ni.url IS NULL OR TRIM(ni.url) = '' THEN ''
                WHEN TRIM(ni.url) = '/' THEN '/'
                WHEN LEFT(TRIM(ni.url), 1) = '/' THEN TRIM(TRAILING '/' FROM TRIM(ni.url))
                ELSE CONCAT('/', TRIM(TRAILING '/' FROM TRIM(ni.url)))
              END
WHERE p.id IS NULL;
