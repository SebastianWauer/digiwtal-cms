<?php
declare(strict_types=1);

class DeployService
{
    public function __construct(
        private PDO $pdo,
        private DeploymentRepository $deployRepo,
        private ServerAccessRepository $accessRepo,
        private ModuleCombinator $moduleCombinator,
        private CmsProvisioningService $cmsProvisioner
    ) {}

    public function run(int $deploymentId, int $customerId, string $type = 'cms', array $sourceOverrides = []): bool
    {
        // 1. Deployment auf 'running' setzen
        $this->deployRepo->markStarted($deploymentId);
        $this->deployRepo->updateStatus($deploymentId, 'running');

        try {
            // 2. Server-Zugangsdaten laden
            $access = $this->accessRepo->findByCustomer($customerId);
            if ($access === null) {
                $this->log($deploymentId, '[ERROR] Kein Serverzugang konfiguriert.');
                $this->deployRepo->markFinished($deploymentId, 'failed');
                return false;
            }

            // 3. Passwort entschlüsseln
            $encData = $this->accessRepo->findEncrypted($customerId);
            if ($encData === null || empty($encData['password_enc'])) {
                $this->log($deploymentId, '[ERROR] Kein Passwort im Serverzugang gespeichert.');
                $this->deployRepo->markFinished($deploymentId, 'failed');
                return false;
            }
            $aad = 'cust:' . $customerId;
            $password = VaultCrypto::decrypt(
                (string)$encData['password_enc'],
                (string)$encData['password_nonce'],
                (string)$encData['password_tag'],
                $aad
            );

            // 4. Verbindung aufbauen
            $method = $this->resolveTransferMethod($access);
            if ($method === null) {
                $this->log($deploymentId, '[ERROR] Keine passende Transfermethode verfügbar. Für SFTP/SSH wird die PHP-Erweiterung ssh2 benötigt, für FTP die cURL-FTP-Unterstützung.');
                $this->deployRepo->markFinished($deploymentId, 'failed');
                return false;
            }
            $this->log($deploymentId, "[INFO] Verbindungsmethode: {$method} (konfiguriertes Protokoll: " . (string)($access['protocol'] ?? 'sftp') . ')');

            // 5. CMS-Quelldateien bestimmen
            $cmsSourceDir = $this->resolveSourceDir($sourceOverrides, 'cms', dirname(__DIR__, 2) . '/CMS');
            $frontendSourceDir = $this->resolveSourceDir($sourceOverrides, 'frontend', dirname(__DIR__, 2) . '/Frontend');

            $operations = $this->buildDeployOperations($type, $cmsSourceDir, $frontendSourceDir, $access);
            foreach ($operations as $operation) {
                if (!is_dir($operation['source_dir'])) {
                    $this->log($deploymentId, '[ERROR] Deploy-Quellverzeichnis nicht gefunden: ' . $operation['source_dir']);
                    $this->deployRepo->markFinished($deploymentId, 'failed');
                    return false;
                }
                $this->log($deploymentId, "[INFO] Deploy-Segment {$operation['label']}: {$operation['source_dir']} -> {$operation['remote_path']}");
            }

            // Backup-Metadata vor dem eigentlichen Upload anlegen
            $remotePath = rtrim((string)($access['server_path'] ?? '/CMS'), '/');
            $backupPath = $this->backupRemoteFiles($deploymentId, $access, $password, $remotePath, $method);
            if ($backupPath !== null) {
                $fileCountLocal = 0;
                foreach ($operations as $operation) {
                    $fileCountLocal += count($this->moduleCombinator->buildFileList(
                        $customerId,
                        $operation['source_dir'],
                        $operation['ignore']
                    ));
                }
                $this->deployRepo->createBackupRecord($deploymentId, $customerId, $backupPath, $fileCountLocal);
                $this->log($deploymentId, '[INFO] Backup-Record erstellt (ID in deployment_backups).');
            }

            if ($method === 'ssh2') {
                $sftp = $this->connectSftp($access, $password);
                if ($sftp === null) {
                    $this->log($deploymentId, '[ERROR] SFTP-Verbindung fehlgeschlagen.');
                    $this->deployRepo->markFinished($deploymentId, 'failed');
                    return false;
                }

                $uploaded = 0;
                $totalFiles = 0;
                foreach ($operations as $operation) {
                    $files = $this->moduleCombinator->buildFileList($customerId, $operation['source_dir'], $operation['ignore']);
                    $totalFiles += count($files);
                    foreach ($files as $localFile => $relPath) {
                        $remote = $operation['remote_path'] . '/' . $relPath;
                        $remoteDir = dirname($remote);
                        @ssh2_sftp_mkdir($sftp, $remoteDir, 0755, true);
                        if ($this->uploadFileSftp($sftp, $localFile, $remote)) {
                            $uploaded++;
                        } else {
                            $this->log($deploymentId, "[WARN] Fehler bei {$operation['label']}: {$relPath}");
                        }
                    }
                }
                $this->log($deploymentId, "[INFO] {$uploaded}/{$totalFiles} Dateien übertragen.");
            } elseif ($method === 'shell_sftp') {
                $uploaded = 0;
                $totalFiles = 0;
                foreach ($operations as $operation) {
                    $files = $this->moduleCombinator->buildFileList($customerId, $operation['source_dir'], $operation['ignore']);
                    $totalFiles += count($files);
                    if ($files === []) {
                        continue;
                    }
                    if (!$this->uploadFilesShellSftp($access, $password, $files, $operation['remote_path'])) {
                        $this->log($deploymentId, "[ERROR] Shell-SFTP-Upload fehlgeschlagen für {$operation['label']}.");
                        $this->deployRepo->markFinished($deploymentId, 'failed');
                        return false;
                    }
                    $uploaded += count($files);
                }
                $this->log($deploymentId, "[INFO] {$uploaded}/{$totalFiles} Dateien übertragen.");
            } else {
                // cURL FTP Fallback
                $host = (string)($access['host'] ?? '');
                $port = (int)($access['port'] ?? 21);
                $username = (string)($access['username'] ?? '');
                $uploaded = 0;
                $totalFiles = 0;
                foreach ($operations as $operation) {
                    $files = $this->moduleCombinator->buildFileList($customerId, $operation['source_dir'], $operation['ignore']);
                    $totalFiles += count($files);
                    foreach ($files as $localFile => $relPath) {
                        $remoteUrl = 'ftp://' . $host . ':' . $port . $operation['remote_path'] . '/' . $relPath;
                        if ($this->uploadFileCurl($localFile, $remoteUrl, $username, $password)) {
                            $uploaded++;
                        } else {
                            $this->log($deploymentId, "[WARN] FTP-Fehler bei {$operation['label']}: {$relPath}");
                        }
                    }
                }
                $this->log($deploymentId, "[INFO] {$uploaded}/{$totalFiles} Dateien übertragen.");
            }

            // 7. Version aus config/version.php lesen und im Deployment speichern
            $versionFile = $cmsSourceDir . '/config/version.php';
            $version = null;
            if (is_file($versionFile)) {
                $cfg = include $versionFile;
                $version = is_array($cfg) ? (string)($cfg['cms_version'] ?? null) : null;
            }
            if ($version !== null) {
                $stmt = $this->pdo->prepare('UPDATE deployments SET version_to = ? WHERE id = ?');
                $stmt->execute([$version, $deploymentId]);
            }

            $this->log($deploymentId, '[SUCCESS] Deploy abgeschlossen.');
            $this->deployRepo->markFinished($deploymentId, 'success');
            return true;

        } catch (Throwable $e) {
            $this->log($deploymentId, '[FATAL] ' . $e->getMessage());
            $this->deployRepo->markFinished($deploymentId, 'failed');
            return false;
        }
    }

    public function provisionAndRun(int $deploymentId, array $customer, int $customerId, string $type = 'cms', array $sourceOverrides = []): bool
    {
        $this->deployRepo->markStarted($deploymentId);
        $this->deployRepo->updateStatus($deploymentId, 'running');

        try {
            $access = $this->accessRepo->findByCustomer($customerId);
            if ($access === null) {
                $this->log($deploymentId, '[ERROR] Kein Serverzugang konfiguriert.');
                $this->deployRepo->markFinished($deploymentId, 'failed');
                return false;
            }

            $encData = $this->accessRepo->findEncrypted($customerId);
            if ($encData === null || empty($encData['password_enc'])) {
                $this->log($deploymentId, '[ERROR] Kein Passwort im Serverzugang gespeichert.');
                $this->deployRepo->markFinished($deploymentId, 'failed');
                return false;
            }

            $aad = 'cust:' . $customerId;
            $password = VaultCrypto::decrypt(
                (string)$encData['password_enc'],
                (string)$encData['password_nonce'],
                (string)$encData['password_tag'],
                $aad
            );

            $dbPassword = VaultCrypto::decrypt(
                (string)($encData['db_password_enc'] ?? ''),
                (string)($encData['db_password_nonce'] ?? ''),
                (string)($encData['db_password_tag'] ?? ''),
                $aad
            );
            $setupToken = '';
            if (
                !empty($encData['deploy_token_enc'])
                && !empty($encData['deploy_token_nonce'])
                && !empty($encData['deploy_token_tag'])
            ) {
                try {
                    $setupToken = VaultCrypto::decrypt(
                        (string)$encData['deploy_token_enc'],
                        (string)$encData['deploy_token_nonce'],
                        (string)$encData['deploy_token_tag'],
                        $aad
                    );
                } catch (Throwable) {
                    $setupToken = '';
                }
            }

            $method = $this->resolveTransferMethod($access);
            if ($method === null) {
                $this->log($deploymentId, '[ERROR] Keine passende Transfermethode verfügbar. Für SFTP/SSH wird die PHP-Erweiterung ssh2 benötigt, für FTP die cURL-FTP-Unterstützung.');
                $this->deployRepo->markFinished($deploymentId, 'failed');
                return false;
            }
            $this->log($deploymentId, "[INFO] Verbindungsmethode: {$method} (konfiguriertes Protokoll: " . (string)($access['protocol'] ?? 'sftp') . ')');

            $cmsSourceDir = $this->resolveSourceDir($sourceOverrides, 'cms', dirname(__DIR__, 2) . '/CMS');
            $frontendSourceDir = $this->resolveSourceDir($sourceOverrides, 'frontend', dirname(__DIR__, 2) . '/Frontend');

            $remotePath = rtrim((string)($access['server_path'] ?? '/CMS'), '/');
            $operations = $this->buildDeployOperations($type, $cmsSourceDir, $frontendSourceDir, $access);
            foreach ($operations as $operation) {
                if (!is_dir($operation['source_dir'])) {
                    $this->log($deploymentId, '[ERROR] Deploy-Quellverzeichnis nicht gefunden: ' . $operation['source_dir']);
                    $this->deployRepo->markFinished($deploymentId, 'failed');
                    return false;
                }
                $this->log($deploymentId, "[INFO] Deploy-Segment {$operation['label']}: {$operation['source_dir']} -> {$operation['remote_path']}");
            }

            $backupPath = $this->backupRemoteFiles($deploymentId, $access, $password, $remotePath, $method);
            if ($backupPath !== null) {
                $fileCountLocal = 0;
                foreach ($operations as $operation) {
                    $fileCountLocal += count($this->moduleCombinator->buildFileList(
                        $customerId,
                        $operation['source_dir'],
                        $operation['ignore']
                    ));
                }
                $this->deployRepo->createBackupRecord($deploymentId, $customerId, $backupPath, $fileCountLocal);
                $this->log($deploymentId, '[INFO] Backup-Record erstellt (ID in deployment_backups).');
            }

            if ($method === 'ssh2') {
                $sftp = $this->connectSftp($access, $password);
                if ($sftp === null) {
                    $this->log($deploymentId, '[ERROR] SFTP-Verbindung fehlgeschlagen.');
                    $this->deployRepo->markFinished($deploymentId, 'failed');
                    return false;
                }

                $uploaded = 0;
                $totalFiles = 0;
                foreach ($operations as $operation) {
                    $files = $this->moduleCombinator->buildFileList($customerId, $operation['source_dir'], $operation['ignore']);
                    $totalFiles += count($files);
                    foreach ($files as $localFile => $relPath) {
                        $remote = $operation['remote_path'] . '/' . $relPath;
                        $remoteDir = dirname($remote);
                        @ssh2_sftp_mkdir($sftp, $remoteDir, 0755, true);
                        if ($this->uploadFileSftp($sftp, $localFile, $remote)) {
                            $uploaded++;
                        } else {
                            $this->log($deploymentId, "[WARN] Fehler bei {$operation['label']}: {$relPath}");
                        }
                    }
                }
                $this->log($deploymentId, "[INFO] {$uploaded}/{$totalFiles} Dateien übertragen.");
            } else {
                $host = (string)($access['host'] ?? '');
                $port = (int)($access['port'] ?? 21);
                $username = (string)($access['username'] ?? '');
                $uploaded = 0;
                $totalFiles = 0;
                foreach ($operations as $operation) {
                    $files = $this->moduleCombinator->buildFileList($customerId, $operation['source_dir'], $operation['ignore']);
                    $totalFiles += count($files);
                    foreach ($files as $localFile => $relPath) {
                        $remoteUrl = 'ftp://' . $host . ':' . $port . $operation['remote_path'] . '/' . $relPath;
                        if ($this->uploadFileCurl($localFile, $remoteUrl, $username, $password)) {
                            $uploaded++;
                        } else {
                            $this->log($deploymentId, "[WARN] FTP-Fehler bei {$operation['label']}: {$relPath}");
                        }
                    }
                }
                $this->log($deploymentId, "[INFO] {$uploaded}/{$totalFiles} Dateien übertragen.");
            }

            $envContent = $this->cmsProvisioner->buildEnvContent($access, $dbPassword, $setupToken);
            if (!$this->uploadRemoteTextFile($access, $password, $remotePath . '/.env', $envContent, $method)) {
                $this->log($deploymentId, '[ERROR] .env konnte nicht auf den Zielserver geschrieben werden.');
                $this->deployRepo->markFinished($deploymentId, 'failed');
                return false;
            }
            $this->log($deploymentId, '[INFO] .env auf Zielserver geschrieben.');

            $this->cmsProvisioner->provisionCustomer($customer, $access, $encData, function (string $message) use ($deploymentId): void {
                $this->log($deploymentId, $message);
            });

            $versionFile = $cmsSourceDir . '/config/version.php';
            $version = null;
            if (is_file($versionFile)) {
                $cfg = include $versionFile;
                $version = is_array($cfg) ? (string)($cfg['cms_version'] ?? null) : null;
            }
            if ($version !== null) {
                $stmt = $this->pdo->prepare('UPDATE deployments SET version_to = ? WHERE id = ?');
                $stmt->execute([$version, $deploymentId]);
            }

            $this->log($deploymentId, '[SUCCESS] Erstdeploy und Provisionierung abgeschlossen.');
            $this->deployRepo->markFinished($deploymentId, 'success');
            return true;
        } catch (Throwable $e) {
            $this->log($deploymentId, '[FATAL] ' . $e->getMessage());
            $this->deployRepo->markFinished($deploymentId, 'failed');
            return false;
        }
    }

    private function resolveTransferMethod(array $access): ?string
    {
        $protocol = (string)($access['protocol'] ?? 'sftp');

        if (in_array($protocol, ['ssh', 'sftp'], true)) {
            if (
                function_exists('ssh2_connect')
                && function_exists('ssh2_auth_password')
                && function_exists('ssh2_sftp')
            ) {
                return 'ssh2';
            }

            return $this->canUseShellSftp() ? 'shell_sftp' : null;
        }

        if ($protocol === 'ftp') {
            return function_exists('curl_init') ? 'curl_ftp' : null;
        }

        return null;
    }

    private function connectSftp(array $access, string $password): mixed
    {
        if (!function_exists('ssh2_connect') || !function_exists('ssh2_auth_password') || !function_exists('ssh2_sftp')) {
            return null;
        }

        $host = (string)($access['host'] ?? '');
        $port = (int)($access['port'] ?? 22);
        $username = (string)($access['username'] ?? '');

        if ($host === '' || $username === '') {
            return null;
        }

        $conn = @ssh2_connect($host, $port);
        if (!$conn) {
            return null;
        }

        if (!@ssh2_auth_password($conn, $username, $password)) {
            return null;
        }

        $sftp = @ssh2_sftp($conn);
        return $sftp ?: null;
    }

    private function uploadFileSftp(mixed $sftp, string $localPath, string $remotePath): bool
    {
        $remoteStreamPath = 'ssh2.sftp://' . (int)$sftp . $remotePath;
        $in = @fopen($localPath, 'rb');
        if ($in === false) {
            return false;
        }
        $out = @fopen($remoteStreamPath, 'wb');
        if ($out === false) {
            fclose($in);
            return false;
        }
        $ok = stream_copy_to_stream($in, $out) !== false;
        fclose($in);
        fclose($out);
        return $ok;
    }

    private function uploadFileCurl(string $localPath, string $remoteUrl, string $username, string $password): bool
    {
        $fp = @fopen($localPath, 'rb');
        if ($fp === false) {
            return false;
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $remoteUrl,
            CURLOPT_UPLOAD => true,
            CURLOPT_INFILE => $fp,
            CURLOPT_INFILESIZE => filesize($localPath) ?: 0,
            CURLOPT_USERPWD => $username . ':' . $password,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FTP_CREATE_MISSING_DIRS => CURLFTP_CREATE_DIR_RETRY,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 15,
        ]);

        $result = curl_exec($ch);
        $errno = curl_errno($ch);
        curl_close($ch);
        fclose($fp);

        return $result !== false && $errno === 0;
    }

    private function uploadRemoteTextFile(array $access, string $password, string $remotePath, string $contents, string $method): bool
    {
        $tmp = tempnam(sys_get_temp_dir(), 'digiwtal_env_');
        if ($tmp === false) {
            return false;
        }

        try {
            if (file_put_contents($tmp, $contents) === false) {
                return false;
            }

            if ($method === 'ssh2') {
                $sftp = $this->connectSftp($access, $password);
                if ($sftp === null) {
                    return false;
                }
                $remoteDir = dirname($remotePath);
                @ssh2_sftp_mkdir($sftp, $remoteDir, 0755, true);
                return $this->uploadFileSftp($sftp, $tmp, $remotePath);
            }

            if ($method === 'shell_sftp') {
                return $this->uploadFilesShellSftp(
                    $access,
                    $password,
                    [$tmp => ltrim($remotePath, '/')],
                    '/'
                );
            }

            $remoteUrl = 'ftp://' . (string)($access['host'] ?? '') . ':' . (int)($access['port'] ?? 21) . $remotePath;
            return $this->uploadFileCurl($tmp, $remoteUrl, (string)($access['username'] ?? ''), $password);
        } finally {
            @unlink($tmp);
        }
    }

    private function resolveSourceDir(array $sourceOverrides, string $key, string $fallback): string
    {
        $candidate = (string)($sourceOverrides[$key] ?? '');
        if ($candidate !== '') {
            return rtrim($candidate, '/');
        }

        return $fallback;
    }

    /**
     * @return array<int,array{label:string,source_dir:string,remote_path:string,ignore:array<int,string>}>
     */
    private function buildDeployOperations(string $type, string $cmsSourceDir, string $frontendSourceDir, array $access): array
    {
        $serverPath = rtrim((string)($access['server_path'] ?? '/CMS'), '/');
        $htmlPath = rtrim((string)($access['html_path'] ?? '/Frontend'), '/');

        return match ($type) {
            'frontend' => [[
                'label' => 'frontend',
                'source_dir' => $frontendSourceDir,
                'remote_path' => $htmlPath,
                'ignore' => [],
            ]],
            'combined', 'full' => [
                [
                    'label' => 'cms',
                    'source_dir' => $cmsSourceDir,
                    'remote_path' => $serverPath,
                    'ignore' => [],
                ],
                [
                    'label' => 'frontend',
                    'source_dir' => $frontendSourceDir,
                    'remote_path' => $htmlPath,
                    'ignore' => [],
                ],
            ],
            default => [[
                'label' => 'cms',
                'source_dir' => $cmsSourceDir,
                'remote_path' => $serverPath,
                'ignore' => [],
            ]],
        };
    }

    private function log(int $deploymentId, string $message): void
    {
        $line = '[' . gmdate('Y-m-d H:i:s') . '] ' . $message . "\n";
        $stmt = $this->pdo->prepare(
            'UPDATE deployments SET log = CONCAT(COALESCE(log, ""), ?) WHERE id = ?'
        );
        $stmt->execute([$line, $deploymentId]);
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

    private function uploadFilesShellSftp(array $access, string $password, array $files, string $remoteBasePath): bool
    {
        $host = (string)($access['host'] ?? '');
        $port = (int)($access['port'] ?? 22);
        $username = (string)($access['username'] ?? '');

        if ($host === '' || $username === '' || $password === '') {
            return false;
        }

        $remoteBasePath = rtrim($remoteBasePath, '/');
        if ($remoteBasePath === '') {
            $remoteBasePath = '/';
        }

        $commands = [];
        $dirs = [];
        foreach ($files as $localFile => $relPath) {
            $relPath = ltrim(str_replace('\\', '/', $relPath), '/');
            $remotePath = $remoteBasePath === '/' ? '/' . $relPath : $remoteBasePath . '/' . $relPath;
            $dir = dirname($remotePath);

            while ($dir !== '.' && $dir !== '' && $dir !== '/') {
                $dirs[$dir] = true;
                $dir = dirname($dir);
            }
        }

        uksort($dirs, static fn(string $a, string $b): int => strlen($a) <=> strlen($b));
        foreach (array_keys($dirs) as $dir) {
            $commands[] = '-mkdir ' . $this->quoteSftpPath($dir);
        }

        foreach ($files as $localFile => $relPath) {
            $relPath = ltrim(str_replace('\\', '/', $relPath), '/');
            $remotePath = $remoteBasePath === '/' ? '/' . $relPath : $remoteBasePath . '/' . $relPath;
            $commands[] = 'put ' . $this->quoteSftpPath($localFile) . ' ' . $this->quoteSftpPath($remotePath);
        }

        return $this->runShellSftpBatch($access, $password, $commands);
    }

    private function runShellSftpBatch(array $access, string $password, array $commands): bool
    {
        $host = (string)($access['host'] ?? '');
        $port = (int)($access['port'] ?? 22);
        $username = (string)($access['username'] ?? '');
        if ($host === '' || $username === '' || $password === '' || !$this->canUseShellSftp()) {
            return false;
        }

        $batchFile = tempnam(sys_get_temp_dir(), 'digiwtal_sftp_');
        if ($batchFile === false) {
            return false;
        }

        try {
            if (file_put_contents($batchFile, implode("\n", $commands) . "\n") === false) {
                return false;
            }

            $sshpassPath = trim((string)@shell_exec('command -v sshpass 2>/dev/null'));
            $sftpPath = trim((string)@shell_exec('command -v sftp 2>/dev/null'));
            if ($sshpassPath === '' || $sftpPath === '') {
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

    private function quoteSftpPath(string $path): string
    {
        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $path) . '"';
    }

    /**
     * Lädt alle Remote-Dateien als lokales Backup herunter.
     * Gibt den lokalen Backup-Pfad zurück, oder null bei Fehler.
     */
    private function backupRemoteFiles(
        int $deploymentId,
        array $access,
        string $password,
        string $remotePath,
        string $method
    ): ?string {
        $backupDir = dirname(__DIR__) . '/storage/backups/'
            . date('Ymd_His') . '_cust' . ($access['customer_id'] ?? 'X');

        if (!is_dir($backupDir)) {
            @mkdir($backupDir, 0750, true);
        }
        if (!is_dir($backupDir)) {
            $this->log($deploymentId, '[WARN] Backup-Verzeichnis konnte nicht erstellt werden: ' . $backupDir);
            return null;
        }

        $meta = json_encode([
            'remote_path' => $remotePath,
            'host' => $access['host'] ?? '',
            'created_at' => gmdate('c'),
            'method' => $method,
            'note' => 'Automatisches Backup vor Deploy – für Rollback bitte Remote-Stand manuell sichern',
        ], JSON_PRETTY_PRINT);

        file_put_contents($backupDir . '/backup_meta.json', (string)$meta);
        $this->log($deploymentId, '[INFO] Backup-Metadata gespeichert: ' . $backupDir);

        return $backupDir;
    }

    /**
     * Versucht einen Rollback auf den letzten bekannten guten Stand.
     * Gibt true zurück wenn Rollback erfolgreich.
     */
    public function rollback(int $deploymentId, int $customerId): bool
    {
        $this->log($deploymentId, '[ROLLBACK] Rollback gestartet...');

        $lastBackup = $this->deployRepo->findLatestBackup($customerId);
        return $this->rollbackWithBackupRecord($deploymentId, $lastBackup);
    }

    public function rollbackFromDeployment(int $deploymentId, int $customerId, int $targetDeploymentId): bool
    {
        $this->log($deploymentId, '[ROLLBACK] Rollback für Deployment #' . $targetDeploymentId . ' gestartet...');
        $backup = $this->deployRepo->findBackupByDeployment($customerId, $targetDeploymentId);
        return $this->rollbackWithBackupRecord($deploymentId, $backup);
    }

    private function rollbackWithBackupRecord(int $deploymentId, ?array $backup): bool
    {
        if ($backup === null) {
            $this->log($deploymentId, '[ROLLBACK] Kein Backup gefunden – Rollback nicht möglich.');
            $this->deployRepo->updateStatus($deploymentId, 'failed');
            return false;
        }

        $metaFile = (string)($backup['backup_path'] ?? '') . '/backup_meta.json';
        if (!is_file($metaFile)) {
            $this->log($deploymentId, '[ROLLBACK] Backup-Metadata nicht gefunden: ' . $metaFile);
            $this->deployRepo->updateStatus($deploymentId, 'failed');
            return false;
        }

        $meta = json_decode((string)file_get_contents($metaFile), true);
        $this->log($deploymentId, '[ROLLBACK] Letztes Backup vom: ' . ($meta['created_at'] ?? 'unbekannt'));
        $this->log($deploymentId, '[ROLLBACK] Remote-Pfad: ' . ($meta['remote_path'] ?? 'unbekannt'));
        $this->log($deploymentId, '[ROLLBACK] HINWEIS: Automatischer Datei-Rollback noch nicht implementiert.');
        $this->log($deploymentId, '[ROLLBACK] Bitte Remote-Stand manuell aus dem Backup wiederherstellen.');

        $this->deployRepo->updateStatus($deploymentId, 'rolled_back');
        return false;
    }

    /** @deprecated Nutze ModuleCombinator::buildFileList() stattdessen */
    private function collectFiles(string $baseDir): array
    {
        $result = [];
        $ignore = ['.git', '.env', '.claude', 'node_modules', 'storage', 'upload.sh'];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($baseDir, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file->isFile()) continue;
            $absPath = $file->getPathname();
            $relPath = ltrim(str_replace($baseDir, '', $absPath), '/\\');
            $parts = explode('/', str_replace('\\', '/', $relPath));
            $skip = false;
            foreach ($ignore as $ign) {
                if (in_array($ign, $parts, true)) { $skip = true; break; }
            }
            if ($skip) continue;
            $result[$absPath] = $relPath;
        }
        return $result;
    }
}
