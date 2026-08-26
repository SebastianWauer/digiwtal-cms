<?php
declare(strict_types=1);

$GLOBALS['CMS_REQUEST_T0'] = microtime(true);
ob_start();

$__appRoot = getenv('CMS_APP_ROOT');
$__appRoot = ($__appRoot !== false && $__appRoot !== '') ? rtrim($__appRoot, '/') : (__DIR__ . '/..');
require_once $__appRoot . '/app/bootstrap.php';
// Setup-Redirect: wenn nicht installiert → zu /setup
$_uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$_uriNoBase = $_uri;
$_base = cms_base_path();
if ($_base !== '' && str_starts_with($_uriNoBase, $_base)) {
    $_uriNoBase = substr($_uriNoBase, strlen($_base));
    if ($_uriNoBase === '') $_uriNoBase = '/';
}
if (!str_starts_with($_uriNoBase, '/setup') && !str_starts_with($_uriNoBase, '/assets')) {
    try {
        if (\App\Core\Setup::allowSetupRequest(db())) {
            header('Location: ' . cms_base_path() . '/setup');
            exit;
        }
    } catch (\Throwable) {
        // DB nicht erreichbar → Setup darf normal laufen
    }
}
$env   = \App\Core\Env::get('APP_ENV', 'production');
$isDev = ($env === 'development');
ini_set('display_errors',         $isDev ? '1' : '0');
ini_set('display_startup_errors', $isDev ? '1' : '0');
error_reporting($isDev ? E_ALL : (E_ALL & ~E_DEPRECATED & ~E_STRICT));

use App\Http\Router;

$router = new Router();

/**
 * UI Routes
 */
$router->get('/', \App\Controller\DashboardController::class, 'handle');
if ($isDev) {
    $router->get('/test', \App\Controller\TestController::class, 'handle');
}

$router->get('/login', \App\Controller\LoginController::class, 'show');
$router->post('/login', \App\Controller\LoginController::class, 'submit');
$router->get('/password-reset', \App\Controller\PasswordResetController::class, 'showRequest');
$router->post('/password-reset', \App\Controller\PasswordResetController::class, 'submitRequest');
$router->get('/password-reset/{token:[A-Fa-f0-9]+}', \App\Controller\PasswordResetController::class, 'showResetForm');
$router->post('/password-reset/{token:[A-Fa-f0-9]+}', \App\Controller\PasswordResetController::class, 'submitResetForm');

$router->post('/logout', \App\Controller\LogoutController::class, 'handle');

$router->post('/theme', \App\Controller\ThemeController::class, 'handle');
$router->post('/prefs', \App\Controller\UserPrefsController::class, 'handle');

$router->get('/pages', \App\Controller\PagesController::class, 'index');
$router->get('/pages/edit', \App\Controller\PagesController::class, 'edit');
$router->post('/pages/preview', \App\Controller\PagesController::class, 'preview');
$router->post('/pages/save', \App\Controller\PagesController::class, 'save');
$router->post('/pages/delete', \App\Controller\PagesController::class, 'delete');
$router->post('/pages/restore', \App\Controller\PagesController::class, 'restore');
$router->get('/pages/deleted', \App\Controller\PagesController::class, 'deleted');
$router->post('/pages/purge', \App\Controller\PagesController::class, 'purge');

$router->get('/events', \App\Controller\EventsController::class, 'index');
$router->get('/events/categories', \App\Controller\EventsController::class, 'categories');
$router->post('/events/categories/save', \App\Controller\EventsController::class, 'saveCategory');
$router->get('/events/edit', \App\Controller\EventsController::class, 'edit');
$router->post('/events/save', \App\Controller\EventsController::class, 'save');
$router->post('/events/delete', \App\Controller\EventsController::class, 'delete');
$router->post('/events/restore', \App\Controller\EventsController::class, 'restore');
$router->get('/events/deleted', \App\Controller\EventsController::class, 'deleted');
$router->post('/events/purge', \App\Controller\EventsController::class, 'purge');

$router->get('/news', \App\Controller\NewsController::class, 'index');
$router->get('/news/categories', \App\Controller\NewsController::class, 'categories');
$router->post('/news/categories/save', \App\Controller\NewsController::class, 'saveCategory');
$router->get('/news/edit', \App\Controller\NewsController::class, 'edit');
$router->post('/news/save', \App\Controller\NewsController::class, 'save');
$router->post('/news/delete', \App\Controller\NewsController::class, 'delete');
$router->post('/news/restore', \App\Controller\NewsController::class, 'restore');
$router->get('/news/deleted', \App\Controller\NewsController::class, 'deleted');
$router->post('/news/purge', \App\Controller\NewsController::class, 'purge');

$router->get('/forms/submissions', \App\Controller\FormSubmissionsController::class, 'index');
$router->post('/forms/submissions/status', \App\Controller\FormSubmissionsController::class, 'status');

$router->get('/migrate', \App\Controller\MigrateController::class, 'show');
$router->post('/migrate/run', \App\Controller\MigrateController::class, 'run');
$router->post('/migrate/baseline', \App\Controller\MigrateController::class, 'baseline');

/**
 * System (NUR SystemUser Admin)
 */
$router->get('/system/health', \App\Controller\SystemHealthController::class, 'show');
$router->get('/system/health/api', \App\Controller\SystemHealthController::class, 'api');
$router->post('/system/health/reset', \App\Controller\SystemHealthController::class, 'reset');

$router->get('/backup',     \App\Controller\BackupController::class, 'show');
$router->post('/backup/db', \App\Controller\BackupController::class, 'exportDb');

/**
 * MEDIA (Module)
 */
$router->get('/media', \App\Controller\MediaController::class, 'index');
$router->get('/media/show', \App\Controller\MediaController::class, 'show');
$router->get('/media/deleted', \App\Controller\MediaController::class, 'deleted');
$router->get('/media/edit', \App\Controller\MediaController::class, 'edit');
$router->post('/media/save', \App\Controller\MediaController::class, 'save');
$router->post('/media/upload', \App\Controller\MediaController::class, 'upload');
$router->post('/media/folder/create', \App\Controller\MediaController::class, 'folderCreate');
$router->post('/media/folder/move', \App\Controller\MediaController::class, 'folderMove');
$router->post('/media/folder/delete', \App\Controller\MediaController::class, 'folderDelete');
$router->post('/media/delete', \App\Controller\MediaController::class, 'delete');
$router->post('/media/restore', \App\Controller\MediaController::class, 'restore');
$router->post('/media/purge', \App\Controller\MediaController::class, 'purge');
$router->post('/media/move', \App\Controller\MediaController::class, 'move');
$router->post('/media/rotate', \App\Controller\MediaController::class, 'rotate');
$router->get('/media/thumb', \App\Controller\MediaController::class, 'thumb');
$router->get('/media/file', \App\Controller\MediaController::class, 'file');

// USERS
$router->get('/users', \App\Controller\UsersController::class, 'index');
$router->get('/users/deleted', \App\Controller\UsersController::class, 'deleted');
$router->get('/users/edit', \App\Controller\UsersController::class, 'edit');
$router->post('/users/save', \App\Controller\UsersController::class, 'save');
$router->post('/users/delete', \App\Controller\UsersController::class, 'delete');
$router->post('/users/restore', \App\Controller\UsersController::class, 'restore');
$router->post('/users/purge', \App\Controller\UsersController::class, 'purge');

// ROLES
$router->get('/roles', \App\Controller\RolesController::class, 'index');
$router->get('/roles/deleted', \App\Controller\RolesController::class, 'deleted');
$router->get('/roles/edit', \App\Controller\RolesController::class, 'edit');
$router->post('/roles/save', \App\Controller\RolesController::class, 'save');
$router->post('/roles/delete', \App\Controller\RolesController::class, 'delete');
$router->post('/roles/restore', \App\Controller\RolesController::class, 'restore');
$router->post('/roles/purge', \App\Controller\RolesController::class, 'purge');

$router->get('/settings', \App\Controller\SiteSettingsController::class, 'show');
$router->post('/settings', \App\Controller\SiteSettingsController::class, 'save');

$router->get('/changelog', \App\Controller\ChangelogController::class, 'show');

/**
 * Setup-Wizard (nur wenn nicht installiert)
 */
$router->get('/setup',         \App\Controller\SetupController::class, 'step1');
$router->post('/setup/step1',  \App\Controller\SetupController::class, 'step1Post');
$router->get('/setup/step2',   \App\Controller\SetupController::class, 'step2');
$router->post('/setup/step2',  \App\Controller\SetupController::class, 'step2Post');
$router->get('/setup/step3',   \App\Controller\SetupController::class, 'step3');
$router->post('/setup/finish', \App\Controller\SetupController::class, 'finish');
$router->get('/setup/status',  \App\Controller\SetupController::class, 'status');

$router->dispatch();

$ms = round((microtime(true) - (float)($GLOBALS['CMS_REQUEST_T0'] ?? microtime(true))) * 1000, 2);
if (!headers_sent()) {
    header('X-Response-Time-ms: ' . $ms);
}

ob_end_flush();



