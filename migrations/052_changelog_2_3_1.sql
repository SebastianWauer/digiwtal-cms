-- CMS Release 2.3.1 - Automatisch verwaltete Instanzkonfiguration

SET NAMES utf8mb4;

INSERT INTO changelogs (version, type, module_key, content_md, released_at)
VALUES (
    '2.3.1',
    'cms',
    NULL,
    '## Version 2.3.1\n\n### Konfiguration\n\n- CMS- und Frontend-Konfiguration werden bei jedem Rollout automatisch aus der zentralen Verwaltung synchronisiert.\n- Health-, Deploy-, Migrations-, Setup- und CMS-API-Token sind pro Kundeninstanz getrennt und werden automatisch bereitgestellt.\n- Von Hand gepflegte Zusatzwerte in bestehenden `.env`-Dateien bleiben bei der Synchronisierung erhalten.\n- Neue Instanzen benoetigen keine manuell angelegten `.env`-Dateien mehr.',
    '2026-08-28 00:00:00'
);
