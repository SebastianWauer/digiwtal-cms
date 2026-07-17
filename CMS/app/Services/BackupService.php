<?php
declare(strict_types=1);

namespace App\Services;

final class BackupService
{
    /** Tabellen, die ins Backup aufgenommen werden (Reihenfolge = Abhängigkeiten). */
    private const TABLES = [
        'roles',
        'permissions',
        'role_permissions',
        'users',
        'pages',
        'seo_meta',
        'media_folders',
        'media_items',
        'site_settings',
    ];

    /**
     * Generiert ein SQL-Dump mit INSERT-Statements für alle relevanten Tabellen.
     */
    public function exportDbSql(\PDO $pdo): string
    {
        $lines = [];
        $lines[] = '-- CMS Database Backup';
        $lines[] = '-- Generated: ' . date('Y-m-d H:i:s');
        $lines[] = '-- --------------------------------------------------------';
        $lines[] = '';
        $lines[] = 'SET NAMES utf8mb4;';
        $lines[] = 'SET FOREIGN_KEY_CHECKS = 0;';
        $lines[] = '';

        foreach (self::TABLES as $table) {
            $lines[] = '-- --------------------------------------------------------';
            $lines[] = '-- Table: ' . $table;
            $lines[] = '-- --------------------------------------------------------';
            $lines[] = '';

            try {
                $stmt = $pdo->query('SELECT * FROM `' . $table . '`');
                if ($stmt === false) {
                    $lines[] = '-- (skipped: query failed)';
                    $lines[] = '';
                    continue;
                }

                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                if (empty($rows)) {
                    $lines[] = '-- (no rows)';
                    $lines[] = '';
                    continue;
                }

                $cols = '`' . implode('`, `', array_keys($rows[0])) . '`';

                foreach ($rows as $row) {
                    $vals = array_map(function (mixed $v) use ($pdo): string {
                        if ($v === null) return 'NULL';
                        return $pdo->quote((string)$v);
                    }, array_values($row));

                    $lines[] = 'INSERT INTO `' . $table . '` (' . $cols . ') VALUES ('
                             . implode(', ', $vals) . ');';
                }
            } catch (\Throwable) {
                $lines[] = '-- (skipped: table not found or error)';
            }

            $lines[] = '';
        }

        $lines[] = 'SET FOREIGN_KEY_CHECKS = 1;';
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * Schreibt den Inhalt in storage/backups/{prefix}_YYYYMMDD_HHMMSS.{ext}.
     * Gibt den vollständigen Dateipfad zurück.
     */
    public function writeBackupFile(string $content, string $prefix, string $ext = 'sql'): string
    {
        $dir = $this->backupsDir();

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $filename = $prefix . '_' . date('Ymd_His') . '.' . $ext;
        $path     = $dir . '/' . $filename;

        file_put_contents($path, $content, LOCK_EX);

        return $path;
    }

    /**
     * Listet alle *.sql- und *.zip-Dateien aus storage/backups/, absteigend nach mtime.
     *
     * @return array<int, array{name:string, path:string, size_kb:float, created_at:string}>
     */
    public function listBackups(): array
    {
        $dir = $this->backupsDir();

        if (!is_dir($dir)) {
            return [];
        }

        $files = array_merge(
            glob($dir . '/*.sql') ?: [],
            glob($dir . '/*.zip') ?: []
        );

        $result = [];

        foreach ($files as $path) {
            if (!is_file($path)) continue;

            $mtime = (int)filemtime($path);
            $size  = (int)filesize($path);

            $result[] = [
                'name'       => basename($path),
                'path'       => $path,
                'size_kb'    => round($size / 1024, 1),
                'created_at' => date('Y-m-d H:i:s', $mtime),
                '_mtime'     => $mtime,
            ];
        }

        usort($result, fn(array $a, array $b): int => $b['_mtime'] <=> $a['_mtime']);

        // _mtime ist intern, nicht nach außen geben
        return array_map(function (array $e): array {
            unset($e['_mtime']);
            return $e;
        }, $result);
    }

    /**
     * Löscht Backup-Dateien, die älter als $keepDays Tage sind.
     * Gibt die Anzahl gelöschter Dateien zurück.
     */
    public function cleanOldBackups(int $keepDays = 30): int
    {
        $dir = $this->backupsDir();

        if (!is_dir($dir)) {
            return 0;
        }

        $cutoff = time() - ($keepDays * 86400);
        $files  = array_merge(
            glob($dir . '/*.sql') ?: [],
            glob($dir . '/*.zip') ?: []
        );

        $deleted = 0;

        foreach ($files as $path) {
            if (!is_file($path)) continue;
            if ((int)filemtime($path) < $cutoff) {
                @unlink($path);
                $deleted++;
            }
        }

        return $deleted;
    }

    private function backupsDir(): string
    {
        $root = function_exists('admin_project_root') ? admin_project_root() : dirname(__DIR__, 2);
        return rtrim((string)$root, '/') . '/storage/backups';
    }
}
