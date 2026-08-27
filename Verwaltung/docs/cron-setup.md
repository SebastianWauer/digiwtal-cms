# Ueberwachung der Instanzen

Zwei Quellen pruefen dieselben Instanzen mit demselben Code. Faellt eine aus,
laeuft die andere weiter - und das Dashboard sagt es, wenn beide schweigen.

| Quelle | Wo | Takt | Traegt sich ein als |
|---|---|---|---|
| Cron der Verwaltung | IONOS-Webspace | alle 5 Minuten | `cron` |
| Geplanter CI-Lauf | GitHub Actions (`health.yml`) | alle 5 Minuten, versetzt | `ci` |
| Rollout einer Instanz | GitHub Actions (`deploy-instanz.yml`) | bei jedem Rollout | `rollout` |

## Cron-Job

### Empfohlen: ausfuehrbarer Pfad in der IONOS-Cronverwaltung

Die IONOS-Eingabevalidierung akzeptiert je nach Vertrag nur einen Pfad aus
Buchstaben, Zahlen, Bindestrich, Unterstrich, Punkt und Schraegstrich. Deshalb
steht ein ausfuehrbarer Wrapper ohne Argumente bereit:

```cron
*/5 * * * * /home/www/Verwaltung/scripts/health-cron.sh
```

Der Wrapper startet PHP 8.4 und schreibt dessen Ausgabe nach
`/home/www/storage/logs/health-cron.log`. Im IONOS-Formular wird nur der Pfad
ohne vorangestellten Cron-Ausdruck eingetragen; das Intervall hat ein eigenes
Feld.

### Alternative: geschuetzter HTTP-Aufruf

Cron-Umgebungen, die Argumente und HTTP-Header erlauben, koennen mit einem
eigenen CI-Token direkt den Verwaltungs-Endpunkt aufrufen:

```cron
*/5 * * * * /usr/bin/curl -fsS -X POST -H "X-Ci-Token: <TOKEN>" https://verwaltung.digiwtal.de/api/ci/health-run
```

### Alternative: PHP direkt ausfuehren

```cron
*/5 * * * * /usr/bin/php8.4 -f /pfad/zum/projekt/Verwaltung/scripts/health_check.php
```

Das Skript ist ein duenner Aufruf um `services/HealthMonitor.php`. Benoetigte
Werte kommen aus `Verwaltung/.env`: `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`,
optional `HEALTH_ALERT_EMAIL` (Standard: info@digiwtal.de) und `HC_DEBUG=1` fuer
ausfuehrliche Logzeilen.

## Geplanter CI-Lauf

`.github/workflows/health.yml` braucht dieselben zwei Secrets wie der Rollout:
`VERWALTUNG_URL` und `VERWALTUNG_CI_TOKEN`. Der Lauf holt die Instanzen ueber
`GET /api/ci/instances`, misst sie mit `.github/scripts/health_sweep.php` und
meldet die Messung an `POST /api/ci/health-report`.

Zwei Eigenheiten von GitHub, die den Cron als zweiten Takt rechtfertigen:
Geplante Laeufe starten nicht minutengenau, und in einem Repository ohne
Aktivitaet werden sie nach 60 Tagen abgeschaltet.

## Was wo entschieden wird

- **Gemessen** wird an zwei Orten, mit denselben statischen Methoden
  (`HealthMonitor::probeCms()` / `probeFrontend()`).
- **Bewertet** wird an genau einer Stelle: `HealthMonitor::evaluate()` in der
  Verwaltung. Auch die Pipeline schickt nur ihre Rohmessung dorthin.
- **Alarmiert** (Mail und Push) wird beim Statuswechsel, ebenfalls nur dort.

## Wenn im Dashboard "Die Überwachung meldet sich nicht" steht

Dann ist seit ueber 20 Minuten kein Pruflauf mehr angekommen - unabhaengig
davon, was die Kundenkarten zeigen. Die Reihenfolge zum Nachsehen:

1. `monitor_runs` in der Datenbank: Welche Quelle hat sich zuletzt gemeldet?
2. Actions-Tab: Laeuft `Instanzen ueberwachen` noch, und ist er gruen?
3. IONOS-Oberflaeche: Steht der Cronjob noch, und zeigt er auf den richtigen Pfad?
4. `HC_DEBUG=1` setzen und den Cron einmal von Hand starten.

## Adresse des Health-Endpunkts

`https://<cms-domain>/api.php/api/health`, Token im Header `X-Health-Token`.

Ohne `api.php` landet der Aufruf beim Admin-Front-Controller und antwortet mit
404 - die Instanz gilt dann als "down", obwohl sie laeuft. `HealthMonitor::cmsHealthUrl()`
baut die Adresse deshalb an einer Stelle fuer alle Aufrufer.
