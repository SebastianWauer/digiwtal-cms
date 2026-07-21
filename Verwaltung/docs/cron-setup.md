# Cron Setup: health_check.php

## Empfohlener Cron-Job

```cron
*/5 * * * * php /pfad/zum/projekt/Verwaltung/scripts/health_check.php
```

## Was das Skript macht

- Lädt aktive Kunden und deren hinterlegte Health-Check-URLs.
- Führt periodische HTTP-Checks für CMS/Frontend-Instanzen aus.
- Schreibt Ergebnisse und Status in die Verwaltung (Monitoring/Übersicht).
- Protokolliert Fehlerfälle für Betrieb und Troubleshooting.

## Benötigte Umgebungsvariablen

Die Werte werden aus `Verwaltung/.env` gelesen:

- `DB_HOST`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`
- optional: `HEALTH_CHECK_TIMEOUT` (falls im Skript unterstützt)

Hinweis: Der Cron-Prozess muss im selben Environment laufen, in dem diese Variablen verfügbar sind.
