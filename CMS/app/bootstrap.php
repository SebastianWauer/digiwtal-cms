<?php
declare(strict_types=1);

/**
 * -------------------------------------------------
 * Session Hardening (System-level, enforced)
 * -------------------------------------------------
 */

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

// Cookies nur über HTTPS (Admin-Bereich ist HTTPS)
ini_set('session.cookie_secure', $https ? '1' : '0');

// JS darf Session-Cookie niemals lesen
ini_set('session.cookie_httponly', '1');

// Session ID nicht via URL
ini_set('session.use_only_cookies', '1');

// Strict mode gegen Session-Fixation
ini_set('session.use_strict_mode', '1');

// SameSite (Default Lax; bei Admin ausreichend)
ini_set('session.cookie_samesite', 'Lax');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}


/**
 * Zentrales Bootstrap für alle Requests.
 * - PSR-4 Autoloader App\
 * - lädt Infrastruktur + Legacy-Funktionen
 * - KEINE DB-Sideeffects (kein Seed, keine Migrationen, kein admin_pdo-Aufruf)
 */

$ROOT = realpath(__DIR__ . '/..');
if ($ROOT === false) {
    http_response_code(500);
    echo 'Bootstrap error: ROOT not resolvable';
    exit;
}

/**
 * Wichtig: Einige Legacy-Funktionen referenzieren Paths/Redirect direkt.
 * Deshalb hier VORAB hart includen (Case-sensitive auf Linux!).
 */
$pathsFile = $ROOT . '/app/Core/Paths.php';
$redirFile = $ROOT . '/app/Http/Redirect.php';

if (!is_file($pathsFile)) {
    http_response_code(500);
    echo 'Bootstrap error: missing app/Core/Paths.php';
    exit;
}
if (!is_file($redirFile)) {
    http_response_code(500);
    echo 'Bootstrap error: missing app/Http/Redirect.php';
    exit;
}

require_once $pathsFile;
require_once $redirFile;
$sharedLogger = $ROOT . '/shared/FileLogger.php';
$localLogger = $ROOT . '/app/FileLogger.php';
if (is_file($sharedLogger)) {
    require_once $sharedLogger;
} elseif (is_file($localLogger)) {
    require_once $localLogger;
}

require_once $ROOT . '/app/Core/Env.php';
\App\Core\Env::load($ROOT);

/**
 * Composer Autoload (falls vorhanden)
 */
$composer = $ROOT . '/vendor/autoload.php';
if (is_file($composer)) {
    require_once $composer;
}

/**
 * PSR-4 Autoloader App\
 */
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) return;

    $rel = substr($class, strlen($prefix));
    $path = __DIR__ . '/' . str_replace('\\', '/', $rel) . '.php';

    if (is_file($path)) {
        require_once $path;
    }
});

/**
 * Request profiling (System Health / Request Timing)
 * Muss VOR db.php geladen werden (db.php benutzt cms_prof_add_db()).
 */
require_once $ROOT . '/app/profiler.php';
if (function_exists('cms_prof_init')) {
    cms_prof_init();
}

/**
 * Legacy (Functions) – Reihenfolge:
 * DB muss vor admin_auth geladen sein (admin_auth kann db() brauchen).
 */
require_once $ROOT . '/app/db.php';
require_once $ROOT . '/app/admin_auth.php';
require_once $ROOT . '/app/admin_csrf.php';
require_once $ROOT . '/app/admin_prefs.php';
require_once $ROOT . '/app/admin_env.php';

/**
 * Views/Includes
 */
require_once $ROOT . '/app/includes/components.php';
require_once $ROOT . '/app/includes/media_picker.php';
require_once $ROOT . '/app/includes/sidebar.php';
require_once $ROOT . '/app/includes/layout.php';

\App\Core\PluginLoader::load(dirname(__DIR__) . '/plugins');
\App\Core\Hooks::do_action('cms_bootstrap_done');
