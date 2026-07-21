<?php
declare(strict_types=1);

class DeployController
{
    public function __construct(
        private CustomerRepository $customerRepo,
        private ServerAccessRepository $accessRepo,
        private DeploymentRepository $deploymentRepo,
        private DeployService $deployService,
        private AuditLogger $audit
    ) {}

    public function history(int $customerId): void
    {
        AdminAuth::requireAuth();

        $customer = $this->customerRepo->findById($customerId);
        if ($customer === null) {
            http_response_code(404);
            echo '<!DOCTYPE html><html><head><title>404</title></head><body><h1>404 Not Found</h1></body></html>';
            exit;
        }

        $deploymentLoadErrors = [];
        try {
            $deployments = $this->deploymentRepo->listByCustomer($customerId, 20);
        } catch (Throwable $e) {
            FileLogger::channel('verwaltung')->error('[DEPLOY_HISTORY] ' . $e->getMessage());
            $deployments = [];
            $deploymentLoadErrors = [
                'Deployments konnten nicht geladen werden.',
                'Bitte prüfe, ob die Migrationen 012_create_deployments.sql und 013_deployment_backups.sql ausgeführt wurden.'
            ];
        }

        $access = $this->accessRepo->findByCustomer($customerId);

        $success = $_SESSION['flash_success'] ?? null;
        $flashErrors = $_SESSION['flash_errors'] ?? [];
        unset($_SESSION['flash_success'], $_SESSION['flash_errors']);

        $errors = [];
        if (is_array($flashErrors)) {
            $errors = $flashErrors;
        } elseif ($flashErrors !== null) {
            $errors[] = (string)$flashErrors;
        }
        $errors = array_merge($errors, $deploymentLoadErrors);

        require __DIR__ . '/../views/deployments/history.php';
    }

    public function install(int $customerId): void
    {
        AdminAuth::requireAuth();

        if (!Csrf::verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_errors'] = ['CSRF token invalid'];
            header('Location: /admin/customers/' . $customerId . '/deployments');
            exit;
        }

        $customer = $this->customerRepo->findById($customerId);
        if ($customer === null) {
            http_response_code(404);
            echo '<!DOCTYPE html><html><head><title>404</title></head><body><h1>404 Not Found</h1></body></html>';
            exit;
        }

        $this->ensureNoRunningDeployment($customerId);

        $deploymentId = $this->deploymentRepo->create($customerId, 'cms', 'first_install');
        $queued = $this->enqueueDeployWorker('provision', $deploymentId, $customerId, 'cms');

        $this->audit->log(
            'deploy.first_install',
            'deployment',
            $deploymentId,
            'customer: ' . $customerId
        );

        if ($queued) {
            $_SESSION['flash_success'] = 'Erstinstallation #' . $deploymentId . ' wurde als Background-Job gestartet.';
        } else {
            $this->deploymentRepo->markFinished($deploymentId, 'failed');
            $_SESSION['flash_errors'] = ['Erstinstallation #' . $deploymentId . ' konnte nicht gestartet werden.'];
        }

        header('Location: /admin/customers/' . $customerId . '/deployments');
        exit;
    }

    public function rollback(int $customerId): void
    {
        AdminAuth::requireAuth();

        if (!Csrf::verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_errors'] = ['CSRF token invalid'];
            header('Location: /admin/customers/' . $customerId . '/deployments');
            exit;
        }

        $customer = $this->customerRepo->findById($customerId);
        if ($customer === null) {
            http_response_code(404);
            echo '<!DOCTYPE html><html><head><title>404</title></head><body><h1>404 Not Found</h1></body></html>';
            exit;
        }

        // Neuen Deployment-Eintrag für den Rollback anlegen
        $deploymentId = $this->deploymentRepo->create($customerId, 'cms', 'rollback');
        $queued = $this->enqueueDeployWorker('rollback_latest', $deploymentId, $customerId, 'cms');
        $this->audit->log('deploy.rollback', 'deployment', $deploymentId, "customer: {$customerId}");

        if ($queued) {
            $_SESSION['flash_success'] = 'Rollback #' . $deploymentId . ' wurde als Background-Job gestartet.';
        } else {
            $this->deploymentRepo->markFinished($deploymentId, 'failed');
            $_SESSION['flash_errors'] = ['Rollback #' . $deploymentId . ' konnte nicht gestartet werden.'];
        }

        header('Location: /admin/customers/' . $customerId . '/deployments');
        exit;
    }

    public function rollbackToDeployment(int $customerId, int $deploymentId): void
    {
        AdminAuth::requireAuth();

        if (!Csrf::verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_errors'] = ['CSRF token invalid'];
            header('Location: /admin/customers/' . $customerId . '/deployments');
            exit;
        }

        $target = $this->deploymentRepo->findById($deploymentId);
        if ($target === null || (int)($target['customer_id'] ?? 0) !== $customerId) {
            $_SESSION['flash_errors'] = ['Deployment nicht gefunden.'];
            header('Location: /admin/customers/' . $customerId . '/deployments');
            exit;
        }

        $rollbackDeploymentId = $this->deploymentRepo->create($customerId, 'cms', 'rollback:deployment:' . $deploymentId);
        $queued = $this->enqueueDeployWorker('rollback_deployment', $rollbackDeploymentId, $customerId, 'cms', $deploymentId);
        $this->audit->log('deploy.rollback.target', 'deployment', $rollbackDeploymentId, "customer: {$customerId}, target: {$deploymentId}");

        if ($queued) {
            $_SESSION['flash_success'] = 'Rollback-Job #' . $rollbackDeploymentId . ' auf Stand von Deployment #' . $deploymentId . ' gestartet.';
        } else {
            $this->deploymentRepo->markFinished($rollbackDeploymentId, 'failed');
            $_SESSION['flash_errors'] = ['Rollback-Job konnte nicht gestartet werden.'];
        }

        header('Location: /admin/customers/' . $customerId . '/deployments');
        exit;
    }

    public function status(int $customerId, int $deploymentId): void
    {
        AdminAuth::requireAuth();

        $deployment = $this->deploymentRepo->findById($deploymentId);
        if ($deployment === null || (int)($deployment['customer_id'] ?? 0) !== $customerId) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'Deployment not found']);
            return;
        }

        header('Content-Type: application/json');
        echo json_encode([
            'ok' => true,
            'deployment_id' => (int)$deployment['id'],
            'status' => (string)($deployment['status'] ?? 'pending'),
            'started_at' => $deployment['started_at'] ?? null,
            'finished_at' => $deployment['finished_at'] ?? null,
            'log' => (string)($deployment['log'] ?? ''),
        ]);
    }

    public function stop(int $customerId, int $deploymentId): void
    {
        AdminAuth::requireAuth();

        if (!Csrf::verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_errors'] = ['CSRF token invalid'];
            header('Location: /admin/customers/' . $customerId . '/deployments');
            exit;
        }

        $deployment = $this->deploymentRepo->findById($deploymentId);
        if ($deployment === null || (int)($deployment['customer_id'] ?? 0) !== $customerId) {
            $_SESSION['flash_errors'] = ['Deployment nicht gefunden.'];
            header('Location: /admin/customers/' . $customerId . '/deployments');
            exit;
        }

        if ((string)($deployment['status'] ?? '') !== 'running') {
            $_SESSION['flash_errors'] = ['Nur laufende Deployments können manuell gestoppt werden.'];
            header('Location: /admin/customers/' . $customerId . '/deployments');
            exit;
        }

        $this->deploymentRepo->markStoppedAsFailed(
            $deploymentId,
            '[MANUAL] Deployment manuell auf failed gesetzt, weil der Prozess hängen blieb.'
        );
        $this->audit->log('deploy.stop', 'deployment', $deploymentId, 'customer: ' . $customerId);

        $_SESSION['flash_success'] = 'Deployment #' . $deploymentId . ' wurde manuell als fehlgeschlagen markiert.';
        header('Location: /admin/customers/' . $customerId . '/deployments');
        exit;
    }

    public function testConnections(int $customerId): void
    {
        AdminAuth::requireAuth();

        if (!Csrf::verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_errors'] = ['CSRF token invalid'];
            header('Location: /admin/customers/' . $customerId . '/deployments');
            exit;
        }

        $customer = $this->customerRepo->findById($customerId);
        if ($customer === null) {
            http_response_code(404);
            echo '<!DOCTYPE html><html><head><title>404</title></head><body><h1>404 Not Found</h1></body></html>';
            exit;
        }

        try {
            $access = $this->accessRepo->findByCustomer($customerId);
            if ($access === null) {
                $_SESSION['flash_errors'] = ['Kein Serverzugang für diesen Kunden gespeichert.'];
                header('Location: /admin/customers/' . $customerId . '/deployments');
                exit;
            }

            $encData = $this->accessRepo->findEncrypted($customerId) ?? [];
            $results = [];
            $hasError = false;

            [$serverOk, $serverMessage] = $this->testServerConnection($customerId, $access, $encData);
            $results[] = ($serverOk ? 'OK: ' : 'FEHLER: ') . $serverMessage;
            $hasError = $hasError || !$serverOk;

            [$dbOk, $dbMessage] = $this->testDatabaseConnection($customerId, $access, $encData);
            $results[] = ($dbOk ? 'OK: ' : 'FEHLER: ') . $dbMessage;
            $hasError = $hasError || !$dbOk;

            $message = implode(' | ', $results);
            $this->audit->log('deploy.connection_test', 'customer', $customerId, $message);

            if ($hasError) {
                $_SESSION['flash_errors'] = $results;
            } else {
                $_SESSION['flash_success'] = $message;
            }
        } catch (Throwable $e) {
            FileLogger::channel('verwaltung')->error('[DEPLOY_CONNECTION_TEST] ' . $e->getMessage());
            $_SESSION['flash_errors'] = ['Verbindungstest abgebrochen: ' . $e->getMessage()];
        }

        header('Location: /admin/customers/' . $customerId . '/deployments');
        exit;
    }

    public function agentPayload(int $customerId): void
    {
        AdminAuth::requireAuth();

        if (!Csrf::verify($_POST['csrf_token'] ?? '')) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'CSRF token invalid']);
            return;
        }

        $customer = $this->customerRepo->findById($customerId);
        if ($customer === null) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'Customer not found']);
            return;
        }

        $type = (string)($_POST['type'] ?? 'cms');
        if (!in_array($type, ['cms', 'frontend', 'combined'], true)) {
            $type = 'cms';
        }

        $access = $this->accessRepo->findByCustomer($customerId);
        if ($access === null) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'Serverzugang nicht gefunden']);
            return;
        }

        $encData = $this->accessRepo->findEncrypted($customerId) ?? [];
        if (empty($encData['password_enc'])) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'Server-Passwort fehlt']);
            return;
        }

        try {
            $password = VaultCrypto::decrypt(
                (string)$encData['password_enc'],
                (string)($encData['password_nonce'] ?? ''),
                (string)($encData['password_tag'] ?? ''),
                'cust:' . $customerId
            );
        } catch (Throwable $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'Server-Passwort konnte nicht entschlüsselt werden']);
            return;
        }

        $payload = [
            'customer_id' => $customerId,
            'customer_name' => (string)($customer['name'] ?? ''),
            'type' => $type,
            'server' => [
                'host' => (string)($access['host'] ?? ''),
                'port' => (int)($access['port'] ?? 22),
                'protocol' => (string)($access['protocol'] ?? 'sftp'),
                'username' => (string)($access['username'] ?? ''),
                'password' => $password,
                'server_path' => (string)($access['server_path'] ?? '/CMS'),
                'html_path' => (string)($access['html_path'] ?? '/Frontend'),
            ],
            'frontend' => [
                'canonical_base' => (string)($access['canonical_base'] ?? ''),
                'health_cms_url' => (string)($access['health_cms_url'] ?? ''),
                'site_name' => (string)($access['site_name'] ?? ''),
            ],
        ];

        $this->audit->log('deploy.agent_payload', 'customer', $customerId, 'type: ' . $type);

        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'payload' => $payload], JSON_UNESCAPED_SLASHES);
    }

    private function ensureNoRunningDeployment(int $customerId): void
    {
        $running = $this->deploymentRepo->listRunning();
        foreach ($running as $dep) {
            if ((int)($dep['customer_id'] ?? 0) === $customerId) {
                $_SESSION['flash_errors'] = ['Es läuft bereits ein Deployment für diesen Kunden.'];
                header('Location: /admin/customers/' . $customerId . '/deployments');
                exit;
            }
        }
    }

    private function enqueueDeployWorker(
        string $mode,
        int $deploymentId,
        int $customerId,
        string $type = 'cms',
        ?int $targetDeploymentId = null
    ): bool {
        $workerPath = dirname(__DIR__) . '/scripts/deploy_worker.php';
        if (!is_file($workerPath)) {
            FileLogger::channel('verwaltung')->error('[DEPLOY_QUEUE] Worker-Script fehlt: ' . $workerPath);
            return false;
        }

        $parts = [
            'php',
            escapeshellarg($workerPath),
            '--mode=' . escapeshellarg($mode),
            '--deployment-id=' . (int)$deploymentId,
            '--customer-id=' . (int)$customerId,
            '--type=' . escapeshellarg($type),
        ];
        if ($targetDeploymentId !== null) {
            $parts[] = '--target-deployment-id=' . (int)$targetDeploymentId;
        }
        $cmd = implode(' ', $parts) . ' > /dev/null 2>&1 &';

        exec($cmd, $output, $exitCode);
        if ($exitCode !== 0) {
            FileLogger::channel('verwaltung')->error('[DEPLOY_QUEUE] Konnte Worker nicht starten. cmd=' . $cmd . ' exit=' . $exitCode);
            return false;
        }

        return true;
    }

    private function testServerConnection(int $customerId, array $access, array $encData): array
    {
        $host = trim((string)($access['host'] ?? ''));
        $port = (int)($access['port'] ?? 0);
        $protocol = (string)($access['protocol'] ?? 'sftp');
        $username = trim((string)($access['username'] ?? ''));

        if ($host === '' || $port < 1) {
            return [false, 'Serverzugang unvollständig: Host oder Port fehlt.'];
        }

        $socket = @fsockopen($host, $port, $errno, $errstr, 10);
        if ($socket === false) {
            return [false, "Server {$host}:{$port} nicht erreichbar ({$errstr} / {$errno})."];
        }
        fclose($socket);

        $password = '';
        if (!empty($encData['password_enc'])) {
            try {
                $password = VaultCrypto::decrypt(
                    (string)$encData['password_enc'],
                    (string)($encData['password_nonce'] ?? ''),
                    (string)($encData['password_tag'] ?? ''),
                    'cust:' . $customerId
                );
            } catch (Throwable) {
                return [false, 'Server-Passwort konnte nicht entschlüsselt werden.'];
            }
        }

        if (in_array($protocol, ['ssh', 'sftp'], true)) {
            $hasSsh2Connect = function_exists('ssh2_connect');
            $hasSsh2AuthPassword = function_exists('ssh2_auth_password');
            $hasSsh2Sftp = function_exists('ssh2_sftp');
            $hasShellSftp = $this->canUseShellSftp();

            if ((!$hasSsh2Connect || !$hasSsh2AuthPassword || !$hasSsh2Sftp) && !$hasShellSftp) {
                return [
                    false,
                    "TCP zu {$host}:{$port} erreichbar, aber ssh2 ist im laufenden Request nicht vollständig verfügbar "
                    . "(ssh2_connect=" . ($hasSsh2Connect ? 'yes' : 'no')
                    . ', ssh2_auth_password=' . ($hasSsh2AuthPassword ? 'yes' : 'no')
                    . ', ssh2_sftp=' . ($hasSsh2Sftp ? 'yes' : 'no')
                    . ', shell_sftp=' . ($hasShellSftp ? 'yes' : 'no')
                    . ', php=' . PHP_VERSION
                    . ', sapi=' . PHP_SAPI . ').'
                ];
            }

            if ($username === '' || $password === '') {
                return [false, strtoupper($protocol) . ' benötigt Benutzername und Passwort.'];
            }

            if ($hasSsh2Connect && $hasSsh2AuthPassword && $hasSsh2Sftp) {
                $conn = @ssh2_connect($host, $port);
                if ($conn === false) {
                    return [false, strtoupper($protocol) . " Handshake zu {$host}:{$port} fehlgeschlagen."];
                }

                if (!@ssh2_auth_password($conn, $username, $password)) {
                    return [false, strtoupper($protocol) . " Login fehlgeschlagen für {$username}@{$host}:{$port}."];
                }

                return [true, strtoupper($protocol) . " Login erfolgreich für {$username}@{$host}:{$port} via ssh2."];
            }

            if ($hasShellSftp) {
                if ($this->testShellSftpLogin($host, $port, $username, $password)) {
                    return [true, strtoupper($protocol) . " Login erfolgreich für {$username}@{$host}:{$port} via shell_sftp."];
                }

                return [false, strtoupper($protocol) . " Login fehlgeschlagen für {$username}@{$host}:{$port} via shell_sftp."];
            }
        }

        if ($protocol === 'ftp') {
            if (!function_exists('curl_init')) {
                return [false, 'FTP-Test nicht möglich: cURL fehlt.'];
            }

            if ($username === '' || $password === '') {
                return [false, 'FTP benötigt Benutzername und Passwort.'];
            }

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => 'ftp://' . $host . ':' . $port . '/',
                CURLOPT_USERPWD => $username . ':' . $password,
                CURLOPT_NOBODY => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 15,
            ]);
            $result = curl_exec($ch);
            $errno = curl_errno($ch);
            $error = curl_error($ch);
            curl_close($ch);

            if ($result === false || $errno !== 0) {
                return [false, "FTP Login fehlgeschlagen für {$username}@{$host}:{$port} ({$error})."];
            }

            return [true, "FTP Login erfolgreich für {$username}@{$host}:{$port}."];
        }

        return [false, 'Unbekanntes Protokoll: ' . $protocol];
    }

    private function testDatabaseConnection(int $customerId, array $access, array $encData): array
    {
        $dbHost = trim((string)($access['db_host'] ?? ''));
        $dbPort = (int)($access['db_port'] ?? 3306);
        $dbName = trim((string)($access['db_name'] ?? ''));
        $dbUser = trim((string)($access['db_user'] ?? ''));

        if ($dbHost === '' || $dbName === '' || $dbUser === '') {
            return [false, 'Kundendatenbank unvollständig: DB-Host, DB-Name oder DB-Benutzer fehlt.'];
        }

        $dbPassword = '';
        if (!empty($encData['db_password_enc'])) {
            try {
                $dbPassword = VaultCrypto::decrypt(
                    (string)$encData['db_password_enc'],
                    (string)($encData['db_password_nonce'] ?? ''),
                    (string)($encData['db_password_tag'] ?? ''),
                    'cust:' . $customerId
                );
            } catch (Throwable) {
                return [false, 'DB-Passwort konnte nicht entschlüsselt werden.'];
            }
        }

        try {
            $pdo = new PDO(
                'mysql:host=' . $dbHost . ';port=' . $dbPort . ';dbname=' . $dbName . ';charset=utf8mb4',
                $dbUser,
                $dbPassword,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => 10,
                ]
            );
            $pdo->query('SELECT 1');
        } catch (Throwable $e) {
            return [false, 'DB-Verbindung fehlgeschlagen zu ' . $dbHost . ':' . $dbPort . '/' . $dbName . ' (' . $e->getMessage() . ').'];
        }

        return [true, 'DB-Verbindung erfolgreich zu ' . $dbHost . ':' . $dbPort . '/' . $dbName . '.'];
    }

    private function canUseShellSftp(): bool
    {
        if (!function_exists('proc_open') || !function_exists('shell_exec')) {
            return false;
        }

        $sftpPath = trim((string)@shell_exec('command -v sftp 2>/dev/null'));
        $sshpassPath = trim((string)@shell_exec('command -v sshpass 2>/dev/null'));

        return $sftpPath !== '' && $sshpassPath !== '';
    }

    private function testShellSftpLogin(string $host, int $port, string $username, string $password): bool
    {
        if (!$this->canUseShellSftp()) {
            return false;
        }

        $sshpassPath = trim((string)@shell_exec('command -v sshpass 2>/dev/null'));
        $sftpPath = trim((string)@shell_exec('command -v sftp 2>/dev/null'));
        if ($sshpassPath === '' || $sftpPath === '') {
            return false;
        }

        $batchFile = tempnam(sys_get_temp_dir(), 'digiwtal_sftp_test_');
        if ($batchFile === false) {
            return false;
        }

        try {
            if (file_put_contents($batchFile, "pwd\n") === false) {
                return false;
            }

            $cmd = escapeshellarg($sshpassPath)
                . ' -e '
                . escapeshellarg($sftpPath)
                . ' -oBatchMode=no -oStrictHostKeyChecking=no -oUserKnownHostsFile=/dev/null'
                . ' -P ' . (int)$port
                . ' -b ' . escapeshellarg($batchFile)
                . ' ' . escapeshellarg($username . '@' . $host);

            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];
            $process = @proc_open($cmd, $descriptors, $pipes, null, ['SSHPASS' => $password]);
            if (!is_resource($process)) {
                return false;
            }

            fclose($pipes[0]);
            stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            stream_get_contents($pipes[2]);
            fclose($pipes[2]);

            return proc_close($process) === 0;
        } finally {
            @unlink($batchFile);
        }
    }

}
