INSERT INTO modules (key_name, display_name, description)
VALUES 
    ('cms', 'CMS', 'Inhalts-Management und Seiten-Editor'),
    ('pagebuilder', 'Page Builder', 'Visueller Seiten-Editor mit Blöcken'),
    ('seo', 'SEO Tools', 'Suchmaschinen-Optimierung und Meta-Tags'),
    ('shop', 'Shop', 'E-Commerce und Produkt-Verwaltung')
ON DUPLICATE KEY UPDATE 
    display_name = VALUES(display_name),
    description = VALUES(description);
