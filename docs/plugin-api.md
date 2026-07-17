# Plugin-API

Diese Dokumentation richtet sich an PHP-Entwickler, die das CMS über Plugins erweitern möchten.

---

## Inhaltsverzeichnis

1. [Verzeichnisstruktur und Mindestinhalt](#1-verzeichnisstruktur-und-mindestinhalt)
2. [Verfügbare Hooks](#2-verfügbare-hooks)
3. [API-Referenz](#3-api-referenz)
4. [Priority-System](#4-priority-system)
5. [Eigene Hooks definieren](#5-eigene-hooks-definieren)
6. [Vollständiges Beispiel-Plugin](#6-vollständiges-beispiel-plugin)
7. [Sicherheitshinweise](#7-sicherheitshinweise)

---

## 1. Verzeichnisstruktur und Mindestinhalt

Jedes Plugin liegt als eigenes Unterverzeichnis unter `plugins/` und muss genau eine Einstiegsdatei enthalten:

```
plugins/
└── mein-plugin/
    └── plugin.php        ← Pflicht, wird automatisch geladen
```

Der `PluginLoader` scannt `plugins/` beim Bootstrap **alphabetisch** und führt `require_once plugin.php` für jedes gefundene Unterverzeichnis aus. Weitere Dateien (Klassen, Templates, Assets) können beliebig im Plugin-Verzeichnis abgelegt und von `plugin.php` eingebunden werden:

```
plugins/
└── mein-plugin/
    ├── plugin.php        ← Einstiegspunkt
    ├── src/
    │   └── MyService.php
    └── templates/
        └── widget.php
```

### Laden weiterer Dateien

```php
// plugins/mein-plugin/plugin.php
declare(strict_types=1);

require_once __DIR__ . '/src/MyService.php';

\App\Core\Hooks::add_action('cms_bootstrap_done', function (): void {
    // Plugin ist bereit
});
```

> **Ladereihenfolge:** Plugins werden alphabetisch nach Verzeichnisname geladen. Hängt Plugin B von Plugin A ab, muss A lexikografisch vor B kommen (z. B. `01-plugin-a/`, `02-plugin-b/`).

---

## 2. Verfügbare Hooks

### `cms_bootstrap_done`

Wird am Ende des Bootstrap-Prozesses ausgelöst, nachdem alle CMS-Komponenten initialisiert sind.

| Parameter | — |
|-----------|---|
| Signatur  | `function (): void` |
| Zeitpunkt | Nach Session-Start, Autoloader, DB, CSRF und allen Includes |

**Anwendungsfälle:** Initialisierung von Plugin-Ressourcen, Registrierung weiterer Hooks, Logging des Starts.

```php
\App\Core\Hooks::add_action('cms_bootstrap_done', function (): void {
    // CMS ist vollständig geladen – sicherer Punkt für Initialisierungen
    error_log('[mein-plugin] geladen');
});
```

---

### `cms_after_page_save`

Wird nach dem erfolgreichen Speichern einer Seite (Insert **und** Update) ausgelöst.

| Parameter | Typ      | Beschreibung              |
|-----------|----------|---------------------------|
| `$pageId` | `int`    | Datenbank-ID der Seite    |
| `$slug`   | `string` | URL-Slug der Seite        |
| Signatur  | `function (int $pageId, string $slug): void` | |

**Anwendungsfälle:** Cache invalidieren, Suchindex aktualisieren, externe Webhooks aufrufen, Audit-Log schreiben.

```php
\App\Core\Hooks::add_action('cms_after_page_save', function (int $pageId, string $slug): void {
    // Wird bei Insert und Update aufgerufen
    error_log("[mein-plugin] Seite {$pageId} ({$slug}) wurde gespeichert");
});
```

---

## 3. API-Referenz

Alle Funktionen befinden sich in der Klasse `\App\Core\Hooks` als statische Methoden.

### `add_action`

Registriert einen Callback für einen Action-Hook. Actions haben keinen Rückgabewert — sie führen Seiteneffekte aus (Logging, Dateischreiben, HTTP-Requests usw.).

```php
\App\Core\Hooks::add_action(
    string $hook,
    callable $cb,
    int $priority = 10
): void
```

**Parameter:**

| Name        | Typ        | Beschreibung                                      |
|-------------|------------|---------------------------------------------------|
| `$hook`     | `string`   | Name des Hooks                                    |
| `$cb`       | `callable` | Callback (Closure, Funktionsname, `[$obj, 'methode']`) |
| `$priority` | `int`      | Ausführungsreihenfolge; niedrigere Werte = früher (Standard: `10`) |

```php
// Closure
\App\Core\Hooks::add_action('cms_after_page_save', function (int $id, string $slug): void {
    // ...
});

// Statische Methode
\App\Core\Hooks::add_action('cms_after_page_save', [MyPlugin::class, 'onPageSave']);

// Instanz-Methode
$handler = new MyHandler();
\App\Core\Hooks::add_action('cms_after_page_save', [$handler, 'handle']);
```

---

### `do_action`

Löst einen Action-Hook aus und ruft alle registrierten Callbacks in Prioritätsreihenfolge auf. Wird vom CMS intern aufgerufen; Plugins können damit eigene Hooks feuern.

```php
\App\Core\Hooks::do_action(
    string $hook,
    mixed ...$args
): void
```

```php
// Eigenen Hook feuern (aus einem Plugin heraus)
\App\Core\Hooks::do_action('mein-plugin/nach_export', $exportPath);
```

---

### `add_filter`

Registriert einen Callback für einen Filter-Hook. Filter **empfangen** einen Wert, **verändern** ihn und **geben** ihn zurück. Mehrere Filter werden nacheinander auf denselben Wert angewendet.

```php
\App\Core\Hooks::add_filter(
    string $hook,
    callable $cb,
    int $priority = 10
): void
```

Der Callback muss den (ggf. modifizierten) Wert zurückgeben:

```php
\App\Core\Hooks::add_filter('cms_page_title', function (string $title, int $pageId): string {
    return strtoupper($title);   // Wert verändern und zurückgeben
});
```

> **Wichtig:** Gibt ein Filter-Callback `null` oder gar nichts zurück, wird der Wert auf `null` gesetzt und an den nächsten Filter weitergegeben. Immer den (veränderten) Wert explizit zurückgeben.

---

### `apply_filters`

Wendet alle registrierten Filter-Callbacks auf einen Wert an und gibt das Ergebnis zurück. Wird vom CMS oder von Plugins an Stellen aufgerufen, an denen Dritte den Wert beeinflussen dürfen.

```php
\App\Core\Hooks::apply_filters(
    string $hook,
    mixed $value,
    mixed ...$args
): mixed
```

```php
// Im CMS oder Plugin: Wert filterbar machen
$title = \App\Core\Hooks::apply_filters('cms_page_title', $rawTitle, $pageId);

// Kein Filter registriert → $rawTitle wird unverändert zurückgegeben
```

---

## 4. Priority-System

Callbacks werden nach ihrem `$priority`-Wert sortiert. **Niedrigere Werte werden zuerst ausgeführt.**

| Priority | Verwendung                                        |
|----------|---------------------------------------------------|
| `1`      | Sehr früh — z. B. Sicherheitsprüfungen            |
| `5`      | Früh — Vorverarbeitung                            |
| `10`     | **Standard** — normaler Plugin-Code               |
| `20`     | Spät — Nachverarbeitung, abhängig von anderen     |
| `100`    | Sehr spät — Logging, Audit-Trail                  |

Callbacks mit **gleicher Priority** werden in der Reihenfolge ihrer Registrierung ausgeführt (FIFO).

```php
// Wird vor Standard-Callbacks ausgeführt
\App\Core\Hooks::add_action('cms_after_page_save', function (int $id, string $slug): void {
    // Cache leeren – soll vor dem Logging passieren
}, 5);

// Standard-Priority (10)
\App\Core\Hooks::add_action('cms_after_page_save', function (int $id, string $slug): void {
    // Suchindex aktualisieren
});

// Wird zuletzt ausgeführt
\App\Core\Hooks::add_action('cms_after_page_save', function (int $id, string $slug): void {
    // Audit-Log schreiben
}, 100);
```

---

## 5. Eigene Hooks definieren

Plugins können eigene Hooks definieren — sowohl Actions als auch Filter. Damit lassen sich Erweiterungspunkte für andere Plugins schaffen.

### Eigene Action feuern

```php
// In plugin.php oder einer Plugin-Klasse:
\App\Core\Hooks::do_action('mein-plugin/vor_import', $dateiPfad);

// Datei importieren ...

\App\Core\Hooks::do_action('mein-plugin/nach_import', $dateiPfad, $anzahlDatensaetze);
```

Andere Plugins können sich darauf registrieren:

```php
\App\Core\Hooks::add_action('mein-plugin/nach_import', function (string $pfad, int $zeilen): void {
    error_log("Import abgeschlossen: {$zeilen} Zeilen aus {$pfad}");
});
```

### Eigenen Filter anbieten

```php
// Wert filterbar machen:
$exportFormat = \App\Core\Hooks::apply_filters('mein-plugin/export_format', 'csv');

// Anderes Plugin überschreibt das Format:
\App\Core\Hooks::add_filter('mein-plugin/export_format', function (string $format): string {
    return 'json';
});
```

### Namenskonvention

Eigene Hook-Namen sollten immer mit dem Plugin-Slug als Präfix versehen werden, um Kollisionen mit CMS-Hooks oder anderen Plugins zu vermeiden:

```
{plugin-slug}/{hook-name}

Beispiele:
  mein-plugin/vor_export
  mein-plugin/nach_import
  mein-plugin/seiten_titel
```

---

## 6. Vollständiges Beispiel-Plugin

Das folgende Plugin demonstriert Actions, Filter und eigene Hooks in einem realistischen Szenario: Es schreibt ein Audit-Log nach jeder Seitenänderung und erlaubt anderen Plugins, den Log-Eintrag zu verändern.

**Verzeichnisstruktur:**

```
plugins/
└── audit-log/
    ├── plugin.php
    └── src/
        └── AuditLogger.php
```

**`plugins/audit-log/src/AuditLogger.php`:**

```php
<?php
declare(strict_types=1);

final class AuditLogger
{
    private string $logFile;

    public function __construct(string $storageDir)
    {
        $this->logFile = rtrim($storageDir, '/') . '/logs/audit.log';
        @mkdir(dirname($this->logFile), 0755, true);
    }

    public function onPageSave(int $pageId, string $slug): void
    {
        // Roheintrag zusammenbauen
        $entry = sprintf(
            '[%s] page_save  id=%d  slug=%s',
            date('Y-m-d H:i:s'),
            $pageId,
            $slug
        );

        // Anderen Plugins erlauben, den Eintrag zu verändern
        $entry = \App\Core\Hooks::apply_filters('audit-log/eintrag', $entry, $pageId, $slug);

        file_put_contents($this->logFile, $entry . PHP_EOL, FILE_APPEND | LOCK_EX);

        // Eigene Action feuern, damit Plugins reagieren können
        \App\Core\Hooks::do_action('audit-log/nach_schreiben', $this->logFile, $entry);
    }
}
```

**`plugins/audit-log/plugin.php`:**

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/src/AuditLogger.php';

\App\Core\Hooks::add_action('cms_bootstrap_done', function (): void {

    $logger = new AuditLogger(dirname(__DIR__, 2) . '/storage');

    // Auf CMS-Hook reagieren
    \App\Core\Hooks::add_action(
        'cms_after_page_save',
        [$logger, 'onPageSave'],
        100   // Spät ausführen – andere Plugins zuerst
    );

    // Eigenen Filter anbieten: IP-Adresse anhängen
    \App\Core\Hooks::add_filter(
        'audit-log/eintrag',
        function (string $eintrag, int $pageId, string $slug): string {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unbekannt';
            return $eintrag . '  ip=' . $ip;
        }
    );
});
```

**Ergebnis in `storage/logs/audit.log`:**

```
[2025-08-14 10:23:41] page_save  id=42  slug=/ueber-uns  ip=203.0.113.5
```

---

## 7. Sicherheitshinweise

### Datenbankzugriff nur über `db()`

Direkter Zugriff auf `$_GLOBALS` oder das Anlegen eigener PDO-Verbindungen ist nicht erlaubt. Verwende ausschließlich die bereitgestellte Hilfsfunktion:

```php
// Korrekt
$pdo  = db();
$stmt = $pdo->prepare('SELECT id, title FROM pages WHERE slug = ?');
$stmt->execute([$slug]);

// Falsch – eigene Verbindung
$pdo = new \PDO('mysql:host=localhost;dbname=cms', 'user', 'pass');
```

### Keine globalen Variablen

Globale Variablen verschmutzen den gemeinsamen Namespace und können andere Plugins oder das CMS selbst korrumpieren:

```php
// Falsch
global $myPluginConfig;
$myPluginConfig = ['key' => 'value'];

// Korrekt – Zustand in einer Klasse kapseln
final class MyPluginConfig
{
    private static array $data = [];

    public static function set(string $key, mixed $value): void
    {
        self::$data[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::$data[$key] ?? $default;
    }
}
```

### Eingaben immer validieren

Hooks können mit beliebigen Daten aufgerufen werden. Parameter immer auf erwarteten Typ und Wertebereich prüfen, bevor damit gearbeitet wird.

```php
\App\Core\Hooks::add_action('cms_after_page_save', function (int $pageId, string $slug): void {
    if ($pageId <= 0 || $slug === '') {
        return;   // Ungültige Daten – nichts tun
    }
    // ...
});
```

### Filter müssen immer einen Wert zurückgeben

Ein Filter-Callback, der keinen Wert zurückgibt, setzt den gefilterten Wert auf `null` und kann so nachfolgende Plugins oder das CMS beschädigen:

```php
// Falsch – kein Return
\App\Core\Hooks::add_filter('cms_page_title', function (string $title): void {
    strtoupper($title);   // Vergessen: return
});

// Korrekt
\App\Core\Hooks::add_filter('cms_page_title', function (string $title): string {
    return strtoupper($title);
});
```

### Dateisystem-Zugriff

Schreibzugriffe sind ausschließlich im Verzeichnis `storage/` erlaubt. Kein Schreiben in `public/`, `app/` oder andere CMS-Verzeichnisse:

```php
// Korrekt
$logDir = dirname(__DIR__, 2) . '/storage/logs';

// Verboten
$logDir = dirname(__DIR__, 2) . '/public/logs';
$logDir = dirname(__DIR__, 2) . '/app/logs';
```

### Externe HTTP-Requests

Bei Webhooks oder API-Calls immer Timeouts setzen, um blockierende Requests zu vermeiden:

```php
$ctx = stream_context_create([
    'http' => [
        'timeout' => 3,   // Sekunden
        'method'  => 'POST',
        'header'  => 'Content-Type: application/json',
        'content' => json_encode(['id' => $pageId, 'slug' => $slug]),
    ],
]);

@file_get_contents('https://example.com/webhook', false, $ctx);
```
