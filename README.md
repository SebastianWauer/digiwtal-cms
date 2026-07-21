# Digiwtal

Ein selbst entwickeltes, Framework-freies PHP-Ökosystem für den Betrieb von Websites – bestehend aus einem Redaktions-CMS, einem entkoppelten Frontend und einem zentralen Betreiber-Panel für die Verwaltung mehrerer Kundeninstanzen.

Das Projekt besteht aus drei eigenständigen Anwendungen, die zusammenarbeiten, aber getrennt deploybar sind:

| Anwendung | Ordner | Zweck | Eigene README |
|-----------|--------|-------|---------------|
| **CMS** | [`CMS/`](CMS/) | Redaktionssystem mit Page-Builder, Medien, Events, News und öffentlicher REST-API | [CMS/README.md](CMS/README.md) |
| **Frontend** | [`Frontend/`](Frontend/) | Öffentliche Website, die Inhalte per API aus dem CMS bezieht und rendert | [Frontend/README.md](Frontend/README.md) |
| **Verwaltung** | [`Verwaltung/`](Verwaltung/) | Betreiber-Panel: Kunden, Credential-Vault, Deployments, Monitoring | [Verwaltung/README.md](Verwaltung/README.md) |

Zusätzlich gibt es `shared/` mit projektübergreifendem Code (aktuell `FileLogger.php`).

## Wie die Teile zusammenspielen

```
                    ┌──────────────────────┐
                    │     Verwaltung       │  Betreiber-Panel (mehrere Kunden)
                    │  Vault · Deploy ·    │
                    │  Monitoring · 2FA    │
                    └──────────┬───────────┘
                     deployt & │ überwacht
                       provisioniert
              ┌────────────────┴────────────────┐
              ▼                                  ▼
      ┌───────────────┐   REST /api/v1/  ┌───────────────┐
      │      CMS       │◄────────────────│    Frontend    │
      │ Redaktion +    │   liefert Seiten │  rendert die   │
      │ öffentliche API│   Events, News,  │  Website für    │
      │                │   Settings       │  Besucher      │
      └───────────────┘                   └───────────────┘
```

- Das **CMS** ist die Datenquelle. Redakteure pflegen dort Seiten, Medien, Events und News. Es stellt öffentliche Inhalte unter `/api/v1/...` bereit.
- Das **Frontend** hält selbst keine Inhalte. Es ruft bei jedem Seitenaufruf die CMS-API auf (`CMS_API_URL`) und rendert die zurückgegebenen Blöcke über sein Theme-System.
- Die **Verwaltung** ist die Betriebsebene darüber. Sie kennt die Kunden, verwaltet deren Server-Zugangsdaten in einem verschlüsselten Vault, provisioniert neue CMS-Instanzen und rollt Deployments per SFTP/SSH aus. Ein Health-Check-Cron überwacht die laufenden CMS-/Frontend-Instanzen.

## Technische Grundlagen (für alle drei Apps gleich)

- **Sprache:** PHP 8.3 (nutzt `readonly`, `enum`, `match`, benannte Argumente – PHP 8.1 ist Minimum)
- **Keine Abhängigkeiten:** kein Composer, kein Framework, keine `vendor/`. Jede App bringt ihren eigenen kleinen Router, Autoloader und `.env`-Loader mit.
- **Datenbank:** MySQL/MariaDB über PDO. Schema wird über nummerierte SQL-Migrationen in `*/migrations/` verwaltet (Statustabelle `schema_migrations`).
- **Konfiguration:** pro App eine `.env`-Datei (siehe jeweilige README). `.env` ist in `.gitignore` und darf **niemals** eingecheckt werden.

## Schnellstart (lokale Entwicklung)

Voraussetzung: PHP 8.1+ mit den Erweiterungen `pdo_mysql`, `openssl`, `curl`, `mbstring`, `fileinfo` sowie eine erreichbare MySQL-/MariaDB-Datenbank.

```bash
# 1. CMS starten (inkl. geführtem Web-Setup unter /setup)
cp CMS/.env.example CMS/.env      # Werte eintragen
php -S localhost:8001 -t CMS/public

# 2. Frontend starten (zeigt auf die CMS-API aus Schritt 1)
#    Frontend/.env anlegen mit CMS_API_URL=http://localhost:8001
php -S localhost:8002 CMS/public/web.php   # bzw. Frontend/index.php als Router

# 3. Verwaltung starten (optional, eigenes .env nötig)
php -S localhost:8003 -t Verwaltung/public
```

Details, Reihenfolge und alle nötigen `.env`-Schlüssel stehen in der README der jeweiligen App.

## Deployment

Es gibt zwei Deploy-Wege, die parallel existieren:

1. **GitHub Actions** (`.github/workflows/deploy.yml`): Bei Push auf `main` wird per `rsync` über SSH auf den IONOS-Webspace ausgerollt. Secrets (`DEPLOY_SSH_KEY`, `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_PATH`, `DEPLOY_PORT`) liegen in den Repository-Secrets.
2. **Verwaltung-Deploy** (`Verwaltung/`): Panel-gesteuertes Ausrollen einzelner Kundeninstanzen per SFTP/SSH, inkl. lokalem Deploy-Agent für den SFTP-Transfer vom eigenen Rechner.

## Repository-Struktur

```
digiwtal-cms/
├── CMS/          → Redaktions-CMS + öffentliche API
├── Frontend/     → öffentliche Website (API-Client)
├── Verwaltung/   → Betreiber-Panel (Kunden, Vault, Deploy)
├── shared/       → projektübergreifender Code (FileLogger)
├── .github/      → CI/CD (rsync-Deploy auf IONOS)
└── README.md     → dieses Dokument
```

## Sicherheitshinweise

- Keine `.env`-Dateien, keine privaten Schlüssel (`Frontend/keys/`, `Verwaltung/agent/certs/*.key`) und keine `secrets/` einchecken – sie stehen in `.gitignore`.
- Der Vault-Schlüssel (`VAULT_KEY_BASE64`) der Verwaltung ist der Generalschlüssel für alle gespeicherten Server-Zugangsdaten. Geht er verloren, sind die Daten unlesbar; wird er kompromittiert, sind alle Zugänge offen.
- Interne CMS-API-Endpunkte (`/api/internal/...`) sind durch `DEPLOY_TOKEN` geschützt, das Web-Setup durch `SETUP_TOKEN`.
