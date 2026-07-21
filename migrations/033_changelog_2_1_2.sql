-- CMS Release 2.1.2 Changelog Entry

INSERT INTO changelogs (version, type, module_key, content_md, released_at)
VALUES (
    '2.1.2',
    'cms',
    NULL,
    '## Was ist neu\n\n- Frontend rendert jetzt alle Blocktypen aus `themes/default/blocks` (text, hero, image, columns, cta, faq, gallery, video)\n- SEO-Metadaten aus der CMS-API werden im Frontend-Layout ausgegeben (Description, Robots, Canonical, OpenGraph)\n- Textblock-Ausgabe unterstützt WYSIWYG-HTML (Quill) mit erlaubter Tag-Liste\n- Frontend zeigt CMS-Aenderungen jetzt direkt (Live-Auslieferung ohne Seiten-API-Dateicache)\n\n## Sicherheit & Stabilitaet\n\n- Frontend-Logger hat einen robusten Fallback ohne Fatal Error, wenn `shared/FileLogger.php` fehlt\n- Interne Log-Pfade werden nicht mehr auf der oeffentlichen 500-Seite angezeigt\n- Doppeltes API-Rate-Limiting entfernt (nur noch ein zentraler Limiter)\n\n## Performance\n\n- `brand.php` nutzt API-Dateicache mit konfigurierbarer TTL und Browser-Cache-Header',
    '2026-03-06 00:00:00'
);
