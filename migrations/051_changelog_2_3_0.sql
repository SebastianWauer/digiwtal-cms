-- CMS Release 2.3.0 - Eigenstaendige CMS- und Frontend-Projekte

SET NAMES utf8mb4;

INSERT INTO changelogs (version, type, module_key, content_md, released_at)
VALUES (
    '2.3.0',
    'cms',
    NULL,
    '## Version 2.3.0\n\n### Architektur\n\n- Das CMS ist als eigenstaendiges Projekt betreibbar und erwartet kein fest eingebautes Kundenfrontend mehr.\n- Das fuer Vorschauen verwendete Frontend wird installationsbezogen ueber `FRONTEND_SOURCE_DIR` zugeordnet.\n- Frontend-Dateien werden nur innerhalb des konfigurierten Projektverzeichnisses aufgeloest.\n\n### Betrieb\n\n- Fehlt die Frontend-Zuordnung, bleibt das CMS bedienbar und meldet fuer die Frontend-Vorschau einen klaren Konfigurationsfehler.',
    '2026-08-27 00:00:00'
);
