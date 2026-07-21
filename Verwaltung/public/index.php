<?php
declare(strict_types=1);

// -------------------------------------------------------
// Error Handling (before .env loader)
// -------------------------------------------------------
error_reporting(E_ALL);
ini_set('log_errors', '1');

// -------------------------------------------------------
// Minimal .env loader (no Composer required)
// -------------------------------------------------------
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

$allowedHost = (string)(getenv('ADMIN_HOST') ?: '');

// HTTPS enforcement
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
    || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443)
    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');

if (!$isHttps) {
    $host = (string)($_SERVER['HTTP_HOST'] ?? '');
    $uri  = (string)($_SERVER['REQUEST_URI'] ?? '/');

    if ($allowedHost !== '' && $host !== $allowedHost) {
        $host = $allowedHost;
    }
    if ($host === '' && $allowedHost !== '') {
        $host = $allowedHost;
    }

    header('Location: https://' . $host . $uri, true, 301);
    exit;
}

// Security headers (updated CSP for inline styles)
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header("Content-Security-Policy: base-uri 'self'; form-action 'self'; frame-ancestors 'none'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; connect-src 'self' https://127.0.0.1:8765 https://localhost:8765; default-src 'self';");

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => true,
    'httponly' => true,
    'samesite' => 'Strict'
]);

session_start();

require_once __DIR__ . '/../app/Router.php';
require_once __DIR__ . '/../app/AdminAuth.php';
require_once __DIR__ . '/../app/Csrf.php';
require_once __DIR__ . '/../app/Totp.php';
require_once __DIR__ . '/../app/VaultCrypto.php';
require_once __DIR__ . '/../repositories/AdminUserRepository.php';
require_once __DIR__ . '/../repositories/CustomerRepository.php';
require_once __DIR__ . '/../repositories/CustomerHealthRepository.php';
require_once __DIR__ . '/../repositories/ServerCredentialRepository.php';
require_once __DIR__ . '/../repositories/ModuleRepository.php';
require_once __DIR__ . '/../repositories/CustomerModuleRepository.php';
require_once __DIR__ . '/../repositories/ServerAccessRepository.php';
require_once __DIR__ . '/../repositories/DeploymentRepository.php';
require_once __DIR__ . '/../repositories/WebhookTokenRepository.php';
require_once __DIR__ . '/../services/DeployService.php';
require_once __DIR__ . '/../services/CmsProvisioningService.php';
require_once __DIR__ . '/../services/ModuleCombinator.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/DashboardController.php';
require_once __DIR__ . '/../controllers/CustomerController.php';
require_once __DIR__ . '/../controllers/VaultController.php';
require_once __DIR__ . '/../controllers/ModuleController.php';
require_once __DIR__ . '/../controllers/ServerAccessController.php';
require_once __DIR__ . '/../controllers/DeployController.php';
require_once __DIR__ . '/../repositories/PushSubscriptionRepository.php';
require_once __DIR__ . '/../services/PushService.php';
require_once __DIR__ . '/../controllers/PushController.php';
require_once __DIR__ . '/../controllers/CustomerDetailController.php';
require_once __DIR__ . '/../services/AuditLogger.php';
require_once __DIR__ . '/../controllers/AuditController.php';
require_once __DIR__ . '/../controllers/AdminUserController.php';
require_once __DIR__ . '/../controllers/WebhookController.php';
require_once __DIR__ . '/../controllers/WebhookManageController.php';

// -------------------------------------------------------
// Database connection from .env
// -------------------------------------------------------
$dbHost = (string)(getenv('DB_HOST') ?: 'localhost');
$dbName = (string)(getenv('DB_NAME') ?: '');
$dbUser = (string)(getenv('DB_USER') ?: '');
$dbPass = (string)(getenv('DB_PASS') ?: '');

try {
    $pdo = new PDO(
        'mysql:host=' . $dbHost . ';dbname=' . $dbName . ';charset=utf8mb4',
        $dbUser,
        $dbPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    http_response_code(503);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Wartung</title>'
        . '</head><body><main><h1>Verbindungsfehler</h1><p>Datenbankverbindung fehlgeschlagen. Bitte später erneut versuchen.</p></main></body></html>';
    exit;
}

// -------------------------------------------------------
// Repository instances
// -------------------------------------------------------
$userRepo = new AdminUserRepository($pdo);
$customerRepo = new CustomerRepository($pdo);
$vaultRepo = new ServerCredentialRepository($pdo);
$moduleRepo = new ModuleRepository($pdo);
$customerModuleRepo = new CustomerModuleRepository($pdo);
$accessRepo = new ServerAccessRepository($pdo);
$deploymentRepo = new DeploymentRepository($pdo);
$pushRepo = new PushSubscriptionRepository($pdo);
$webhookRepo = new WebhookTokenRepository($pdo);

$moduleCombinator = new ModuleCombinator($customerModuleRepo, $moduleRepo);
$cmsProvisioner = new CmsProvisioningService();
$deployService = new DeployService($pdo, $deploymentRepo, $accessRepo, $moduleCombinator, $cmsProvisioner);

// -------------------------------------------------------
// Controller instances
// -------------------------------------------------------
$auditLogger     = new AuditLogger($pdo);
$auditController = new AuditController($pdo);

$authController           = new AuthController($userRepo, $pdo, $auditLogger);
$dashboardController      = new DashboardController($customerRepo);
$customerController       = new CustomerController($customerRepo, $auditLogger);
$vaultController          = new VaultController($customerRepo, $vaultRepo);
$moduleController         = new ModuleController($moduleRepo, $customerRepo, $customerModuleRepo);
$serverAccessController   = new ServerAccessController($customerRepo, $accessRepo);
$deployController         = new DeployController($customerRepo, $accessRepo, $deploymentRepo, $deployService, $auditLogger);
$pushController           = new PushController($pushRepo);
$customerDetailController = new CustomerDetailController($customerRepo, $pdo);
$adminUserController      = new AdminUserController($userRepo, $auditLogger);
$webhookController        = new WebhookController($webhookRepo, $deploymentRepo, $deployService, $customerRepo, $auditLogger);
$webhookManageController  = new WebhookManageController($webhookRepo, $customerRepo, $auditLogger);

// -------------------------------------------------------
// Router setup
// -------------------------------------------------------
$router = new Router();

$router->add('GET', '/admin/login', [$authController, 'showLogin']);
$router->add('POST', '/admin/login', [$authController, 'handleLogin']);
$router->add('GET', '/admin/verify-2fa', [$authController, 'showVerify2FA']);
$router->add('POST', '/admin/verify-2fa', [$authController, 'handleVerify2FA']);
$router->add('GET', '/admin/logout', [$authController, 'logout']);
$router->add('GET', '/admin/dashboard', [$dashboardController, 'index']);
$router->add('GET', '/admin/customers', [$customerController, 'index']);
$router->add('GET', '/admin/customers/create', [$customerController, 'create']);
$router->add('POST', '/admin/customers', [$customerController, 'store']);
$router->add('GET', '/admin/customers/{id}/edit', [$customerController, 'edit']);
$router->add('POST', '/admin/customers/{id}', [$customerController, 'update']);
$router->add('POST', '/admin/customers/{id}/toggle', [$customerController, 'toggle']);
$router->add('GET', '/admin/customers/{id}/vault', [$vaultController, 'index']);
$router->add('GET', '/admin/customers/{id}/vault/create', [$vaultController, 'create']);
$router->add('POST', '/admin/customers/{id}/vault', [$vaultController, 'store']);
$router->add('POST', '/admin/vault/{id}/reveal', [$vaultController, 'reveal']);
$router->add('POST', '/admin/vault/{id}/rotate', [$vaultController, 'rotate']);
$router->add('POST', '/admin/vault/{id}/delete', [$vaultController, 'delete']);
$router->add('GET', '/admin/modules', [$moduleController, 'index']);
$router->add('GET', '/admin/customers/{id}/modules', [$moduleController, 'customerModules']);
$router->add('POST', '/admin/customers/{id}/modules/{moduleId}', [$moduleController, 'toggleModule']);
$router->add('GET',  '/admin/customers/{id}/access', [$serverAccessController, 'show']);
$router->add('POST', '/admin/customers/{id}/access', [$serverAccessController, 'save']);
$router->add('GET',  '/admin/customers/{id}/deployments', [$deployController, 'history']);
$router->add('POST', '/admin/customers/{id}/deployments/install', [$deployController, 'install']);
$router->add('POST', '/admin/customers/{id}/deployments/test-connections', [$deployController, 'testConnections']);
$router->add('POST', '/admin/customers/{id}/deployments/agent-payload', [$deployController, 'agentPayload']);
$router->add('POST', '/admin/customers/{id}/deployments/{deploymentId}/stop', [$deployController, 'stop']);
$router->add('GET',  '/admin/customers/{id}/deployments/{deploymentId}/status', [$deployController, 'status']);
$router->add('POST', '/admin/customers/{id}/deployments/{deploymentId}/rollback', [$deployController, 'rollbackToDeployment']);
$router->add('POST', '/admin/customers/{id}/deployments/rollback', [$deployController, 'rollback']);
$router->add('POST', '/admin/push/subscribe',   [$pushController, 'subscribe']);
$router->add('POST', '/admin/push/unsubscribe', [$pushController, 'unsubscribe']);
$router->add('GET',  '/admin/push/vapid-public', [$pushController, 'vapidPublic']);
$router->add('GET', '/admin/customers/{id}', [$customerDetailController, 'show']);
$router->add('GET', '/admin/audit', [$auditController, 'index']);
$router->add('GET',  '/admin/admin-users', [$adminUserController, 'index']);
$router->add('GET',  '/admin/admin-users/create', [$adminUserController, 'create']);
$router->add('GET',  '/admin/admin-users/{id}', [$adminUserController, 'show']);
$router->add('POST', '/admin/admin-users', [$adminUserController, 'store']);
$router->add('POST', '/admin/admin-users/{id}/totp/start', [$adminUserController, 'startTotpSetup']);
$router->add('POST', '/admin/admin-users/{id}/totp/verify', [$adminUserController, 'verifyTotpSetup']);
$router->add('POST', '/admin/admin-users/{id}/password', [$adminUserController, 'resetPassword']);
$router->add('GET',  '/admin/admin-users/{id}/totp/qr', [$adminUserController, 'qr']);
$router->add('POST', '/admin/admin-users/{id}/delete', [$adminUserController, 'delete']);
$router->add('GET',  '/admin/customers/{id}/webhooks', [$webhookManageController, 'show']);
$router->add('POST', '/admin/customers/{id}/webhooks', [$webhookManageController, 'store']);
$router->add('POST', '/admin/webhooks/{id}/delete', [$webhookManageController, 'delete']);
$router->add('POST', '/webhook/deploy', [$webhookController, 'trigger']);

// -------------------------------------------------------
// Dispatch request
// -------------------------------------------------------
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if ($path === '/' || $path === '') {
    header('Location: /admin/dashboard');
    exit;
}

$router->dispatch($method, $path);
