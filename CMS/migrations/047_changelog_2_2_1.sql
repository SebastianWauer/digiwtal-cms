-- CMS Release 2.2.1 - Schutz vor eigener Accountsperre

SET NAMES utf8mb4;

INSERT INTO changelogs (version, type, module_key, content_md, released_at)
VALUES (
    '2.2.1',
    'cms',
    NULL,
    '## Version 2.2.1\n\n### Benutzerverwaltung\n\n- Angemeldete Benutzer können ihren eigenen Account nicht mehr versehentlich sperren. Der Status anderer Benutzer kann mit der entsprechenden Berechtigung weiterhin geändert werden.',
    '2026-08-27 00:00:00'
);
