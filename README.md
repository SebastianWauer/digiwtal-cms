# DigiWtal CMS

Zentrales, kundenneutrales PHP-CMS mit Redaktionsoberflaeche und REST-API.
Jeder Kunde betreibt eine isolierte CMS-Instanz mit eigener Datenbank und
eigenen Medien; alle Instanzen verwenden denselben Programmcode.

## Lokal starten

1. `.env.example` nach `.env` kopieren und Datenbankzugang eintragen.
2. Migrationen mit `php scripts/migrate.php` ausfuehren.
3. `php -S localhost:8001 -t public` starten.

Das Kundenfrontend ist ein eigenstaendiges Projekt. Fuer die CMS-Vorschau kann
sein absoluter Pfad ueber `FRONTEND_SOURCE_DIR` und seine URL ueber
`FRONTEND_BASE_URL` konfiguriert werden. Ohne diese Zuordnung bleiben CMS und
API funktionsfaehig; lediglich die Frontend-Vorschau steht nicht zur Verfuegung.

Secrets, `.env`, Medien und Laufzeitdaten duerfen nicht eingecheckt werden.
