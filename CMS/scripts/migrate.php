<?php
declare(strict_types=1);

/**
 * scripts/migrate.php
 * CLI-Migrations-Runner.
 *
 * Duenne Huelle um die Funktionen aus app/db.php - bewusst ohne eigene
 * DB-Verbindung und ohne eigene Definition von schema_migrations. Frueher
 * brachte dieses Skript beides selbst mit: eine Konfigurationslogik, die
 * andere Schluessel las als config/db.php liefert (deshalb war es gar nicht
 * lauffaehig), und eine Statustabelle mit der Spalte `version` statt `id`.
 * Wer beide Runner benutzte, bekam "Unknown column".
 *
 * Aufruf:
 *   php scripts/migrate.php            zeigt offene Migrationen und wendet sie an
 *   php scripts/migrate.php --dry-run  zeigt nur, was anstehen wuerde
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

$root = realpath(__DIR__ . '/..');
if ($root === false || !is_file($root . '/app/bootstrap.php')) {
    fwrite(STDERR, "Projektwurzel nicht auffindbar (app/bootstrap.php fehlt).\n");
    exit(1);
}

require_once $root . '/app/bootstrap.php';

$dryRun = in_array('--dry-run', $argv, true);

try {
    $pdo = db();
} catch (Throwable $e) {
    fwrite(STDERR, "DB-Verbindung fehlgeschlagen: " . $e->getMessage() . "\n");
    fwrite(STDERR, "Hinweis: .env pruefen (DB_HOST, DB_NAME, DB_USER, DB_PASS).\n");
    exit(1);
}

db_ensure_migrations_table($pdo);
$applied = db_applied_migrations($pdo);

$pending = [];
foreach (db_migration_files() as $file) {
    if (!isset($applied[basename($file)])) {
        $pending[] = $file;
    }
}

if ($pending === []) {
    echo "Keine offenen Migrationen (" . count($applied) . " bereits angewendet).\n";
    exit(0);
}

echo count($pending) . " offene Migration(en):\n";
foreach ($pending as $file) {
    echo "  - " . basename($file) . "\n";
}

if ($dryRun) {
    echo "\n--dry-run: nichts angewendet.\n";
    exit(0);
}

echo "\n";
$ran = 0;
foreach ($pending as $file) {
    $id  = basename($file);
    $sql = file_get_contents($file);
    if (!is_string($sql) || trim($sql) === '') {
        fwrite(STDERR, "Migration leer oder nicht lesbar: {$id}\n");
        exit(1);
    }

    echo "Anwenden: {$id}\n";
    try {
        db_apply_migration($pdo, $id, $sql);
    } catch (Throwable $e) {
        fwrite(STDERR, "\n" . $e->getMessage() . "\n");
        fwrite(STDERR, "Abgebrochen nach {$ran} erfolgreichen Migration(en).\n");
        exit(1);
    }
    $ran++;
}

echo "\n{$ran} Migration(en) angewendet.\n";
