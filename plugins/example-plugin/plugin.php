<?php
declare(strict_types=1);

/**
 * Example Plugin – registriert sich auf 'cms_after_page_save'
 * und schreibt einen Logeintrag nach storage/logs/plugin.log.
 */

\App\Core\Hooks::add_action('cms_after_page_save', function (int $id, string $slug): void {
    $logDir  = dirname(__DIR__, 2) . '/storage/logs';
    $logFile = $logDir . '/plugin.log';

    @mkdir($logDir, 0755, true);

    $line = date('Y-m-d H:i:s') . ' [example-plugin] cms_after_page_save'
          . ' id=' . $id . ' slug=' . $slug . PHP_EOL;

    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
});
