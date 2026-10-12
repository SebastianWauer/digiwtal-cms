# SVG-Logos mit eingebetteten Bildern

Der Uploadfilter entfernt aktive SVG-Inhalte, externe Quellen und ungueltige
XML-Definitionen. Eingebettete PNG-, JPEG- und WebP-Bilder werden anhand ihres
tatsaechlichen Formats geprueft. Eingebettete SVGs werden rekursiv mit demselben
Filter bereinigt, begrenzt auf drei Ebenen und maximal 10 MB je Dokument.

Bei den bisherigen Sport-Voice-Logo-Dateien wurde der Bildinhalt bereits beim
Upload entfernt. Eine Filteraenderung allein stellt diese Dateien nicht wieder
her. Die Originale liegen im zugeordneten Frontend unter `assets/sport-voice/`.

Die Reparatur darf nur im Sport-Voice-CMS ausgefuehrt werden:

```sh
php scripts/repair-sport-voice-logos.php
php scripts/repair-sport-voice-logos.php --apply
```

Ohne `--apply` werden keine Dateien veraendert. Nur die fuenf bekannten
Mono-Logo-Dateinamen mit leerem Bildinhalt und dem alten `mono`-Filter werden
ersetzt. Intakte Logos werden uebersprungen. Medien-IDs und Seitenzuordnungen
bleiben erhalten. Sicherungen liegen unter `storage/backups/svg-logos/`.

Alternativ beim kundenbezogenen CMS-Workflow `Deploy to IONOS` die optionale
Eingabe `sport_voice_logos_reparieren` aktivieren. Sie ist standardmaessig aus
und wird bei den automatischen allgemeinen Rollouts nicht ausgefuehrt.
Zuvor das aktualisierte Sport-Voice-Frontend ausrollen: Die Logo-Leiste bekommt
einen neuen Cache-Schluessel fuer CMS-Bilder, um alte leere Dateien zu umgehen.

Lokale Pruefung ohne Datenbankzugriff:

```sh
php scripts/check-svg-sanitizer.php /pfad/zum/Frontend/assets/sport-voice
```
