<?php
declare(strict_types=1);

namespace App\Controller;

use App\Services\BackupService;

final class BackupController
{
    public function show(): void
    {
        $user  = admin_require_system_user();
        $theme = function_exists('admin_theme_for_user')
            ? admin_theme_for_user((int)($user['id'] ?? 0))
            : 'dark';

        $svc     = new BackupService();
        $backups = array_slice($svc->listBackups(), 0, 10);

        admin_layout_begin([
            'title'    => 'Backup',
            'theme'    => $theme,
            'active'   => 'backup',
            'user'     => $user,
            'next'     => '/backup',
            'pageCss'  => 'backup',
            'headline' => 'Backup',
            'subtitle' => 'Datenbankexport und Backup-Verwaltung.',
        ]);

        require __DIR__ . '/../Views/backup.php';

        admin_layout_end();
    }

    public function exportDb(): void
    {
        admin_require_system_user();

        \admin_verify_csrf();

        set_time_limit(120);

        $pdo  = db();
        $svc  = new BackupService();
        $sql  = $svc->exportDbSql($pdo);
        $path = $svc->writeBackupFile($sql, 'db');

        $filename = basename($path);
        $size     = strlen($sql);

        if (!headers_sent()) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . $size);
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
        }

        echo $sql;
    }
}
