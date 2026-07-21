# Plugin Hook Katalog

Diese Datei dokumentiert alle derzeit **im CMS-Core tatsächlich ausgelösten** Hooks.

Quelle der Ermittlung:
- `rg "Hooks::do_action|Hooks::apply_filters"` in `CMS/app` und `CMS/public`
- Stand dieses Repositories: Es gibt aktuell 2 Core-Action-Hooks und 0 Core-Filter-Hooks.

## Core-Hooks (vollständig)

### `cms_bootstrap_done`
- Typ: `action`
- Zeitpunkt: nach Bootstrap und nach Plugin-Load
- Core-Quelle: `CMS/app/bootstrap.php`
- Aufruf: `\App\Core\Hooks::do_action('cms_bootstrap_done')`
- Parameter: keine
- Rückgabewert: keiner
- Zweck: Plugin-Initialisierung nach vollständigem Systemstart

### `cms_after_page_save`
- Typ: `action`
- Zeitpunkt: nach erfolgreichem Speichern einer Seite
- Core-Quelle: `CMS/app/Services/PageService.php`
- Aufruf:
  - bei neuer Seite: `\App\Core\Hooks::do_action('cms_after_page_save', $newId, $slug)`
  - bei Update: `\App\Core\Hooks::do_action('cms_after_page_save', $id, $slug)`
- Parameter:
  - `$pageId` (`int`)
  - `$slug` (`string`)
- Rückgabewert: keiner
- Zweck: Folgeaktionen nach Save (z.B. Cache-Invalidierung, Audit, Integrationen)

## Core-Filter (aktuell)

Aktuell werden im CMS-Core keine `Hooks::apply_filters(...)` aufgerufen.
Die Filter-API ist vorhanden (`CMS/app/Core/Hooks.php`), aber derzeit nur für Plugins/Custom-Code nutzbar.

## Hook-API (Registrierung)

```php
\App\Core\Hooks::add_action('cms_after_page_save', function (int $pageId, string $slug): void {
    // ...
});

\App\Core\Hooks::add_filter('mein-plugin/custom-filter', function (mixed $value): mixed {
    return $value;
});
```

## Vollständiges Beispiel-Plugin

Bestehendes Beispiel im Repository:
- `CMS/plugins/example-plugin/plugin.php`

```php
<?php
declare(strict_types=1);

\App\Core\Hooks::add_action('cms_after_page_save', function (int $id, string $slug): void {
    $dir = dirname(__DIR__, 2) . '/storage/logs';
    @mkdir($dir, 0755, true);
    @file_put_contents(
        $dir . '/plugin.log',
        date('Y-m-d H:i:s') . " [example-plugin] cms_after_page_save id={$id} slug={$slug}\n",
        FILE_APPEND
    );
});
```

## Plugin-Struktur und Laden

- Ablage: `CMS/plugins/<plugin-name>/`
- Einstiegspunkt: `CMS/plugins/<plugin-name>/plugin.php`
- Laden: `PluginLoader::load()` lädt pro Unterordner die Datei `plugin.php` per `require_once`
- Pflicht-Interface: keines (ausführbares `plugin.php` genügt)

## Wichtige Abgrenzung

`CMS/docs/plugin-api.md` enthält zusätzlich Beispiel-Hook-Namen (z.B. `mein-plugin/*`), die als Muster dienen.
Diese sind **nicht automatisch Core-Hooks**, solange sie nicht im produktiven Code per `do_action`/`apply_filters` ausgelöst werden.
