-- CMS Release 2.2.3 - Vollstaendige Basis-Pfad-Unterstuetzung

SET NAMES utf8mb4;

INSERT INTO changelogs (version, type, module_key, content_md, released_at)
VALUES (
    '2.2.3',
    'cms',
    NULL,
    '## Version 2.2.3\n\n### Basis-Pfad\n\n- Interne CMS-Routen, Medienauswahl, Vorschauen, Formularfilter, Theme-Wechsel und Sitemap beruecksichtigen nun Installationen in Unterverzeichnissen wie `/cms`.\n- Oeffentliche Medien-URLs der CMS-API enthalten den konfigurierten Basis-Pfad.\n- Deployments spiegeln den oeffentlichen CMS-Einstieg automatisch in den Unterordner des Frontends und konfigurieren das Apache-Routing; ein manuelles Verschieben im Webspace entfaellt.',
    '2026-08-27 00:00:00'
);
