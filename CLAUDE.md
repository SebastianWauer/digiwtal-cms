# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with
code in this repository.

@DESIGN.md

## Was das Projekt ist

DIGIWTAL CMS — ein kundenneutrales PHP-CMS mit Redaktionsoberfläche und
REST-API. Jeder Kunde betreibt eine **isolierte Instanz** mit eigener Datenbank
und eigenen Medien; alle Instanzen laufen auf demselben Programmcode aus diesem
Repository. Ausgerollt wird zentral aus der
[Verwaltung](../Verwaltung) heraus (siehe *Deployment*).

Das **Kundenfrontend ist ein eigenes Projekt** und liegt nicht hier. Das ist
für die Arbeit am Blocksystem entscheidend — siehe *Blöcke gehen über zwei
Repositories*.

## Kein Composer, kein Build, keine Tests im Repo

Plain PHP 8.x. **Kein Composer, kein `vendor/`, kein Build-Schritt, keine
Testsuite.** Das ist Absicht und in [docs/testing.md](docs/testing.md)
begründet: Tests, Composer und `vendor/` wurden entfernt, um das Repo schlank
zu halten; auf dem Live-Server werden sie nicht gebraucht.

Fehlende `composer.json`, `phpunit.xml` und `Tests/` sind also **kein defekter
Zustand**, den man reparieren sollte. Wer lokal PHPUnit braucht, findet die
Einrichtung in `docs/testing.md`; die dort erzeugten Dateien gehören nicht ins
Repo.

Autoloading übernimmt ein handgeschriebener PSR-4-Loader für `App\` in
[app/bootstrap.php](app/bootstrap.php). Ein vorhandenes `vendor/autoload.php`
wird eingebunden, falls es lokal existiert, ist aber nicht vorausgesetzt.

## Befehle

```bash
# Lokal starten (Admin-Oberfläche)
php -S localhost:8001 -t public

# Frontend-Renderer lokal (eigener Einstiegspunkt)
php -S localhost:8000 public/web.php

# Migrationen anwenden
php scripts/migrate.php

# Nur anzeigen, was anstehen würde
php scripts/migrate.php --dry-run

# Syntaxprüfung einer geänderten Datei (es gibt keinen Linter im Projekt)
php -l app/Controller/PagesController.php
```

Vorbereitung: `.env.example` nach `.env` kopieren und mindestens `DB_HOST`,
`DB_NAME`, `DB_USER`, `DB_PASS` eintragen. Ohne erreichbare Datenbank leitet
`public/index.php` auf `/setup` um.

`APP_ENV=development` in der `.env` schaltet `display_errors` ein und
registriert zusätzlich die Route `/test`.

**Einzelnen Test ausführen:** nicht möglich — es gibt keine Tests im
Repository (siehe oben). Prüfungen laufen über `php -l`, die Health-Seite
unter `/system/health` und den Health-Endpunkt `/api.php/api/health`.

## Architektur

### Drei Einstiegspunkte, ein Bootstrap

| Datei | Zweck |
|---|---|
| `public/index.php` | Admin-Oberfläche. Definiert die komplette Routentabelle. |
| `public/api.php` | REST-API mit eigenem Exception-Handler und Logging nach `storage/api_error.log`. |
| `public/web.php` | Öffentliches Kundenfrontend. Läuft auf eigener Domain; Apache leitet Nicht-`cms.`-Hosts hierher. |

Alle drei laden `app/bootstrap.php`. Die Wurzel wird über die Umgebungsvariable
`CMS_APP_ROOT` bestimmt, die der Webserver per `SetEnv` setzt — nicht über die
`.env`, weil sie gebraucht wird, bevor diese gelesen wird.

### Die Reihenfolge in `bootstrap.php` ist bedeutungstragend

Sie ist kommentiert, und die Kommentare sind ernst gemeint: `Paths` und
`Redirect` werden vor allem anderen hart eingebunden, weil Legacy-Funktionen
sie direkt referenzieren; `profiler.php` muss vor `db.php` geladen sein, weil
`db.php` dessen Funktionen benutzt; `db.php` vor `admin_auth.php`. Am Ende
lädt der `PluginLoader` und feuert `cms_bootstrap_done`.

Wer hier etwas umsortiert, bricht Dinge an weit entfernten Stellen.

### Routing

`App\Http\Router` kennt statische Routen und Parameter-Segmente
(`{id:\d+}`, `{slug:.+}`). Die gesamte Admin-Routentabelle steht am Stück in
`public/index.php` — der schnellste Weg, den Funktionsumfang zu überblicken.
Interne Pfade stehen als Konstanten in `App\Core\Paths`; dort ist auch
`cms_base_path()` verankert, das Deployments in Unterverzeichnissen erlaubt
(`CMS_BASE_PATH`). URLs deshalb nie hart schreiben.

### Zwei Funktionsschichten nebeneinander

Das Projekt mischt bewusst prozedurale Legacy-Helfer mit Klassen. Beides ist
in Gebrauch, keins wird abgelöst:

- **Prozedural, global verfügbar:** `db()`, `admin_current_user()`,
  `admin_require_login()`, `admin_can($key)`, `admin_require_perm($key)`,
  `admin_csrf_field()`, `admin_verify_csrf()`, `h()`.
- **Klassen unter `App\`:** Controller, `Services/`, `Repositories/`,
  `PageBuilder/`, `Frontend/`, `Core/`.

Controller sind dünn und rufen beides. Rechteprüfung passiert am Anfang der
Controller-Methode über `admin_require_perm()`; Formulare tragen
`admin_csrf_field()`, POST-Handler rufen `admin_verify_csrf()`.

### Repositories

Jede Entität hat ein Interface plus eine `…Db`-Implementierung
(`PageRepositoryInterface` / `PageRepositoryDb`). Neue Datenzugriffe folgen
dem Muster; Controller und Services sprechen gegen das Interface.

### Blöcke gehen über zwei Repositories

Der Seiten-Editor arbeitet mit typisierten Blöcken. Ein Blocktyp ist an drei
Stellen **in diesem Repo** definiert:

1. Eine Klasse unter `app/PageBuilder/Blocks/`, die
   `BlockTypeInterface` erfüllt: `type()`, `label()`, `defaults()`, `fields()`,
   `definition()`.
2. Eine Zeile in `BlockRegistry::types()` — die Liste ist handgepflegt, es gibt
   keine automatische Erkennung.
3. Die Editor-Oberfläche in `app/Views/pages_edit.php` (rund 2.900 Zeilen,
   überwiegend Inline-JavaScript) und das zugehörige CSS in
   `public/assets/css/admin-pages-edit.css`.

**Das Rendering liegt nicht hier.** `App\Frontend\BlockRenderer` sucht das
Template unter `themes/default/blocks/<type>.php` im **Kundenfrontend**,
aufgelöst über `App\Core\FrontendSource` und die `.env`-Variable
`FRONTEND_SOURCE_DIR`. Ohne diese Zuordnung bleiben CMS und API voll
funktionsfähig, nur die Vorschau fehlt.

Ein neuer Blocktyp ist damit **nie** allein in diesem Repository fertig.

### Migrationen

Nummerierte SQL-Dateien in `migrations/` (`001_…` bis aktuell `056_…`).
Angewendete Migrationen stehen in `schema_migrations`. Der Runner
`scripts/migrate.php` ist eine dünne Hülle um die `db_*`-Funktionen in
`app/db.php` und bringt bewusst **keine** eigene Verbindung und keine eigene
Statustabelle mit — genau daran ist eine frühere Fassung gescheitert.

Neue Migration: nächste freie Nummer, sprechender Name, nie eine bestehende
Datei ändern.

### Plugins und Hooks

`App\Core\PluginLoader` scannt `plugins/` beim Bootstrap alphabetisch und lädt
je Unterverzeichnis `plugin.php`. Erweiterungspunkte laufen über
`App\Core\Hooks` (`do_action`, Filter, Prioritäten). Die vollständige
Referenz steht in [docs/plugin-api.md](docs/plugin-api.md) und
[docs/plugin-hooks.md](docs/plugin-hooks.md), ein lauffähiges Beispiel unter
`plugins/example-plugin/`.

### Setup

Ist das CMS nicht installiert, leitet `public/index.php` jede Anfrage außerhalb
von `/setup` und `/assets` dorthin um (`App\Core\Setup::allowSetupRequest()`).
Der dreistufige Assistent liegt unter `app/Setup/` und `app/Views/setup_step*.php`.

## Deployment

Ausgerollt wird **nicht** von hier aus, sondern über GitHub Actions. Ein Push
auf `main` startet [.github/workflows/deploy-all.yml](.github/workflows/deploy-all.yml),
das sich die Instanzliste aus der Verwaltung holt und pro Kunde
[deploy.yml](.github/workflows/deploy.yml) auslöst: `rsync --delete` auf den
Webspace, `.env` aus dem Vault der Verwaltung zusammenführen, Migrationen
ausführen, Health prüfen und das Ergebnis zurückmelden.

**Ein Push auf `main` ist damit ein Rollout an alle Kunden.** Er ist nicht
durch ein `git revert` zurückzuholen.

GitHub kennt nur zwei Secrets (`VERWALTUNG_URL`, `VERWALTUNG_CI_TOKEN`); alles
andere — Hosts, Datenbanken, Tokens — liegt im Vault der Verwaltung. Der Grund
steht als Kommentarblock oben in `deploy.yml`: auf IONOS-Webspace fehlt die
ssh2-Erweiterung, es gibt keine nutzbare Shell und der FTP-Port ist zu.

`CLAUDE.md`, `DESIGN.md` und `AGENTS.md` sind aus der rsync-Übertragung
ausgenommen und erreichen keinen Kunden-Webspace.

## Verhältnis zu den anderen Regeldateien

- `../CLAUDE.MD` gilt für alle Projekte unter `f:\Websites\` und bleibt in
  Kraft. Diese Datei ergänzt sie, sie ersetzt sie nicht.
- [DESIGN.md](DESIGN.md) trägt die Designregeln dieses Projekts. Was dort als
  **TODO: ungeklärt** steht, wird nicht plausibel gefüllt, sondern entschieden.
- [AGENTS.md](AGENTS.md) trägt den verbindlichen Änderungs- und
  Release-Prozess: nach jeder abgeschlossenen Änderung ausdrücklich fragen, ob
  übernommen und ausgerollt werden soll — ohne Zustimmung nicht committen,
  pushen, mergen oder deployen.
