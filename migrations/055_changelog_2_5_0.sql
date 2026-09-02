-- CMS Release 2.5.0 - Zentrale Navigationsreihenfolge

SET NAMES utf8mb4;

INSERT INTO changelogs (version, type, module_key, content_md, released_at)
VALUES (
    '2.5.0',
    'cms',
    NULL,
    '## Version 2.5.0\n\n### Seiten und Navigation\n\n- Die Navigationsreihenfolge wird zentral auf der Seitenuebersicht per Drag-and-drop oder Pfeiltasten gepflegt.\n- Eine gespeicherte Reihenfolge gilt einheitlich fuer Navigation, Sidebar und Seiten-Karussell.\n- Neue Navigationseintraege werden automatisch am Ende angefuegt; bisher nicht einsortierte Eintraege springen nicht mehr an den Anfang.\n- Die konkurrierende Einzelsortierung im Seiten-Karussell wurde entfernt. Bilder und Beschreibungstexte bleiben weiterhin je Folie bearbeitbar.\n- Speichern und zurueck uebernimmt jetzt alle Seiteneinstellungen, bevor zur Seitenuebersicht gewechselt wird.',
    '2026-09-03 00:00:00'
);
