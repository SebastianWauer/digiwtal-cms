-- CMS Release 2.4.0 - Rechtliche Angaben und Oeffnungsstatus

SET NAMES utf8mb4;

INSERT IGNORE INTO site_settings (`key`, `value`) VALUES
('opening_status', 'hidden'),
('legal_owner', ''),
('legal_register_entry', ''),
('legal_register_court', ''),
('legal_register_number', ''),
('legal_vat_id', '');

INSERT INTO changelogs (version, type, module_key, content_md, released_at)
VALUES (
    '2.4.0',
    'cms',
    NULL,
    '## Version 2.4.0\n\n### Einstellungen\n\n- Inhaber beziehungsweise vertretungsberechtigte Person kann zentral gepflegt werden.\n- Registerart, Registergericht, Registernummer und Umsatzsteuer-ID stehen fuer Impressum und Frontend bereit.\n- Der sichtbare Oeffnungsstatus kann auf nicht anzeigen, aktuell geoeffnet oder aktuell geschlossen gesetzt werden.\n- Alle neuen Angaben werden ueber die oeffentliche Settings-API an das Kundenfrontend geliefert.',
    '2026-08-31 00:00:00'
);
