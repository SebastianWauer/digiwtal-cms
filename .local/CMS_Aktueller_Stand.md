# CMS Aktueller Stand

Stand: 2026-04-15
CMS-Version: 2.1.2
Zweck: Lokale Ausgangsdokumentation fuer spaetere Changelog-Erstellung.
Status dieser Datei: lokal, nicht fuer Git-Commits vorgesehen, liegt in `CMS/.local/`.

## Grundsaetzlicher Aufbau

Das CMS ist eine eigenstaendige PHP-Anwendung im Ordner `CMS`.

Wichtige Einstiegspunkte:

- `CMS/public/index.php`: Admin-UI, Routing, Setup-Redirect, Response-Time-Header.
- `CMS/public/api.php`: oeffentliche/technische API, unter anderem Brand-Settings und Event-Daten.
- `CMS/public/web.php`: Frontend-Web-Auslieferung ueber CMS-nahe Infrastruktur.
- `CMS/app/bootstrap.php`: zentrales Bootstrap fuer Sessions, Autoloading, Environment, DB, Auth, CSRF, Prefs, Includes und Plugins.
- `CMS/config/version.php`: aktuelle CMS-Version.
- `CMS/migrations/`: SQL-Migrationen fuer Schema-Entwicklung.
- `CMS/docs/`: bestehende Dokumentation zu Testing und Plugin-API.

Die Anwendung verwendet einen eigenen kleinen Router (`App\Http\Router`) mit Controller-Klassen unter `CMS/app/Controller`.
Admin-Views liegen unter `CMS/app/Views`, wiederverwendbare Layout-/Sidebar-/Picker-Teile unter `CMS/app/includes`.

## Version und Changelog-Basis

Aktuelle Version laut `CMS/config/version.php`: `2.1.2`.

Es gibt eine Changelog-Seite im Adminbereich:

- Route: `GET /changelog`
- Controller: `App\Controller\ChangelogController`
- View: `CMS/app/Views/changelog.php`
- Migration: `027_changelogs.sql`
- Versionsmigration: `033_changelog_2_1_2.sql`

Diese lokale Datei soll spaeter als fachliche Basis dienen, um veraenderte Funktionen gegen den hier dokumentierten Stand abzugleichen.

## Routing Admin-UI

Aktuelle Admin-Routen aus `CMS/public/index.php`:

- `GET /`: Dashboard.
- `GET /login`: Login-Formular.
- `POST /login`: Login-Verarbeitung.
- `POST /logout`: Logout, CSRF-geschuetzt.
- `GET /password-reset`: Passwort-zuruecksetzen anfordern.
- `POST /password-reset`: Reset-Link/Token erzeugen.
- `GET /password-reset/{token}`: Reset-Formular.
- `POST /password-reset/{token}`: neues Passwort speichern.
- `POST /theme`: Admin-Theme speichern.
- `POST /prefs`: Admin-UI-Praeferenzen speichern.
- `GET /pages`: Seitenliste.
- `GET /pages/edit`: Seite erstellen/bearbeiten.
- `POST /pages/preview`: Seitenvorschau.
- `POST /pages/save`: Seite speichern.
- `POST /pages/delete`: Seite soft-deleten.
- `POST /pages/restore`: geloeschte Seite wiederherstellen.
- `GET /pages/deleted`: Papierkorb fuer Seiten.
- `POST /pages/purge`: Seite endgueltig loeschen.
- `GET /events`: Eventliste.
- `GET /events/categories`: Event-Kategorien verwalten.
- `POST /events/categories/save`: Event-Kategorie speichern.
- `GET /events/edit`: Event bearbeiten.
- `POST /events/save`: Event speichern.
- `POST /events/delete`: Event soft-deleten.
- `POST /events/restore`: Event wiederherstellen.
- `GET /events/deleted`: Event-Papierkorb.
- `POST /events/purge`: Event endgueltig loeschen.
- `GET /media`: Medienbibliothek.
- `GET /media/show`: Medienansicht.
- `GET /media/deleted`: Medien-Papierkorb.
- `GET /media/edit`: Medium bearbeiten.
- `POST /media/save`: Medienmetadaten speichern.
- `POST /media/upload`: Upload.
- `POST /media/folder/create`: Ordner erstellen.
- `POST /media/delete`: Medium soft-deleten.
- `POST /media/restore`: Medium wiederherstellen.
- `POST /media/purge`: Medium endgueltig loeschen.
- `POST /media/move`: Medium verschieben.
- `POST /media/rotate`: Bild drehen.
- `GET /media/thumb`: Thumbnail ausliefern.
- `GET /media/file`: Originaldatei ausliefern.
- `GET /users`: Benutzerliste.
- `GET /users/deleted`: geloeschte Benutzer.
- `GET /users/edit`: Benutzer erstellen/bearbeiten.
- `POST /users/save`: Benutzer speichern.
- `POST /users/delete`: Benutzer soft-deleten.
- `POST /users/restore`: Benutzer wiederherstellen.
- `POST /users/purge`: Benutzer endgueltig loeschen.
- `GET /roles`: Rollenliste.
- `GET /roles/deleted`: geloeschte Rollen.
- `GET /roles/edit`: Rolle erstellen/bearbeiten.
- `POST /roles/save`: Rolle speichern.
- `POST /roles/delete`: Rolle soft-deleten.
- `POST /roles/restore`: Rolle wiederherstellen.
- `POST /roles/purge`: Rolle endgueltig loeschen.
- `GET /settings`: Site-/Brand-/SEO-Einstellungen anzeigen.
- `POST /settings`: Einstellungen speichern.
- `GET /migrate`: Migrationen anzeigen.
- `POST /migrate/run`: Migrationen ausfuehren.
- `POST /migrate/baseline`: Migrationsbaseline setzen.
- `GET /system/health`: System Health UI.
- `GET /system/health/api`: System Health JSON/API.
- `POST /system/health/reset`: Health-Daten zuruecksetzen.
- `GET /backup`: Backup UI.
- `POST /backup/db`: Datenbankexport.
- `GET /setup`: Setup Schritt 1.
- `POST /setup/step1`: Setup Schritt 1 speichern.
- `GET /setup/step2`: Setup Schritt 2.
- `POST /setup/step2`: Setup Schritt 2 speichern.
- `GET /setup/step3`: Setup Schritt 3.
- `POST /setup/finish`: Setup abschliessen.
- `GET /setup/status`: Setup-Status.

Die Test-Route `/test` existiert nur bei `APP_ENV=development`.

## Setup und Installation

Beim Request auf `CMS/public/index.php` prueft das CMS, ob Setup erlaubt/notwendig ist.
Wenn die Anwendung noch nicht installiert ist, wird auf `/setup` umgeleitet.

Setup-Komponenten:

- `App\Core\Setup`: erkennt Installationsstatus ueber `site_settings` und `app_installed`.
- `App\Controller\SetupController`: Setup-Wizard.
- `App\Setup\MigrationsRunner`: fuehrt SQL-Migrationen aus.
- `App\Setup\EnsureSiteSettings`: stellt Basiseinstellungen sicher.
- `App\Setup\EnsureDefaultPages`: erzeugt Default-Seiten.
- `CMS/app/Seeds/default_pages.php`: Seed-Daten fuer Standardseiten.

Setup erzeugt bzw. erwartet grundlegende Tabellen wie Admin-Foundation, Pages, Site-Settings, Permissions, Rollen und User.

## Authentifizierung

Login:

- View: `CMS/app/Views/login.php`
- Controller: `App\Controller\LoginController`
- Service: `App\Services\AuthService`
- Legacy-/Security-Funktionen: `CMS/app/admin_auth.php`
- CSRF-Funktionen: `CMS/app/admin_csrf.php`

Aktueller Login-Stand:

- Eigenes Auth-Layout im Stil der Admin-Oberflaeche.
- Firmen-/CMS-Logo wird aus `site_settings` geladen:
  - bevorzugt `cms_logo_dark_media_id`,
  - fallback `cms_logo_light_media_id`,
  - fallback `logo_media_id`.
- Footer zeigt das Jahr und die CMS-Version aus `admin_version()`/`config/version.php`.
- Kein Light/Dark-Schalter im Login.
- Formularfelder bleiben funktional unveraendert:
  - `username`
  - `password`
  - `POST /login`
  - CSRF-Feld ueber `admin_csrf_field()`.
- Passwort-vergessen-Link fuehrt auf `/password-reset`.

Passwort-Reset:

- Controller: `PasswordResetController`.
- Views:
  - `password_reset_request.php`
  - `password_reset_form.php`
- Migration: `030_password_resets.sql`.
- Reset-Token in Route als Hex-Token vorgesehen.

Session-/Cookie-Hardening:

- `session.cookie_secure` abhaengig von HTTPS.
- `session.cookie_httponly = 1`.
- `session.use_only_cookies = 1`.
- `session.use_strict_mode = 1`.
- `session.cookie_samesite = Lax`.
- Sessionstart zentral im Bootstrap.

Logout:

- Route `POST /logout`.
- CSRF-Pflicht im `LogoutController`.
- Audit-/Logout-Logik in `admin_auth.php`, Fehler beim Audit blockieren Logout nicht.

## Autorisierung, Rollen und Berechtigungen

Das CMS besitzt ein Rollen-/Permissions-System.

Wichtige Dateien:

- `RolesController`
- `UsersController`
- `RoleRepositoryDb`
- `PermissionRepositoryDb`
- `RolePermissionRepositoryDb`
- `UserRoleRepositoryDb`
- `admin_auth.php`
- `admin_can(...)`
- `admin_require_perm(...)`

Migrationen:

- `010_users_roles_soft_delete.sql`
- `011_users_name_email.sql`
- `012_permissions.sql`
- `013_role_permissions.sql`
- `014_permissions_seed.sql`
- `015_permissions_cleanup_remove_legacy.sql`
- `016_permissions_remove_legacy_leys.sql`
- `017_permissions_cleanup.sql`
- `020_permissions_media.sql`
- `022_permissions_site_settings.sql`

Admin-UI:

- Benutzerverwaltung mit aktiven/geloeschten Benutzern.
- Rollenverwaltung mit aktiven/geloeschten Rollen.
- Soft-Delete, Restore und Purge fuer User/Rollen.
- Sidebar zeigt Menuepunkte abhaengig von Permissions.
- Systembereiche werden nur fuer Admin/Systemuser sichtbar:
  - System Health nur fuer echten Systemuser `admin`.
  - Migrationen fuer Admins.
  - Backup fuer Systemuser.

## CSRF-Schutz

CSRF ist fuer kritische POST-Aktionen vorgesehen.

Komponenten:

- `admin_csrf.php`
- `admin_csrf_field()`
- `admin_csrf_token()`
- `admin_verify_csrf()`

CSRF-Felder werden in den Admin-Views fuer Formulare gerendert, z. B. Login, Logout, Seiten, Medien, Rollen, Benutzer, Events, Settings.

## Admin-Layout und UI

Layout:

- `CMS/app/includes/layout.php`
- `CMS/app/includes/sidebar.php`
- `CMS/app/includes/components.php`

Styles:

- `admin-layout.css`
- `admin-sidebar.css`
- `admin-components.css`
- seitenbezogene CSS-Dateien wie `admin-pages-edit.css`, `admin-media-list.css`, `admin-login.css`.

Funktionen:

- Dunkles/helles Admin-Theme ueber `/theme`.
- Theme kann ohne harten Reload teilweise im Layout angewendet werden.
- Sidebar kann eingeklappt werden.
- Sidebar-Zustand wird ueber `/prefs` als User-Pref gespeichert.
- Mobile Navigation mit Overlay/Backdrop.
- Media-Picker-Modal per iframe.
- Flash-Nachrichten.
- Cache-Busting fuer CSS/JS via `filemtime()`.
- Favicon aus Site-Settings.
- CMS-Logo in Sidebar aus `cms_logo_light_media_id`/`cms_logo_dark_media_id`.

## Dashboard

Route: `/`
Controller: `DashboardController`
View: `dashboard.php`

Zweck:

- Einstieg ins CMS.
- Sichtbarkeit ueber Permission `dashboard.view`.
- Nutzt das globale Admin-Layout und Sidebar.

## Seitenmodul

Routen:

- `/pages`
- `/pages/edit`
- `/pages/save`
- `/pages/preview`
- `/pages/delete`
- `/pages/restore`
- `/pages/deleted`
- `/pages/purge`

Komponenten:

- `PagesController`
- `PageService`
- `PageRepositoryDb`
- `SeoService`
- `BlockRegistry`
- `BlockValidator`
- PageBuilder-Blocktypen unter `CMS/app/PageBuilder/Blocks`.

Funktionen:

- Seitenliste.
- Seiten erstellen und bearbeiten.
- Soft-Delete, Restore und Purge.
- Startseite markieren.
- Navigation/Seitensortierung ueber Page-Daten.
- SEO-Felder/Meta-Daten.
- Content JSON fuer PageBuilder.
- Revisionen ueber Migration `031_page_revisions.sql`.
- Vorschau-Funktion, inklusive Anreicherung von Event-Blockdaten.
- Mediennutzung wird fuer Seiten/Content verfolgt.

PageBuilder-Blocktypen:

- Hero
- Text
- Image
- Columns
- FAQ
- CTA
- Gallery
- Video
- Contact Form
- Imprint
- Events

Validierung:

- `BlockValidator` prueft Blockdaten serverseitig.
- Blocktypen kapseln Defaults, Validierung und Struktur.

## SEO

Komponenten:

- `SeoService`
- `SeoRepositoryDb`
- Migration `024_seo.sql`
- Migration `025_site_settings_seo.sql`

Funktionen:

- Globale SEO-Defaults in Site-Settings.
- Seitenbezogene SEO-Daten.
- Meta-Titel/-Beschreibung.
- SEO-relevante Frontend-Ausgabe ueber Frontend/Theme-Engine.

## Medienmodul

Routen:

- `/media`
- `/media/show`
- `/media/edit`
- `/media/upload`
- `/media/folder/create`
- `/media/delete`
- `/media/restore`
- `/media/deleted`
- `/media/purge`
- `/media/move`
- `/media/rotate`
- `/media/thumb`
- `/media/file`

Komponenten:

- `MediaController`
- `MediaService`
- `MediaRepositoryDb`
- `MediaFolderRepositoryDb`
- `MediaUsageService`
- `MediaUsageRepositoryDb`
- `media_picker.php`

Funktionen:

- Upload einzelner und mehrerer Dateien.
- Ordnerverwaltung.
- Medienliste.
- Medien bearbeiten.
- Soft-Delete, Restore und Purge.
- Medien verschieben.
- Bilder drehen.
- Thumbnail-Auslieferung.
- Originaldatei-Auslieferung.
- Media-Picker fuer andere Module.
- Vorschau im Picker und in Einstellungs-/Event-Views.
- Media-Usage-Tracking fuer Seiten, Site-Settings, Events und Event-Kategorien.

Sicherheits-/Qualitaetspunkte:

- MIME serverseitig in `MediaService` bestimmt.
- Upload-Logik kapselt Dateihandling.
- Medienauslieferung erfolgt ueber kontrollierte Routen.
- Usage-Counts werden nach Aenderungen neu berechnet.

## Eventmodul

Routen:

- `/events`
- `/events/categories`
- `/events/categories/save`
- `/events/edit`
- `/events/save`
- `/events/delete`
- `/events/restore`
- `/events/deleted`
- `/events/purge`

Komponenten:

- `EventsController`
- `EventRepositoryDb`
- `EventCategoryRepositoryDb`
- `EventsBlock`
- `Frontend/themes/default/blocks/events.php`
- `Frontend/assets/css/theme.css`

Funktionen im Admin:

- Eventliste mit Kategorie-Filter.
- Jahresfilter; aktuelles Jahr wird immer angeboten.
- Abgelaufen-Badge fuer Events, deren Ende vor heute liegt.
- Event erstellen/bearbeiten.
- Event soft-deleten, wiederherstellen, endgueltig loeschen.
- Event-Kategorien verwalten.
- Kategorien besitzen:
  - Name
  - Slug
  - Sortierung
  - Farbe (`color_hex`)
  - Serien-/Kategorie-Logo (`logo_media_id`)
- Kategorien koennen ueber Media-Picker ein Logo erhalten.
- Media-Usage fuer Kategorie-Logos wird synchronisiert.
- Events unterstuetzen:
  - Titel
  - Untertitel
  - Beschreibung/Text
  - Datum bzw. Datumsbereich
  - Publishing-Status
  - Kategoriezuordnung
  - Multi-Kategorien
  - Kategorie-spezifische Medienvarianten
  - Kategorie-spezifische Links

Event-Kategorie-Links:

- Tabelle `event_category_links`.
- Link-Typen:
  - normaler Link
  - PDF
  - YouTube
- PDF kann ueber URL oder Media-ID gepflegt werden.
- YouTube-Links koennen optional Start-/Endzeitfenster besitzen:
  - `youtube_start_at`
  - `youtube_end_at`
- Validierung:
  - YouTube-Endzeit muss nach Startzeit liegen.
  - ungueltige Datetime-Eingaben werden abgelehnt.
  - PDF-Eintraege brauchen URL oder PDF-Media-ID.
  - normale Links brauchen URL.

Frontend-Event-Ausgabe:

- Event-Block kann Events aus API/CMS-Daten anzeigen.
- Kategorie-Filter und Jahresfilter.
- Karten-/Listen-Logik.
- Lead-/naechstes Event.
- YouTube-Einbettung fuer naechstes Event, zeitfensterbasiert.
- Zeitplan-Anzeige fuer naechstes Event.
- Modal fuer Eventdetails.
- Kategorie-Chips mit Farbe und optional Logo.
- Kategorie-spezifische Medienvarianten und Fokuspositionen.
- Kategorie-spezifische Links im Modal.
- PDF/YouTube/Link-Typen werden separat dargestellt.

Wichtige Migrationen:

- `034_events_module.sql`
- `035_events_multicat_daterange.sql`
- `036_event_category_media.sql`
- `037_event_category_color.sql`
- `038_events_subtitle.sql`
- `039_events_category_links.sql`
- `040_event_category_logo.sql`
- `041_event_category_links_youtube_window.sql`

## Site Settings und Branding

Route:

- `/settings`

Komponenten:

- `SiteSettingsController`
- `SiteSettingsRepositoryDb`
- `settings_site.php`
- `ThemeEngine`
- API-Brand-Settings in `CMS/public/api.php`

Funktionen:

- Site-Titel.
- Tagline.
- Locale.
- Timezone.
- Favicon.
- Allgemeines Logo.
- CMS-Logo hell.
- CMS-Logo dunkel.
- Brand-Farben:
  - primary
  - secondary
  - tertiary
- SEO-Defaults.
- Speicherung in `site_settings`.
- Media-Usage-Synchronisierung fuer Site-Settings.

API-Ausgabe:

- Brand-Farben werden validiert.
- Logo-URLs werden aus Media-IDs als `/media/file?id=...` erzeugt.
- CMS-Logo-URLs werden separat ausgegeben.

## Backup

Route:

- `GET /backup`
- `POST /backup/db`

Komponenten:

- `BackupController`
- `BackupService`
- View `backup.php`

Funktionen:

- Datenbankexport.
- Zugriff ist auf den System-/Adminbereich begrenzt.
- Backup-Bereich ist in Sidebar nur fuer berechtigte Systemuser sichtbar.

## Migrationen

Route:

- `/migrate`

Komponenten:

- `MigrateController`
- `MigrationsRunner`
- `CMS/scripts/migrate.php`

Funktionen:

- Migrationen anzeigen/ausfuehren.
- Baseline setzen.
- SQL-Dateien liegen numerisch versioniert in `CMS/migrations`.
- Einige Repositories enthalten zusaetzlich defensive Best-Effort-Schema-Erweiterungen, damit neue Spalten bei aelteren Installationen nicht sofort hart brechen.

## System Health und Profiling

Routen:

- `/system/health`
- `/system/health/api`
- `/system/health/reset`

Komponenten:

- `SystemHealthController`
- `profiler.php`
- View `system_health.php`
- CSS `admin-system-health.css`

Funktionen:

- System-/Request-/DB-Informationen.
- Profiling-Hooks werden im Bootstrap vor DB geladen.
- Response-Time-Header `X-Response-Time-ms`.
- Reset-Funktion fuer Health-Daten.

## API

Datei: `CMS/public/api.php`

Zentrale Aufgaben:

- JSON-Antworten.
- API-Fehlerbehandlung.
- Deploy-Token-Pruefung fuer geschuetzte Deploy-Endpunkte.
- Brand-Settings aus `site_settings`.
- Eventdaten fuer Frontend.
- Medien-/Logo-URLs in API-Daten.
- Kategorie-Farben, Kategorie-Logos, Event-Medienvarianten und Kategorie-Links.
- YouTube-Zeitfenster in Event-Kategorie-Links.

Security:

- Deploy-Endpunkte pruefen `X-Deploy-Token` gegen `DEPLOY_TOKEN`.
- Fehlerausgaben sind JSON-basiert.
- Spalten-/Tabellenverfuegbarkeit wird defensiv geprueft, damit API mit unterschiedlichen Migrationsstaenden umgehen kann.

## Frontend-Integration

Das separate Frontend liegt in `Frontend`, konsumiert CMS-Daten und rendert Seiten.

Relevante Dateien:

- `Frontend/index.php`
- `Frontend/app/CmsApiClient.php`
- `Frontend/app/view.php`
- `Frontend/templates/layout.php`
- `Frontend/templates/page.php`
- `Frontend/themes/default/layout.php`
- `Frontend/themes/default/blocks/*`
- `Frontend/assets/css/theme.css`
- `Frontend/assets/css/brand.php`

CMS-seitige Frontend-Komponenten:

- `CMS/app/Frontend/ThemeEngine.php`
- `CMS/app/Frontend/BlockRenderer.php`
- `CMS/app/Frontend/SitemapController.php`

Eventdaten werden im Frontend angereichert:

- Medienvarianten werden absolut auf CMS-Basis-URL aufgeloest.
- Kategorie-Link-URLs werden absolut aufgeloest.
- Kategorie-Logo-Map wird absolut aufgeloest.

## Plugin-System

Komponenten:

- `App\Core\PluginLoader`
- `App\Core\Hooks`
- Beispielplugin: `CMS/plugins/example-plugin/plugin.php`
- Dokumentation:
  - `CMS/docs/plugin-api.md`
  - `CMS/docs/plugin-hooks.md`

Funktionen:

- Plugins werden im Bootstrap geladen.
- Hook `cms_bootstrap_done` wird nach Bootstrap ausgeloest.
- Plugin-API ist dokumentiert.
- Plugins koennen sich an definierten Hooks einklinken.

## Logging

Komponenten:

- `CMS/app/FileLogger.php`
- `shared/FileLogger.php` als bevorzugter shared Logger, falls vorhanden.
- Bootstrap laedt shared Logger, sonst lokalen Fallback.

Zweck:

- Fehler-/Systemlogging.
- Fallback fuer Standalone-CMS-Deployments.

## Security-Zusammenfassung

Aktuelle erkennbare Sicherheitsmechanismen:

- Strict Types in vielen PHP-Dateien.
- Zentrales Bootstrap fuer Sessions und Security-Defaults.
- Session-Cookie-Hardening:
  - Secure bei HTTPS.
  - HttpOnly.
  - SameSite Lax.
  - Strict Mode.
  - Cookies only.
- CSRF-Schutz fuer POST-Aktionen.
- POST-only Logout.
- Rollen-/Permission-System.
- Admin-only und Systemuser-only Bereiche.
- Setup-Redirect verhindert normale Nutzung vor Installation.
- Environment-gesteuerte Fehleranzeige:
  - Development zeigt Fehler.
  - Production unterdrueckt Display Errors.
- Interne Pfadsicherung ueber `Paths::safeInternal`.
- Output escaping ueber `h()`/`htmlspecialchars`.
- API Deploy-Token-Pruefung.
- Soft-Delete fuer zentrale Datenobjekte.
- Migrationen defensiv/idempotent.
- Media-IDs werden normalisiert.
- Farben werden als Hex validiert.
- YouTube-/Datetime-Eingaben werden serverseitig validiert.
- Media-Auslieferung laeuft ueber kontrollierte Endpunkte.
- Audit-/Logout-Logging darf Benutzerfluss nicht blockieren.

Bekannte technische Vorsichtspunkte:

- Einige Bestandsdateien enthalten Encoding-Artefakte in Kommentaren/UI-Texten.
- `upload.sh` ist sehr breit (`put -r *`) und sollte nicht fuer selektive Deployments genutzt werden.
- Einige Repositories fuehren Best-Effort-ALTERs aus; sauberer waere langfristig, Schemaaenderungen ausschliesslich ueber Migrationen zu erzwingen.
- Diese lokale Dokumentationsdatei liegt bewusst in einem versteckten Ordner, damit sie nicht in Deploy-/Commit-Flows faellt.

## Datenmodell grob

Zentrale Tabellen laut Migrationen/Repos:

- Admin/User:
  - `users`
  - `roles`
  - `permissions`
  - `role_permissions`
  - `user_roles`
  - `admin_user_prefs`
  - `login_tokens` bzw. login-token-nahe Tabellen/Spalten
  - `password_resets`
- Content:
  - `pages`
  - Page-SEO-/Meta-Felder
  - Page-Revisions
- Navigation:
  - fruehe `navigation_items`, spaeter Migration zu Pages/Nav-Feldern
- Settings:
  - `site_settings`
  - SEO-Defaults
  - Brand-Farben
  - Logos/Favicon
- Media:
  - `media`
  - `media_folders`
  - `media_usages`
- Events:
  - `events`
  - `event_categories`
  - `event_category_map`
  - `event_category_media`
  - `event_category_links`
- Changelog:
  - `changelogs`

## Aktueller Stand fuer spaetere Changelog-Ableitung

Wenn spaeter ein Changelog erzeugt wird, sollten Aenderungen gegen diese Themenachsen bewertet werden:

- Auth/Login/Passwort-Reset.
- Admin-Layout/Sidebar/Theme/Prefs.
- Rollen, Rechte, Benutzer.
- Seiten/PageBuilder/SEO.
- Medienbibliothek/Media Picker/Usage Tracking.
- Events/Kategorien/Links/YouTube/Frontend-Modal.
- Site Settings/Branding/API.
- Backup/Migration/System Health.
- Plugin-System.
- Security/Hardening.
- Deploy-/Setup-Verhalten.

## Nicht in dieser Datei abgebildet

Diese Datei ist eine technische Bestandsaufnahme aus dem aktuellen Codezustand. Sie ersetzt keine vollstaendige Endnutzer-Dokumentation, keine API-Spezifikation und kein Security-Audit. Sie ist aber bewusst detailliert genug, um spaeter nachvollziehbar zu entscheiden, welche neuen Aenderungen changelog-relevant sind.
