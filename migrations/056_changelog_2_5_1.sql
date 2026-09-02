-- CMS Release 2.5.1 - Getrennte Sortierung fuer Header und Footer

SET NAMES utf8mb4;

INSERT INTO changelogs (version, type, module_key, content_md, released_at)
VALUES (
    '2.5.1',
    'cms',
    NULL,
    '## Version 2.5.1\n\n### Seitenuebersicht und Navigation\n\n- Die grosse Sortierliste wurde aus der Seitenuebersicht entfernt.\n- Header und Footer werden jetzt in zwei getrennten, kompakten Popups sortiert.\n- Seiten mit der Einstellung Header und Footer werden ausschliesslich im Header-Popup einsortiert.\n- Die Seitentabelle zeigt Titel, Slug, Startseite, Navigationsbereich, Aenderungsdatum, Status und Aktionen.\n- Die Tabelle gruppiert Startseite, Header-Seiten, Footer-Seiten, Seiten ohne Navigation und Entwuerfe in dieser festen Reihenfolge.\n- Innerhalb von Header und Footer entspricht die Tabellenfolge der jeweils gespeicherten Navigationsreihenfolge.\n- Beim Wechsel einer Seite zwischen Header und Footer wird sie im neuen Bereich automatisch am Ende angefuegt.',
    '2026-09-03 00:00:00'
);
