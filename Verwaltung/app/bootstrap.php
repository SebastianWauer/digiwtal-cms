<?php
declare(strict_types=1);

/**
 * CLI Bootstrap – kein HTTP, kein Session-Start
 * Lädt .env und stellt Umgebungsvariablen bereit
 */

(static function (): void {
    $file = dirname(__DIR__) . '/.env';
    if (!is_file($file)) {
        return;
    }
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        $eq = strpos($line, '=');
        if ($eq === false) {
            continue;
        }
        $k = trim(substr($line, 0, $eq));
        $v = trim(substr($line, $eq + 1));
        if (strlen($v) >= 2 && $v[0] === $v[-1] && ($v[0] === '"' || $v[0] === "'")) {
            $v = substr($v, 1, -1);
        }
        putenv($k . '=' . $v);
        $_ENV[$k] = $v;
    }
})();
require_once dirname(__DIR__, 2) . '/shared/FileLogger.php';

// -------------------------------------------------------
// Error Handling (DEV/PROD aware)
// -------------------------------------------------------
$isDev = (getenv('APP_ENV') === 'development' || getenv('APP_DEBUG') === '1');

error_reporting(E_ALL);
ini_set('log_errors', '1');

if ($isDev) {
    ini_set('display_errors', '1');
} else {
    ini_set('display_errors', '0');
}

// Set error log path if not already configured
$errorLog = getenv('ERROR_LOG') ?: dirname(__DIR__) . '/storage/logs/error.log';
if (!is_dir(dirname($errorLog))) {
    @mkdir(dirname($errorLog), 0755, true);
}
ini_set('error_log', $errorLog);

// Exception Handler (only if not already set)
if (!defined('EXCEPTION_HANDLER_SET')) {
    set_exception_handler(function (Throwable $e): void {
        $errorId = substr(md5((string)time() . $e->getMessage()), 0, 8);
        $logMsg = sprintf(
            "[%s] Exception %s: %s in %s:%d\nStack trace:\n%s\n",
            $errorId,
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        );
        FileLogger::channel('verwaltung')->error($logMsg);

        if (php_sapi_name() === 'cli') {
            echo "FATAL ERROR [$errorId]: " . $e->getMessage() . "\n";
            exit(1);
        } else {
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
            $isDev = (getenv('APP_ENV') === 'development' || getenv('APP_DEBUG') === '1');
            if ($isDev) {
                echo "<h1>Fatal Error [$errorId]</h1>";
                echo "<pre>" . htmlspecialchars($logMsg, ENT_QUOTES) . "</pre>";
            } else {
                echo "<!DOCTYPE html><html><head><title>500 Error</title></head><body>";
                echo "<h1>500 Internal Server Error</h1>";
                echo "<p>Error ID: $errorId</p>";
                echo "</body></html>";
            }
            exit;
        }
    });
    define('EXCEPTION_HANDLER_SET', true);
}
