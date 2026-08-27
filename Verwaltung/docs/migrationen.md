# Migrationen der Verwaltung

Die Verwaltung hat einen eigenen Migrationsstand, getrennt vom CMS. Gefuehrt
wird er in der Tabelle `schema_migrations`, und zwar **ueber den Dateinamen**,
nicht ueber die Nummer: `008` und `009` sind je zweimal vergeben, weil die
SQL-Dateien frueher von Hand eingespielt wurden.

## Wo wird das eingegeben?

**Im Browser, unter `/admin/migrations`** - in der Seitenleiste unter
*Datenbank*, sichtbar nur fuer Superadmins.

Der Webspace der Verwaltung hat keine Shell: PHP laeuft dort als `cgi-fcgi`,
die `ssh2`-Erweiterung fehlt, Port 21 ist zu. Eine Kommandozeile gibt es dort
schlicht nicht - deshalb ist die Seite der vorgesehene Weg.

Auf einem Rechner mit PHP-CLI geht zusaetzlich:

```bash
php scripts/migrate.php --dry-run    # nur anzeigen
php scripts/migrate.php --baseline   # Bestand markieren, nichts ausfuehren
php scripts/migrate.php              # offene Migrationen anwenden
```

Beide Wege benutzen dieselbe Klasse: `services/MigrationRunner.php`.

## Der erste Lauf auf einer gewachsenen Installation

Die bestehende Verwaltung hat ihre Tabellen laengst - nur weiss
`schema_migrations` nichts davon. Wuerde man dort direkt *Anwenden* druecken,
scheiterte gleich `001_create_admin_users.sql` daran, dass die Tabelle schon
existiert.

Deshalb einmalig **Bestand markieren**: Das traegt Dateien als angewendet ein,
ohne sie auszufuehren.

Entscheidend ist dabei, *welche*. Pauschal alles zu markieren waere ein Fehler -
eine wirklich neue Migration wie `023_ci_tokens.sql` wuerde damit als erledigt
gelten und nie laufen, und die Tabelle `ci_tokens` fehlte auf Dauer. Die Seite
schaut deshalb fuer jede offene Datei nach, welche Tabellen sie anfasst und ob
es die schon gibt:

- **vorhanden** - die Tabellen stehen bereits, die Datei wurde frueher von Hand
  eingespielt. Haken ist gesetzt.
- **neu** - die Tabelle fehlt noch, die Datei muss wirklich laufen. Kein Haken.

Der Vorschlag ist nur das: ein Vorschlag. Die Haken lassen sich einzeln
korrigieren.

Auf dem heutigen Stand heisst das: 24 Dateien als Bestand markieren, danach
laeuft `023_ci_tokens.sql` als einzige echte Migration - die Tabelle fuer den
Rollout ueber GitHub Actions.

Auf einer frischen Datenbank ist nichts vorausgewaehlt, weil keine Tabelle
existiert. Dort wird nur *Anwenden* gedrueckt, und alle Dateien laufen der
Reihe nach.

## Reihenfolge bei einer neuen Datei

1. SQL-Datei nach `Verwaltung/migrations/` legen, fortlaufend nummeriert.
2. Dateien auf den Webspace bringen.
3. `/admin/migrations` oeffnen, Eintrag unter *Offene Migrationen* pruefen.
4. *Anwenden* druecken.

Schlaegt eine Datei fehl, bricht der Lauf dort ab. Was davor lief, bleibt
angewendet und markiert; der Rest bleibt offen, und die Fehlermeldung samt
SQL-Fehlercode steht oben auf der Seite. Jeder Lauf landet ausserdem im
Audit-Log (`migration.applied`, `migration.baseline`, `migration.failed`).
