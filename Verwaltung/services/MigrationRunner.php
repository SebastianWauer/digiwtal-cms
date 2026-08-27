<?php
declare(strict_types=1);

/**
 * Migrations-Runner der Verwaltung.
 *
 * Der Status wird ueber den Dateinamen gefuehrt, nicht ueber die Nummer: 008
 * und 009 sind je zweimal vergeben, weil die SQL-Dateien frueher von Hand
 * eingespielt wurden. Ueber den Dateinamen richtet die Doppelung keinen
 * Schaden an.
 *
 * Die Klasse wird von zwei Seiten benutzt: von scripts/migrate.php auf der
 * Kommandozeile und von /admin/migrations im Browser. Der Webspace der
 * Verwaltung hat keine Shell (sapi=cgi-fcgi, kein ssh2) - dort ist der
 * Browser der einzige Weg, und beide sollen denselben Code ausfuehren.
 */
final class MigrationRunner
{
    public function __construct(
        private PDO $pdo,
        private string $migrationsDir
    ) {}

    public static function fuerVerwaltung(PDO $pdo): self
    {
        return new self($pdo, dirname(__DIR__) . '/migrations');
    }

    /** Legt die Statustabelle an, falls sie noch fehlt. */
    public function ensureTable(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                id VARCHAR(190) NOT NULL,
                applied_at DATETIME NOT NULL,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    /**
     * Bereits angewendete Migrationen.
     *
     * @return array<string,string> Dateiname => Zeitpunkt
     */
    public function applied(): array
    {
        $this->ensureTable();

        $out  = [];
        $rows = $this->pdo->query('SELECT id, applied_at FROM schema_migrations ORDER BY id')
            ->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $row) {
            $out[(string)($row['id'] ?? '')] = (string)($row['applied_at'] ?? '');
        }

        return $out;
    }

    /**
     * Alle vorhandenen Migrationsdateien, natuerlich sortiert.
     *
     * @return list<string> vollstaendige Pfade
     */
    public function files(): array
    {
        $files = glob($this->migrationsDir . '/*.sql') ?: [];
        sort($files, SORT_NATURAL);

        return array_values($files);
    }

    /**
     * Noch nicht angewendete Migrationen.
     *
     * @return list<string> Dateinamen
     */
    public function pending(): array
    {
        $applied = $this->applied();

        $out = [];
        foreach ($this->files() as $file) {
            $name = basename($file);
            if (!isset($applied[$name])) {
                $out[] = $name;
            }
        }

        return $out;
    }

    /**
     * Beurteilt jede offene Migration: Welche Tabellen fasst sie an, und gibt
     * es die schon?
     *
     * Das ist der Unterschied zwischen "war frueher mal von Hand eingespielt"
     * und "ist wirklich neu". Genau daran haengt, was beim Markieren des
     * Bestands angehakt sein darf - eine wirklich neue Migration mitzumarkieren
     * hiesse, sie nie auszufuehren.
     *
     * @return list<array{datei:string, tabellen:list<string>, vorhanden:bool, bestand:bool, vorschau:string}>
     */
    public function analyse(): array
    {
        $existierende = $this->existingTables();

        $out = [];
        foreach ($this->pending() as $name) {
            $tabellen = $this->referencedTables((string)@file_get_contents($this->migrationsDir . '/' . $name));

            $alleDa = $tabellen !== [];
            foreach ($tabellen as $tabelle) {
                if (!isset($existierende[strtolower($tabelle)])) {
                    $alleDa = false;
                    break;
                }
            }

            $out[] = [
                'datei'     => $name,
                'tabellen'  => $tabellen,
                'vorhanden' => $alleDa,
                // Nur was schon steht, gehoert in den Bestand.
                'bestand'   => $alleDa,
                'vorschau'  => $this->preview($name),
            ];
        }

        return $out;
    }

    /**
     * Markiert Migrationen als angewendet, ohne sie auszufuehren. Fuer eine
     * gewachsene Installation, deren Tabellen laengst existieren: ohne diesen
     * Schritt wuerde der erste echte Lauf ueber 001 stolpern.
     *
     * @param  list<string>|null $namen null = alle offenen (Vorsicht: dann
     *                                  werden auch wirklich neue Migrationen
     *                                  markiert und nie ausgefuehrt)
     * @return list<string> tatsaechlich markierte Dateinamen
     */
    public function baseline(?array $namen = null): array
    {
        $this->ensureTable();

        $offen = $this->pending();
        if ($namen === null) {
            $auswahl = $offen;
        } else {
            $auswahl = array_values(array_intersect($offen, array_map('basename', $namen)));
        }

        $mark = $this->pdo->prepare('INSERT INTO schema_migrations (id, applied_at) VALUES (:id, NOW())');
        foreach ($auswahl as $name) {
            $mark->execute([':id' => $name]);
        }

        return $auswahl;
    }

    /**
     * Fuehrt alle offenen Migrationen aus und bricht beim ersten Fehler ab.
     * Was bis dahin lief, bleibt angewendet und steht in "angewendet".
     *
     * @return array{angewendet: list<string>, fehler: ?string, fehlerBei: ?string}
     */
    public function migrate(): array
    {
        $this->ensureTable();

        $mark       = $this->pdo->prepare('INSERT INTO schema_migrations (id, applied_at) VALUES (:id, NOW())');
        $angewendet = [];

        foreach ($this->pending() as $name) {
            $sql = trim((string)@file_get_contents($this->migrationsDir . '/' . $name));
            if ($sql === '') {
                return [
                    'angewendet' => $angewendet,
                    'fehler'     => 'Datei ist leer oder nicht lesbar.',
                    'fehlerBei'  => $name,
                ];
            }

            try {
                $this->runStatements($sql);
                $mark->execute([':id' => $name]);
                $angewendet[] = $name;
            } catch (Throwable $e) {
                return [
                    'angewendet' => $angewendet,
                    'fehler'     => $e->getMessage(),
                    'fehlerBei'  => $name,
                ];
            }
        }

        return ['angewendet' => $angewendet, 'fehler' => null, 'fehlerBei' => null];
    }

    /** Fuehrt eine Datei aus, Anweisung fuer Anweisung. */
    private function runStatements(string $sql): void
    {
        foreach (preg_split('/;\s*(\r\n|\r|\n)/', $sql) ?: [$sql] as $part) {
            $part = trim((string)$part);
            if ($part === '' || $part === ';') {
                continue;
            }

            // query() statt exec(): Anweisungen mit Ergebnismenge wuerden sonst
            // ein offenes Result-Set hinterlassen, und die naechste Anweisung
            // scheitert an SQLSTATE 2014.
            $stmt = $this->pdo->query($part);
            if ($stmt instanceof PDOStatement) {
                try {
                    do {
                        $stmt->fetchAll();
                    } while ($stmt->nextRowset());
                } catch (Throwable) {
                    // Manche Anweisungen werfen, statt false zu liefern.
                }
                $stmt->closeCursor();
            }
        }
    }

    /** Anfang einer Migration ohne Kommentarzeilen, fuer die Anzeige im Browser. */
    public function preview(string $name, int $maxZeichen = 320): string
    {
        $sql = $this->stripComments((string)@file_get_contents($this->migrationsDir . '/' . basename($name)));
        $sql = trim((string)preg_replace('/\n{2,}/', "\n", $sql));
        if ($sql === '') {
            return '';
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return mb_strlen($sql) > $maxZeichen ? mb_substr($sql, 0, $maxZeichen) . ' ...' : $sql;
        }

        return strlen($sql) > $maxZeichen ? substr($sql, 0, $maxZeichen) . ' ...' : $sql;
    }

    /** @return array<string,true> vorhandene Tabellen der aktuellen Datenbank, klein geschrieben */
    private function existingTables(): array
    {
        $out  = [];
        $rows = $this->pdo->query(
            'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()'
        )->fetchAll(PDO::FETCH_COLUMN) ?: [];
        foreach ($rows as $name) {
            $out[strtolower((string)$name)] = true;
        }

        return $out;
    }

    /**
     * Tabellen, die eine Migration anfasst.
     *
     * Erkannt wird nur das erste Schluesselwort je Anweisung. Sonst faellt die
     * Erkennung auf "ON DUPLICATE KEY UPDATE" oder "ON UPDATE CURRENT_TIMESTAMP"
     * herein und haelt CURRENT_TIMESTAMP fuer eine Tabelle.
     *
     * @return list<string>
     */
    private function referencedTables(string $sql): array
    {
        $muster = '/^(?:CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?'
            . '|ALTER\s+TABLE'
            . '|INSERT\s+(?:IGNORE\s+)?INTO'
            . '|REPLACE\s+INTO'
            . '|UPDATE'
            . '|DELETE\s+FROM'
            . '|TRUNCATE(?:\s+TABLE)?'
            . '|DROP\s+TABLE(?:\s+IF\s+EXISTS)?'
            . '|CREATE\s+(?:UNIQUE\s+)?INDEX\s+`?[A-Za-z0-9_]+`?\s+ON'
            . ')\s+`?([A-Za-z0-9_]+)`?/i';

        $out = [];
        foreach ($this->splitStatements($this->stripComments($sql)) as $anweisung) {
            if (preg_match($muster, $anweisung, $treffer) !== 1) {
                continue;
            }
            $name = (string)$treffer[1];
            if ($name !== '' && !in_array($name, $out, true)) {
                $out[] = $name;
            }
        }

        return $out;
    }

    /**
     * Zerlegt eine Datei in einzelne Anweisungen.
     *
     * @return list<string>
     */
    private function splitStatements(string $sql): array
    {
        $out = [];
        foreach (preg_split('/;\s*(\r\n|\r|\n)/', $sql) ?: [$sql] as $part) {
            $part = trim((string)$part);
            $part = trim(rtrim($part, ';'));
            if ($part !== '') {
                $out[] = $part;
            }
        }

        return $out;
    }

    /** Entfernt Zeilen- und Blockkommentare, damit die Erkennung nicht darauf hereinfaellt. */
    private function stripComments(string $sql): string
    {
        $sql = (string)preg_replace('#/\*.*?\*/#s', '', $sql);
        $sql = (string)preg_replace('/^\s*--.*$/m', '', $sql);
        $sql = (string)preg_replace('/^\s*#.*$/m', '', $sql);

        return trim($sql);
    }
}
