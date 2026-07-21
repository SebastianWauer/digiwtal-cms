<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

$root = dirname(__DIR__);
require_once dirname($root) . '/shared/FileLogger.php';

(static function (string $rootPath): void {
    $file = $rootPath . '/.env';
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
})($root);

require_once $root . '/app/VaultCrypto.php';
require_once $root . '/repositories/CustomerRepository.php';
require_once $root . '/repositories/CustomerModuleRepository.php';
require_once $root . '/repositories/ModuleRepository.php';
require_once $root . '/repositories/ServerAccessRepository.php';
require_once $root . '/repositories/DeploymentRepository.php';
require_once $root . '/repositories/PushSubscriptionRepository.php';
require_once $root . '/services/ModuleCombinator.php';
require_once $root . '/services/CmsProvisioningService.php';
require_once $root . '/services/DeployService.php';
require_once $root . '/services/PushService.php';

$opts = getopt('', ['mode:', 'deployment-id:', 'customer-id:', 'type::', 'target-deployment-id::']);

$mode = (string)($opts['mode'] ?? '');
$deploymentId = (int)($opts['deployment-id'] ?? 0);
$customerId = (int)($opts['customer-id'] ?? 0);
$type = (string)($opts['type'] ?? 'cms');
$targetDeploymentId = isset($opts['target-deployment-id']) ? (int)$opts['target-deployment-id'] : null;

if ($mode === '' || $deploymentId <= 0 || $customerId <= 0) {
    fwrite(STDERR, "Missing required args.\n");
    exit(1);
}

$dbHost = (string)(getenv('DB_HOST') ?: 'localhost');
$dbName = (string)(getenv('DB_NAME') ?: '');
$dbUser = (string)(getenv('DB_USER') ?: '');
$dbPass = (string)(getenv('DB_PASS') ?: '');

if ($dbName === '' || $dbUser === '') {
    fwrite(STDERR, "Missing DB env.\n");
    exit(1);
}

try {
    $pdo = new PDO(
        'mysql:host=' . $dbHost . ';dbname=' . $dbName . ';charset=utf8mb4',
        $dbUser,
        $dbPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Throwable $e) {
    fwrite(STDERR, "DB connect failed: " . $e->getMessage() . "\n");
    exit(1);
}

$deploymentRepo = new DeploymentRepository($pdo);
$accessRepo = new ServerAccessRepository($pdo);
$moduleRepo = new ModuleRepository($pdo);
$customerModuleRepo = new CustomerModuleRepository($pdo);
$moduleCombinator = new ModuleCombinator($customerModuleRepo, $moduleRepo);
$cmsProvisioner = new CmsProvisioningService();
$deployService = new DeployService($pdo, $deploymentRepo, $accessRepo, $moduleCombinator, $cmsProvisioner);
$customerRepo = new CustomerRepository($pdo);
$pushRepo = new PushSubscriptionRepository($pdo);
$pushService = new PushService();

/**
 * @return array{customer_name:string,finished_at:string,log:string}
 */
function loadDeploymentFailureContext(PDO $pdo, int $deploymentId, int $customerId): array
{
    $stmt = $pdo->prepare(
        'SELECT d.log, d.finished_at, c.name AS customer_name
         FROM deployments d
         LEFT JOIN customers c ON c.id = d.customer_id
         WHERE d.id = ? AND d.customer_id = ?
         LIMIT 1'
    );
    $stmt->execute([$deploymentId, $customerId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
        return ['customer_name' => 'Unbekannt', 'finished_at' => gmdate('c'), 'log' => ''];
    }

    return [
        'customer_name' => (string)($row['customer_name'] ?? 'Unbekannt'),
        'finished_at' => (string)($row['finished_at'] ?? gmdate('c')),
        'log' => (string)($row['log'] ?? ''),
    ];
}

function resolveDeployErrorEmail(PDO $pdo): ?string
{
    $fromEnv = trim((string)(getenv('DEPLOY_ERROR_EMAIL') ?: ''));
    if ($fromEnv !== '' && filter_var($fromEnv, FILTER_VALIDATE_EMAIL)) {
        return $fromEnv;
    }

    try {
        $stmt = $pdo->query("SELECT email FROM admin_users WHERE is_active = 1 ORDER BY id ASC LIMIT 1");
        $email = trim((string)($stmt ? $stmt->fetchColumn() : ''));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email;
        }
    } catch (Throwable) {
        // ignore
    }

    return null;
}

function sendDeployFailureEmail(PDO $pdo, int $deploymentId, int $customerId, string $frontendBase): void
{
    $to = resolveDeployErrorEmail($pdo);
    if ($to === null) {
        return;
    }

    $ctx = loadDeploymentFailureContext($pdo, $deploymentId, $customerId);
    $base = rtrim($frontendBase, '/');
    $link = $base . '/admin/customers/' . $customerId . '/deployments';

    $subject = '[DIGIWTAL] Deployment fehlgeschlagen (#' . $deploymentId . ')';
    $body = "Ein Deployment ist fehlgeschlagen.\n\n"
        . 'Kunde: ' . $ctx['customer_name'] . " (ID: {$customerId})\n"
        . 'Zeitpunkt: ' . $ctx['finished_at'] . "\n"
        . "Deployment-ID: {$deploymentId}\n"
        . "Log-Link: {$link}\n\n"
        . "Fehlermeldung / Log-Auszug:\n"
        . substr(trim($ctx['log']), -4000) . "\n";
    $headers = "Content-Type: text/plain; charset=UTF-8\r\n";

    @mail($to, $subject, $body, $headers);
}

function notifyDeployFailure(
    PDO $pdo,
    PushService $pushService,
    PushSubscriptionRepository $pushRepo,
    int $deploymentId,
    int $customerId
): void {
    $adminHost = trim((string)(getenv('ADMIN_HOST') ?: ''));
    $baseUrl = $adminHost !== '' ? ('https://' . $adminHost) : '';
    $ctx = loadDeploymentFailureContext($pdo, $deploymentId, $customerId);

    if ($pushService->isConfigured()) {
        $pushService->sendToAll(
            $pushRepo->listAll(),
            'Deployment fehlgeschlagen',
            'Kunde: ' . $ctx['customer_name'] . ' | Deployment #' . $deploymentId,
            ($baseUrl !== '' ? $baseUrl : '') . '/admin/customers/' . $customerId . '/deployments',
            'deploy-failed'
        );
    }

    if ($baseUrl === '') {
        $baseUrl = 'https://verwaltung.example.com';
    }
    sendDeployFailureEmail($pdo, $deploymentId, $customerId, $baseUrl);
}

try {
    if ($mode === 'run') {
        $ok = $deployService->run($deploymentId, $customerId, $type);
        if (!$ok) {
            notifyDeployFailure($pdo, $pushService, $pushRepo, $deploymentId, $customerId);
            exit(1);
        }
        exit(0);
    }

    if ($mode === 'provision') {
        $customer = $customerRepo->findById($customerId);
        if ($customer === null) {
            $deploymentRepo->markFinished($deploymentId, 'failed');
            notifyDeployFailure($pdo, $pushService, $pushRepo, $deploymentId, $customerId);
            exit(1);
        }
        $ok = $deployService->provisionAndRun($deploymentId, $customer, $customerId, $type);
        if (!$ok) {
            notifyDeployFailure($pdo, $pushService, $pushRepo, $deploymentId, $customerId);
            exit(1);
        }
        exit(0);
    }

    if ($mode === 'rollback_latest') {
        $ok = $deployService->rollback($deploymentId, $customerId);
        if (!$ok) {
            notifyDeployFailure($pdo, $pushService, $pushRepo, $deploymentId, $customerId);
            exit(1);
        }
        exit(0);
    }

    if ($mode === 'rollback_deployment' && $targetDeploymentId !== null && $targetDeploymentId > 0) {
        $ok = $deployService->rollbackFromDeployment($deploymentId, $customerId, $targetDeploymentId);
        if (!$ok) {
            notifyDeployFailure($pdo, $pushService, $pushRepo, $deploymentId, $customerId);
            exit(1);
        }
        exit(0);
    }

    fwrite(STDERR, "Unknown mode.\n");
    $deploymentRepo->markFinished($deploymentId, 'failed');
    notifyDeployFailure($pdo, $pushService, $pushRepo, $deploymentId, $customerId);
    exit(1);
} catch (Throwable $e) {
    FileLogger::channel('verwaltung')->error('[DEPLOY_WORKER] ' . $e->getMessage());
    $deploymentRepo->markFinished($deploymentId, 'failed');
    notifyDeployFailure($pdo, $pushService, $pushRepo, $deploymentId, $customerId);
    exit(1);
}
