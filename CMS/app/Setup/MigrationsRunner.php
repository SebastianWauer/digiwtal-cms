<?php
declare(strict_types=1);

namespace App\Setup;

use PDO;
use PDOStatement;
use Throwable;

/**
 * MigrationsRunner – bewusst simpel & schnell
 * - Liest /migrations/*.sql (sorted)
 * - Führt jede Migration genau 1x aus, tracked in schema_migrations (PK = id)
 *
 * WICHTIG:
 * - SQL kann mehrere Statements enthalten (z.B. ALTER TABLE + CREATE INDEX).
 *   Deshalb splitten wir Statements und führen sie einzeln aus.
 * - Nur wenn ALLE Statements erfolgreich laufen, wird markiert.
 *
 * Fix:
 * - PDO/MySQL kann bei Statements wie PREPARE/EXECUTE "unbuffered result sets" offen lassen.
 *   Daher drainen wir nach jeder Ausführung alle Resultsets (fetchAll/nextRowset/closeCursor),
 *   um Error 2014 zu vermeiden.
 */
final class MigrationsRunner
{
    /**
     * @return array{ok:bool, ran:int, log:string[]}
     */
    public static function run(PDO $pdo, string $migrationsDir): array
    {
        $log = [];
        $ran = 0;

        try {
            // Fail-fast: bei SQL-Fehlern sofort Exception
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            self::ensureSchemaMigrationsTable($pdo);

            $applied = self::loadApplied($pdo);

            if (!is_dir($migrationsDir)) {
                return ['ok' => false, 'ran' => 0, 'log' => ["Missing migrations dir: {$migrationsDir}"]];
            }

            $files = glob(rtrim($migrationsDir, "/\\") . '/*.sql');
            if ($files === false) $files = [];
            sort($files, SORT_NATURAL);

            if (!$files) {
                return ['ok' => true, 'ran' => 0, 'log' => ["No migrations found."]];
            }

            foreach ($files as $file) {
                $version = trim(basename($file));
                if ($version === '') continue;

                if (isset($applied[$version])) continue;

                $sql = file_get_contents($file);
                if ($sql === false) {
                    return ['ok' => false, 'ran' => $ran, 'log' => array_merge($log, ["Cannot read: {$version}"])];
                }

                $sql = self::stripSqlComments(trim($sql));
                if ($sql === '') {
                    $log[] = "SKIP empty SQL: {$version}";
                    // NICHT als applied markieren, damit du leere Files bemerkst
                    continue;
                }

                $log[] = "Applying: {$version}";
                $log[] = "File: {$file}";

                $statements = self::splitSqlStatements($sql);

                // bewusst ohne Transaktion (ALTER TABLE etc.)
                foreach ($statements as $i => $stmtSql) {
                    $stmtSql = trim($stmtSql);
                    if ($stmtSql === '') continue;

                    // Debug light: nur die ersten Zeichen ins Log (kein Overload)
                    $preview = mb_substr(preg_replace('/\s+/', ' ', $stmtSql) ?? $stmtSql, 0, 90);
                    $log[] = "  - SQL[" . ($i + 1) . "]: " . $preview . (mb_strlen($preview) >= 90 ? '…' : '');

                    self::execAndDrain($pdo, $stmtSql);
                }

                // Track: nur wenn alles durchlief
                self::markApplied($pdo, $version);

                $log[] = "Applied:  {$version}";
                $ran++;
            }

            if ($ran === 0) $log[] = "Up to date. No new migrations.";

            return ['ok' => true, 'ran' => $ran, 'log' => $log];
        } catch (Throwable $e) {
            $log[] = "ERROR: " . $e->getMessage();
            return ['ok' => false, 'ran' => $ran, 'log' => $log];
        }
    }

    /**
     * Markiert alle vorhandenen *.sql als angewendet, ohne sie auszuführen.
     * @return array{ok:bool, ran:int, log:string[]}
     */
    public static function baseline(PDO $pdo, string $migrationsDir): array
    {
        $log = [];
        $ran = 0;

        try {
            // auch hier Exceptions
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            self::ensureSchemaMigrationsTable($pdo);

            $applied = self::loadApplied($pdo);

            if (!is_dir($migrationsDir)) {
                return ['ok' => false, 'ran' => 0, 'log' => ["Missing migrations dir: {$migrationsDir}"]];
            }

            $files = glob(rtrim($migrationsDir, "/\\") . '/*.sql');
            if ($files === false) $files = [];
            sort($files, SORT_NATURAL);

            if (!$files) {
                return ['ok' => true, 'ran' => 0, 'log' => ["No migrations found."]];
            }

            foreach ($files as $file) {
                $version = trim(basename($file));
                if ($version === '') continue;

                if (isset($applied[$version])) continue;

                self::markApplied($pdo, $version);

                $log[] = "Baselined: {$version}";
                $ran++;
            }

            if ($ran === 0) $log[] = "Nothing to baseline (already up to date).";

            return ['ok' => true, 'ran' => $ran, 'log' => $log];
        } catch (Throwable $e) {
            $log[] = "ERROR: " . $e->getMessage();
            return ['ok' => false, 'ran' => $ran, 'log' => $log];
        }
    }

    // ----------------------------
    // Internals
    // ----------------------------

    /**
     * Führt ein SQL-Statement aus und drain't alle Resultsets (MySQL/PDO 2014 fix).
     */
    private static function execAndDrain(PDO $pdo, string $sql): void
    {
        // query() liefert bei MySQL auch für viele non-SELECT Statements ein Statement-Handle.
        // Falls false kommt, versuchen wir exec() (sollte bei ERRMODE_EXCEPTION selten passieren).
        $stmt = $pdo->query($sql);
        if ($stmt instanceof PDOStatement) {
            // Alle Resultsets leeren (auch bei PREPARE/EXECUTE oder Prozeduren)
            do {
                // fetchAll() schließt unbuffered result sets
                try {
                    $stmt->fetchAll();
                } catch (Throwable) {
                    // falls fetchAll auf non-select meckert: ignorieren, aber weiter drainen
                }
            } while ($stmt->nextRowset());
            $stmt->closeCursor();
            return;
        }

        // Fallback
        $pdo->exec($sql);
    }

    private static function ensureSchemaMigrationsTable(PDO $pdo): void
    {
        $pdo->exec("
          CREATE TABLE IF NOT EXISTS schema_migrations (
            id VARCHAR(190) NOT NULL,
            applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        if (!self::hasColumn($pdo, 'schema_migrations', 'applied_at')) {
            $pdo->exec("ALTER TABLE schema_migrations ADD COLUMN applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
        }
        if (!self::hasColumn($pdo, 'schema_migrations', 'id')) {
            throw new \RuntimeException("schema_migrations.id missing – cannot track migrations.");
        }
    }

    /**
     * @return array<string,true>
     */
    private static function loadApplied(PDO $pdo): array
    {
        $applied = [];

        $stmt = $pdo->query("SELECT id FROM schema_migrations");
        $rows = $stmt ? $stmt->fetchAll() : [];

        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (is_array($row) && isset($row['id'])) {
                    $v = (string)$row['id'];
                    if ($v !== '') $applied[$v] = true;
                }
            }
        }

        return $applied;
    }

    private static function markApplied(PDO $pdo, string $version): void
    {
        $version = trim($version);
        if ($version === '') return;

        $ins = $pdo->prepare("INSERT IGNORE INTO schema_migrations (id, applied_at) VALUES (:v, NOW())");
        $ins->execute([':v' => $version]);
    }

    private static function hasColumn(PDO $pdo, string $table, string $col): bool
    {
        $cols = self::listColumns($pdo, $table);
        return isset($cols[strtolower($col)]);
    }

    /**
     * @return array<string,true>
     */
    private static function listColumns(PDO $pdo, string $table): array
    {
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
            $rows = $stmt ? $stmt->fetchAll() : [];
            $out = [];
            if (is_array($rows)) {
                foreach ($rows as $r) {
                    if (is_array($r) && isset($r['Field'])) {
                        $out[strtolower((string)$r['Field'])] = true;
                    }
                }
            }
            return $out;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Entfernt -- und /* *\/ Kommentare, ohne Strings zu zerstören (simpel genug für unsere Migrations).
     */
    private static function stripSqlComments(string $sql): string
    {
        // Entferne /* ... */ Blöcke
        $sql = preg_replace('~/\*.*?\*/~s', '', $sql) ?? $sql;

        // Entferne -- ... bis Zeilenende
        $lines = preg_split("/\r\n|\n|\r/", $sql) ?: [];
        $out = [];
        foreach ($lines as $line) {
            $trim = ltrim($line);
            if (str_starts_with($trim, '--')) continue;
            $out[] = $line;
        }
        return trim(implode("\n", $out));
    }

    /**
     * Splittet SQL in Statements an ; (ignoriert ; in einfachen/doppelten Quotes).
     * @return string[]
     */
    private static function splitSqlStatements(string $sql): array
    {
        $stmts = [];
        $buf = '';
        $len = strlen($sql);
        $inSingle = false;
        $inDouble = false;

        for ($i = 0; $i < $len; $i++) {
            $ch = $sql[$i];

            if ($ch === "'" && !$inDouble) {
                // toggle single (wenn nicht escaped)
                $prev = $i > 0 ? $sql[$i - 1] : '';
                if ($prev !== '\\') $inSingle = !$inSingle;
            } elseif ($ch === '"' && !$inSingle) {
                $prev = $i > 0 ? $sql[$i - 1] : '';
                if ($prev !== '\\') $inDouble = !$inDouble;
            }

            if ($ch === ';' && !$inSingle && !$inDouble) {
                $stmts[] = $buf;
                $buf = '';
                continue;
            }

            $buf .= $ch;
        }

        if (trim($buf) !== '') $stmts[] = $buf;

        return $stmts;
    }
}
