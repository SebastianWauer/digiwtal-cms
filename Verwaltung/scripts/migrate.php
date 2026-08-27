<?php
declare(strict_types=1);

/**
 * scripts/migrate.php - Migrationen der Verwaltung auf der Kommandozeile.
 *
 * Die Logik steht in services/MigrationRunner.php, damit die Seite
 * /admin/migrations im Browser exakt denselben Code ausfuehrt. Auf dem
 * IONOS-Webspace der Verwaltung gibt es keine Shell; dort ist der Browser
 * der Weg, hier auf einem Rechner mit PHP-CLI geht auch das:
 *
 *   php scripts/migrate.php            offene Migrationen anzeigen und anwenden
 *   php scripts/migrate.php --dry-run  nur anzeigen
 *   php scripts/migrate.php --baseline die Dateien als angewendet markieren,
 *                                      deren Tabellen schon existieren - ohne
 *                                      sie auszufuehren. Wirklich neue
 *                                      Migrationen bleiben offen.
 *   php scripts/migrate.php --baseline --alle
 *                                      alle offenen markieren. Nur bewusst
 *                                      benutzen: eine neue Migration wuerde
 *                                      damit nie laufen.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only. Im Browser: /admin/migrations\n");
    exit(1);
}

$root = realpath(__DIR__ . '/..');
if ($root === false) {
    fwrite(STDERR, "Projektwurzel nicht auffindbar.\n");
    exit(1);
}

require_once $root . '/app/bootstrap.php';
require_once $root . '/services/MigrationRunner.php';

$dryRun   = in_array('--dry-run', $argv, true);
$baseline = in_array('--baseline', $argv, true);
$alle     = in_array('--alle', $argv, true);

try {
    $pdo = new PDO(
        'mysql:host=' . (getenv('DB_HOST') ?: 'localhost')
            . ';dbname=' . (getenv('DB_NAME') ?: '')
            . ';charset=utf8mb4',
        (string)(getenv('DB_USER') ?: ''),
        (string)(getenv('DB_PASS') ?: ''),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Throwable $e) {
    fwrite(STDERR, "DB-Verbindung fehlgeschlagen: " . $e->getMessage() . "\n");
    fwrite(STDERR, "Hinweis: .env pruefen (DB_HOST, DB_NAME, DB_USER, DB_PASS).\n");
    exit(1);
}

$runner  = MigrationRunner::fuerVerwaltung($pdo);
$applied = $runner->applied();
$pending = $runner->pending();

if ($pending === []) {
    echo 'Keine offenen Migrationen (' . count($applied) . " bereits angewendet).\n";
    exit(0);
}

$analyse = $runner->analyse();

echo count($pending) . " offene Migration(en):\n";
foreach ($analyse as $eintrag) {
    echo '  - ' . $eintrag['datei'] . '   [' . ($eintrag['bestand'] ? 'Tabellen vorhanden' : 'neu') . "]\n";
}

if ($dryRun) {
    echo "\n--dry-run: nichts angewendet.\n";
    exit(0);
}

if ($baseline) {
    if ($alle) {
        $auswahl = $pending;
    } else {
        $auswahl = array_values(array_column(
            array_filter($analyse, static fn(array $e): bool => $e['bestand']),
            'datei'
        ));
    }

    if ($auswahl === []) {
        echo "\nNichts zu markieren: keine der offenen Migrationen betrifft eine vorhandene Tabelle.\n";
        echo "Zum Anwenden: php scripts/migrate.php\n";
        exit(0);
    }

    $markiert  = $runner->baseline($auswahl);
    $uebrig    = array_values(array_diff($pending, $markiert));
    echo "\n" . count($markiert) . " Migration(en) als angewendet markiert (nichts ausgefuehrt).\n";
    if ($uebrig !== []) {
        echo count($uebrig) . " Migration(en) bleiben offen, weil sie wirklich neu sind:\n";
        foreach ($uebrig as $name) {
            echo '  - ' . $name . "\n";
        }
        echo "Jetzt anwenden mit: php scripts/migrate.php\n";
    }
    exit(0);
}

echo "\n";
$ergebnis = $runner->migrate();
foreach ($ergebnis['angewendet'] as $name) {
    echo "Angewendet: {$name}\n";
}

if ($ergebnis['fehler'] !== null) {
    fwrite(STDERR, "\nMigration " . (string)$ergebnis['fehlerBei'] . ' fehlgeschlagen: ' . (string)$ergebnis['fehler'] . "\n");
    fwrite(STDERR, 'Abgebrochen nach ' . count($ergebnis['angewendet']) . " erfolgreichen Migration(en).\n");
    exit(1);
}

echo "\n" . count($ergebnis['angewendet']) . " Migration(en) angewendet.\n";
