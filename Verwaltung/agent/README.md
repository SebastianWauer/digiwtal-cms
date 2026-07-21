# Lokaler Deploy-Agent

Der lokale Agent fuehrt SFTP-Deployments auf deinem Rechner aus. Die Verwaltung liefert nur das Deploy-Payload.

## Zertifikat erzeugen

Einmalig:

```bash
php Verwaltung/agent/generate_cert.php
```

Danach das Zertifikat fuer `127.0.0.1` bzw. `localhost` im System/Browser vertrauen.

## Start

Im Projekt-Root starten:

```bash
php Verwaltung/agent/server.php
```

Dann auf der Deploy-Seite den Bereich `Lokaler SFTP-Agent` nutzen.

## Verhalten

- `CMS`: nutzt den lokalen Projektordner `CMS`
- `Frontend`: nimmt den im Browser gewaehhlten Ordner, komprimiert ihn zu `tar.gz` und uebergibt ihn an den Agenten
- `Combined`: kombiniert beides

## Plattformen

- macOS / Linux: nutzt `sshpass + sftp`
- Windows: nutzt `WinSCP.com`

## Windows

Installiere WinSCP oder setze den Pfad explizit:

```powershell
$env:WINSCP_PATH="C:\Program Files\WinSCP\WinSCP.com"
php Verwaltung/agent/server.php
```
