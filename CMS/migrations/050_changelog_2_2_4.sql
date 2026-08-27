-- CMS Release 2.2.4 - Dateirechte bei Basis-Pfad-Deployments

SET NAMES utf8mb4;

INSERT INTO changelogs (version, type, module_key, content_md, released_at)
VALUES (
    '2.2.4',
    'cms',
    NULL,
    '## Version 2.2.4\n\n### Deployment\n\n- Automatisch erzeugte CMS-Unterordner und `.htaccess`-Dateien erhalten webservergeeignete Dateirechte.\n- Bereits durch einen Unterpfad-Rollout gesperrte Frontends und CMS-Aufrufe werden beim erneuten Deployment repariert.',
    '2026-08-27 00:00:00'
);
