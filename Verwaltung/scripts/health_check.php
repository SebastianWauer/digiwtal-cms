<?php
declare(strict_types=1);

// Bootstrap
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/VaultCrypto.php';
require_once dirname(__DIR__) . '/repositories/PushSubscriptionRepository.php';
require_once dirname(__DIR__) . '/services/PushService.php';

// Config
$dbHost = (string)(getenv('DB_HOST') ?: 'localhost');
$dbName = (string)(getenv('DB_NAME') ?: '');
$dbUser = (string)(getenv('DB_USER') ?: '');
$dbPass = (string)(getenv('DB_PASS') ?: '');

$pdo = new PDO(
    'mysql:host=' . $dbHost . ';dbname=' . $dbName . ';charset=utf8mb4',
    $dbUser,
    $dbPass,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$pushRepo    = new PushSubscriptionRepository($pdo);
$pushService = new PushService();

$adminEmail = 'info@digiwtal.de';
$debugMode = (getenv('HC_DEBUG') === '1');

// -------------------------------------------------------
// Helpers
// -------------------------------------------------------
function getActiveCustomers(PDO $pdo): array
{
    $stmt = $pdo->query("
        SELECT c.id, c.name, sa.host, sa.health_cms_url, sa.health_frontend_url,
               sa.health_token_enc, sa.health_token_nonce, sa.health_token_tag
        FROM customers c
        INNER JOIN server_access sa ON c.id = sa.customer_id
        WHERE c.abo_status = 'active'
          AND (sa.health_cms_url != '' OR sa.host != '')
          AND sa.health_token_enc != ''
        ORDER BY c.id ASC
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return is_array($rows) ? $rows : [];
}

function decryptToken(string $ciphertextB64, string $nonceB64, string $tagB64, int $customerId): ?string
{
    try {
        $aad = 'cust:' . $customerId;
        return VaultCrypto::decrypt($ciphertextB64, $nonceB64, $tagB64, $aad);
    } catch (Throwable) {
        return null;
    }
}

function normalizeBaseUrl(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    if (!preg_match('#^https?://#i', $value)) {
        $value = 'https://' . $value;
    }

    return rtrim($value, '/');
}

function performCmsHealthCheck(string $cmsBaseUrl, string $token): array
{
    $url = normalizeBaseUrl($cmsBaseUrl) . '/api/health?token=' . urlencode($token);
    $startTime = microtime(true);
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    
    $body = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErrno = curl_errno($ch);
    $curlErr = curl_error($ch);
    curl_close($ch);
    
    $responseMs = (int)((microtime(true) - $startTime) * 1000);
    
    // Timeout
    if ($curlErrno === 28) {
        return [
            'status' => 'timeout',
            'response_ms' => $responseMs,
            'raw_response' => ['error' => 'timeout', 'curl_errno' => 28],
        ];
    }
    
    // Network error or HTTP != 200
    if ($body === false || $httpCode !== 200) {
        return [
            'status' => 'down',
            'response_ms' => $responseMs,
            'raw_response' => ['error' => 'http_error', 'http_code' => $httpCode, 'curl_errno' => $curlErrno],
        ];
    }
    
    // Parse JSON
    $data = json_decode($body, true);
    if (!is_array($data)) {
        return [
            'status' => 'down',
            'response_ms' => $responseMs,
            'raw_response' => ['error' => 'invalid_json'],
        ];
    }
    
    // Map status
    $apiStatus = (string)($data['status'] ?? '');
    if (!in_array($apiStatus, ['healthy', 'degraded'], true)) {
        $apiStatus = 'down'; // Invalid/unknown status → treat as down
    }
    
    return [
        'status' => $apiStatus,
        'response_ms' => $responseMs,
        'raw_response' => $data,
        'cms_version' => (string)($data['cms_version'] ?? ''),
        'frontend_version' => ($data['frontend_version'] ?? null) !== null ? (string)$data['frontend_version'] : null,
        'php_version' => (string)($data['php_version'] ?? ''),
    ];
}

function performFrontendHealthCheck(string $frontendUrl): array
{
    $url = normalizeBaseUrl($frontendUrl);
    if ($url === '') {
        return [
            'checked' => false,
            'ok' => true,
            'status' => 'skipped',
            'response_ms' => 0,
            'http_code' => 0,
        ];
    }

    $startTime = microtime(true);
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_NOBODY => true,
    ]);

    curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErrno = curl_errno($ch);
    curl_close($ch);

    $responseMs = (int)((microtime(true) - $startTime) * 1000);
    $ok = $curlErrno === 0 && $httpCode >= 200 && $httpCode < 400;

    return [
        'checked' => true,
        'ok' => $ok,
        'status' => $ok ? 'healthy' : 'down',
        'response_ms' => $responseMs,
        'http_code' => $httpCode,
        'curl_errno' => $curlErrno,
        'url' => $url,
    ];
}

function getLastHealthStatus(PDO $pdo, int $customerId): ?string
{
    $stmt = $pdo->prepare("
        SELECT status FROM health_checks
        WHERE customer_id = ?
        ORDER BY checked_at DESC
        LIMIT 1
    ");
    $stmt->execute([$customerId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? (string)$row['status'] : null;
}

function getOutageStart(PDO $pdo, int $customerId): ?string
{
    // Find first non-healthy after last healthy (or first ever)
    $stmt = $pdo->prepare("
        SELECT MIN(checked_at) as outage_start
        FROM health_checks
        WHERE customer_id = ?
        AND status != 'healthy'
        AND checked_at > COALESCE(
            (SELECT MAX(checked_at) FROM health_checks WHERE customer_id = ? AND status = 'healthy'),
            '1970-01-01'
        )
    ");
    $stmt->execute([$customerId, $customerId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return ($row && $row['outage_start']) ? (string)$row['outage_start'] : null;
}

function storeHealthCheck(PDO $pdo, int $customerId, array $result): void
{
    $stmt = $pdo->prepare("
        INSERT INTO health_checks (customer_id, checked_at, status, cms_version, frontend_version, php_version, response_ms, raw_response)
        VALUES (?, NOW(), ?, ?, ?, ?, ?, ?)
    ");
    
    $rawJson = json_encode($result['raw_response'] ?? [], JSON_UNESCAPED_UNICODE);
    
    $stmt->execute([
        $customerId,
        $result['status'],
        $result['cms_version'] ?? null,
        $result['frontend_version'] ?? null,
        $result['php_version'] ?? null,
        $result['response_ms'] ?? 0,
        $rawJson,
    ]);
}

function sendMail(string $to, string $subject, string $body): void
{
    $headers = "From: noreply@digiwtal.de\r\nContent-Type: text/plain; charset=utf-8\r\n";
    @mail($to, $subject, $body, $headers);
}

// -------------------------------------------------------
// Main Loop
// -------------------------------------------------------
$customers = getActiveCustomers($pdo);

// DEBUG: Log customer count
if ($debugMode) { FileLogger::channel('verwaltung')->error("[HC] customers_count=" . count($customers)); }

// DEBUG: Test if health_checks table exists
try {
    $pdo->query("SELECT 1 FROM health_checks LIMIT 1");
    if ($debugMode) { FileLogger::channel('verwaltung')->error("[HC] health_checks_table=exists"); }
} catch (Throwable $e) {
    FileLogger::channel('verwaltung')->error("[HC] health_checks_table=error msg=" . $e->getMessage());
}

foreach ($customers as $customer) {
    $customerId = (int)$customer['id'];
    $customerName = (string)$customer['name'];
    $host = (string)$customer['host'];
    $healthCmsUrl = (string)($customer['health_cms_url'] ?? '');
    $healthFrontendUrl = (string)($customer['health_frontend_url'] ?? '');
    $cmsHealthBase = $healthCmsUrl !== '' ? $healthCmsUrl : $host;
    $tokenEnc = (string)$customer['health_token_enc'];
    $tokenNonce = (string)$customer['health_token_nonce'];
    $tokenTag = (string)$customer['health_token_tag'];
    
    // DEBUG: Log customer processing
    if ($debugMode) { FileLogger::channel('verwaltung')->error("[HC] customer_id={$customerId} cms_url={$cmsHealthBase} frontend_url={$healthFrontendUrl}"); }
    
    // Decrypt token
    $token = decryptToken($tokenEnc, $tokenNonce, $tokenTag, $customerId);
    if ($token === null) {
        FileLogger::channel('verwaltung')->error("[HC] decrypt_failed customer_id={$customerId}");
        continue; // Skip if decrypt fails
    }
    
    if ($debugMode) { FileLogger::channel('verwaltung')->error("[HC] decrypt_ok customer_id={$customerId}"); }
    
    // CRITICAL: Get last status BEFORE performing check
    $lastStatus = getLastHealthStatus($pdo, $customerId);
    
    // Perform check
    $result = performCmsHealthCheck($cmsHealthBase, $token);
    $frontendHealth = performFrontendHealthCheck($healthFrontendUrl);
    $result['raw_response'] = array_merge(
        is_array($result['raw_response'] ?? null) ? $result['raw_response'] : [],
        ['frontend_health' => $frontendHealth]
    );
    if ($frontendHealth['checked'] === true && $frontendHealth['ok'] !== true && $result['status'] === 'healthy') {
        $result['status'] = 'degraded';
    }
    $result['response_ms'] = max((int)($result['response_ms'] ?? 0), (int)($frontendHealth['response_ms'] ?? 0));
    $newStatus = $result['status'];
    
    if ($debugMode) { FileLogger::channel('verwaltung')->error("[HC] check_done customer_id={$customerId} status={$newStatus} response_ms={$result['response_ms']}"); }
    
    // Store result
    try {
        storeHealthCheck($pdo, $customerId, $result);
        if ($debugMode) { FileLogger::channel('verwaltung')->error("[HC] insert_ok customer_id={$customerId}"); }
        
        // Synchronisation mit customer_health für Dashboard-Kompatibilität
        $mappedStatus = match($newStatus) {
            'healthy'  => 'online',
            'degraded' => 'degraded',
            default    => 'offline',
        };
        $syncStmt = $pdo->prepare("
            INSERT INTO customer_health (customer_id, status, last_check_at)
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE status = VALUES(status), last_check_at = VALUES(last_check_at)
        ");
        $syncStmt->execute([$customerId, $mappedStatus]);
        if ($debugMode) { FileLogger::channel('verwaltung')->error("[HC] sync_ok customer_id={$customerId}"); }

    } catch (Throwable $e) {
        FileLogger::channel('verwaltung')->error("[HC] insert_failed customer_id={$customerId} err=" . $e->getMessage());
        continue; // Skip on DB error
    }
    
    // Alert logic
    $shouldSendProblem = ($newStatus !== 'healthy') && ($lastStatus === null || $lastStatus === 'healthy');
    $shouldSendRecovery = ($newStatus === 'healthy') && ($lastStatus !== null && $lastStatus !== 'healthy');
    
    if ($shouldSendProblem) {
        $subject = "DIGIWTAL Health Alert: {$customerName} ({$cmsHealthBase}) ist {$newStatus}";
        $body = "Kunde: {$customerName}\n";
        $body .= "CMS Health URL: " . normalizeBaseUrl($cmsHealthBase) . "\n";
        if ($healthFrontendUrl !== '') {
            $body .= "Frontend URL: " . normalizeBaseUrl($healthFrontendUrl) . "\n";
        }
        $body .= "Status: {$newStatus}\n";
        $body .= "Geprüft: " . date('Y-m-d H:i:s') . "\n";
        $body .= "Response Time: {$result['response_ms']}ms\n";
        $body .= "CMS: {$result['cms_version']}\n";
        $body .= "Frontend: " . ($result['frontend_version'] ?? 'n/a') . "\n";
        $body .= "PHP: {$result['php_version']}\n";
        $body .= "\nDetails:\n" . json_encode($result['raw_response'], JSON_PRETTY_PRINT) . "\n";
        
        sendMail($adminEmail, $subject, $body);

        if ($pushService->isConfigured()) {
            $pushService->sendToAll(
                $pushRepo->listAll(),
                "⚠️ {$customerName} ist {$newStatus}",
                "CMS: " . normalizeBaseUrl($cmsHealthBase) . " | Response: {$result['response_ms']}ms",
                '/admin/dashboard',
                'health-alert-' . $customerId
            );
        }
    }
    
    if ($shouldSendRecovery) {
        $outageStart = getOutageStart($pdo, $customerId);
        $outageDuration = 'unbekannt';
        
        if ($outageStart) {
            $start = new DateTime($outageStart);
            $end = new DateTime();
            $diff = $start->diff($end);
            $minutes = ($diff->days * 1440) + ($diff->h * 66) + $diff->i;
            $outageDuration = $minutes . ' Minuten';
        }
        
        $subject = "DIGIWTAL Recovery: {$customerName} wieder healthy – Ausfall {$outageDuration}";
        $body = "Kunde: {$customerName}\n";
        $body .= "CMS Health URL: " . normalizeBaseUrl($cmsHealthBase) . "\n";
        $body .= "Status: healthy (wiederhergestellt)\n";
        $body .= "Wiederhergestellt: " . date('Y-m-d H:i:s') . "\n";
        $body .= "Ausfallzeit: {$outageDuration}\n";
        $body .= "CMS: {$result['cms_version']}\n";
        
        sendMail($adminEmail, $subject, $body);

        if ($pushService->isConfigured()) {
            $pushService->sendToAll(
                $pushRepo->listAll(),
                "✅ {$customerName} wieder online",
                "Ausfall: {$outageDuration}",
                '/admin/dashboard',
                'health-recovery-' . $customerId
            );
        }
    }
}

if ($debugMode) { FileLogger::channel('verwaltung')->error("[HC] script_complete"); }
exit(0);
