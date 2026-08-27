<?php
declare(strict_types=1);

/**
 * scripts/migrate.php – Migrations-Runner der Verwaltung.
 *
 * Die Verwaltung hatte bisher keinen: Ihre SQL-Dateien wurden von Hand
 * eingespielt. Deshalb sind auch zwei Nummern doppelt vergeben (008 und 009) -
 * niemand hat es gemerkt. Der Status wird ueber den Dateinamen gefuehrt, nicht
 * ueber die Nummer, damit die vorhandenen Doppelungen keinen Schaden anrichten.
 *
 * Aufruf:
 *   php scripts/migrate.php            offene Migrationen anzeigen und anwenden
 *   php scripts/migrate.php --dry-run  nur anzeigen
 *   php scripts/migrate.php --baseline alles als angewendet markieren, ohne es
 *                                      auszufuehren (fuer die bestehende
 *                                      Installation, deren Tabellen schon da sind)
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

$root = realpath(__DIR__ . '/..');
if ($root === false) {
    fwrite(STDERR, "Projektwurzel nicht auffindbar.\n");
    exit(1);
}

require_once $root . '/app/bootstrap.php';

$dryRun   = in_array('--dry-run', $argv, true);
$baseline = in_array('--baseline', $argv, true);

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

$pdo->exec("
    CREATE TABLE IF NOT EXISTS schema_migrations (
        id VARCHAR(190) NOT NULL,
        applied_at DATETIME NOT NULL,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$applied = [];
foreach ($pdo->query('SELECT id FROM schema_migrations')->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $applied[(string)($row['id'] ?? '')] = true;
}

$files = glob($root . '/migrations/*.sql') ?: [];
sort($files, SORT_NATURAL);

$pending = array_values(array_filter($files, static fn(string $f): bool => !isset($applied[basename($f)])));

if ($pending === []) {
    echo 'Keine offenen Migrationen (' . count($applied) . " bereits angewendet).\n";
    exit(0);
}

echo count($pending) . " offene Migration(en):\n";
foreach ($pending as $f) {
    echo '  - ' . basename($f) . "\n";
}

if ($dryRun) {
    echo "\n--dry-run: nichts angewendet.\n";
    exit(0);
}

$mark = $pdo->prepare('INSERT INTO schema_migrations (id, applied_at) VALUES (:id, NOW())');

if ($baseline) {
    foreach ($pending as $f) {
        $mark->execute([':id' => basename($f)]);
    }
    echo "\n" . count($pending) . " Migration(en) als angewendet markiert (nichts ausgefuehrt).\n";
    exit(0);
}

echo "\n";
$ran = 0;
foreach ($pending as $file) {
    $id  = basename($file);
    $sql = trim((string)file_get_contents($file));
    if ($sql === '') {
        fwrite(STDERR, "Migration leer: {$id}\n");
        exit(1);
    }

    echo "Anwenden: {$id}\n";
    try {
        foreach (preg_split('/;\s*(\r\n|\r|\n)/', $sql) ?: [$sql] as $part) {
            $part = trim((string)$part);
            if ($part === '') {
                continue;
            }
            // query() statt exec(): Anweisungen mit Ergebnismenge wuerden sonst
            // ein offenes Result-Set hinterlassen (SQLSTATE 2014).
            $stmt = $pdo->query($part);
            if ($stmt instanceof PDOStatement) {
                try {
                    do {
                        $stmt->fetchAll();
                    } while ($stmt->nextRowset());
                } catch (Throwable) {
                    // manche Anweisungen werfen statt false zu liefern
                }
                $stmt->closeCursor();
            }
        }
        $mark->execute([':id' => $id]);
        $ran++;
    } catch (Throwable $e) {
        fwrite(STDERR, "\nMigration {$id} fehlgeschlagen: " . $e->getMessage() . "\n");
        fwrite(STDERR, "Abgebrochen nach {$ran} erfolgreichen Migration(en).\n");
        exit(1);
    }
}

echo "\n{$ran} Migration(en) angewendet.\n";
