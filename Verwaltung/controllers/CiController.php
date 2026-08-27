<?php
declare(strict_types=1);

/**
 * Schnittstelle fuer die CI-Pipeline.
 *
 * Die Verwaltung kann auf IONOS-Webspace nicht selbst uebertragen - dort fehlen
 * die ssh2-Erweiterung und eine nutzbare Shell, FTP ist geschlossen. Das
 * Ausrollen uebernimmt deshalb GitHub Actions. Damit die Zugangsdaten nicht ein
 * zweites Mal in GitHub gepflegt werden muessen, holt die Pipeline sie hier ab:
 * Die Verwaltung bleibt die einzige Quelle.
 *
 * Der Endpunkt gibt Zugangsdaten im Klartext heraus. Deshalb:
 *  - nur mit gueltigem, widerrufbarem CI-Token (Header X-Ci-Token)
 *  - nur ueber HTTPS
 *  - jeder Abruf landet im Audit-Log, mit Token-Label und IP
 *  - immer nur ein Kunde pro Aufruf
 */
class CiController
{
    public function __construct(
        private CiTokenRepository $ciTokens,
        private CustomerRepository $customerRepo,
        private ServerAccessRepository $accessRepo,
        private SupportTokenRepository $supportTokens,
        private AuditLogger $audit
    ) {}

    /** GET /api/ci/deploy-target?customer=<id> */
    public function deployTarget(): void
    {
        $token = $this->headerToken();
        if ($token === '') {
            $this->json(['ok' => false, 'error' => 'missing_token'], 401);
        }

        if (!$this->isSecure()) {
            $this->json(['ok' => false, 'error' => 'https_required'], 400);
        }

        $matched = $this->ciTokens->findByPlainToken($token);
        if ($matched === null) {
            $this->audit->log('ci.token_rejected', 'ci_token', 0, 'ip: ' . $this->clientIp());
            $this->json(['ok' => false, 'error' => 'invalid_token'], 403);
        }

        $customerId = (int)($_GET['customer'] ?? 0);
        if ($customerId <= 0) {
            $this->json(['ok' => false, 'error' => 'missing_customer'], 400);
        }

        $customer = $this->customerRepo->findById($customerId);
        if ($customer === null || (int)($customer['is_active'] ?? 0) !== 1) {
            $this->json(['ok' => false, 'error' => 'customer_not_found_or_inactive'], 404);
        }

        $access = $this->accessRepo->findByCustomer($customerId);
        $encrypted = $this->accessRepo->findEncrypted($customerId);
        if ($access === null || $encrypted === null) {
            $this->json(['ok' => false, 'error' => 'no_server_access'], 404);
        }

        $aad = 'cust:' . $customerId;
        $secret = function (string $prefix) use ($encrypted, $aad): string {
            $enc = (string)($encrypted[$prefix . '_enc'] ?? '');
            $nonce = (string)($encrypted[$prefix . '_nonce'] ?? '');
            $tag = (string)($encrypted[$prefix . '_tag'] ?? '');
            if ($enc === '' || $nonce === '' || $tag === '') {
                return '';
            }
            try {
                return VaultCrypto::decrypt($enc, $nonce, $tag, $aad);
            } catch (Throwable) {
                return '';
            }
        };

        $cmsUrl = rtrim((string)($access['health_cms_url'] ?? ''), '/');
        $frontendUrl = rtrim((string)($access['health_frontend_url'] ?? ($access['canonical_base'] ?? '')), '/');
        $deployToken = $secret('deploy_token');

        $this->ciTokens->markUsed((int)$matched['id'], $this->clientIp());
        $this->audit->log(
            'ci.deploy_target_read',
            'customer',
            $customerId,
            'token: ' . (string)($matched['label'] ?? '') . ', ip: ' . $this->clientIp()
        );

        $this->json([
            'ok' => true,
            'customer' => [
                'id'   => $customerId,
                'name' => (string)($customer['name'] ?? ''),
            ],
            'ssh' => [
                'host'     => (string)($access['host'] ?? ''),
                // Der Port im Serverzugang beschreibt, wie die Verwaltung selbst
                // uebertragen wuerde. Die Pipeline nutzt immer SSH - steht dort
                // ein FTP-Port, waere er hier falsch.
                'port'     => self::sshPort($access),
                'user'     => (string)($access['username'] ?? ''),
                'password' => $secret('password'),
                // Zielpfade: server_path zeigt auf CMS, html_path auf das Frontend.
                // Fuer rsync wird der gemeinsame Elternpfad gebraucht.
                'cms_path'      => (string)($access['server_path'] ?? '/CMS'),
                'frontend_path' => (string)($access['html_path'] ?? '/Frontend'),
            ],
            'db' => [
                'host'     => (string)($access['db_host'] ?? ''),
                'port'     => (int)($access['db_port'] ?? 3306),
                'name'     => (string)($access['db_name'] ?? ''),
                'user'     => (string)($access['db_user'] ?? ''),
                'password' => $secret('db_password'),
            ],
            'urls' => [
                'cms'       => $cmsUrl,
                'frontend'  => $frontendUrl,
                'base_path' => self::basePathFromUrl($cmsUrl),
            ],
            'tokens' => [
                // Setup und Migration teilen sich das Deploy-Token - so macht es
                // CmsProvisioningService::resolveTokens() auch.
                'health'    => $secret('health_token'),
                'deploy'    => $deployToken,
                'migration' => $deployToken,
                'setup'     => $deployToken,
            ],
            'site' => [
                'name'           => (string)($access['site_name'] ?? ''),
                'canonical_base' => (string)($access['canonical_base'] ?? ''),
                'admin_email'    => (string)($access['cms_admin_email'] ?? ''),
            ],
            // Zugang fuer die Hilfe-Funktion: Damit meldet die Instanz Probleme
            // an die Verwaltung zurueck. Das Token entsteht beim ersten Abruf
            // von selbst - ein Feld, das jemand ausfuellen muesste, waere ein
            // weiteres Feld, das leer bleiben kann.
            'support' => [
                'url'   => self::verwaltungBaseUrl(),
                'token' => (string)($this->supportTokens->ensureFor($customerId) ?? ''),
            ],
        ], 200);
    }

    /** Eigene oeffentliche Adresse - die Instanz muss wissen, wohin sie melden soll. */
    private static function verwaltungBaseUrl(): string
    {
        $host = trim((string)(getenv('ADMIN_HOST') ?: ''));
        if ($host === '') {
            $host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
        }
        if ($host === '') {
            return '';
        }

        return preg_match('#^https?://#i', $host) === 1 ? rtrim($host, '/') : 'https://' . rtrim($host, '/');
    }

    /** SSH-Port aus dem Serverzugang, unabhaengig vom dort gesetzten Protokoll. */
    private static function sshPort(array $access): int
    {
        $protocol = strtolower((string)($access['protocol'] ?? 'sftp'));
        $port = (int)($access['port'] ?? 22);

        if ($protocol === 'ftp' || $port === 21 || $port <= 0) {
            return 22;
        }

        return $port;
    }

    private static function basePathFromUrl(string $url): string
    {
        if (trim($url) === '') {
            return '';
        }
        $path = parse_url(trim($url), PHP_URL_PATH);
        if (!is_string($path)) {
            return '';
        }
        $path = rtrim($path, '/');

        return ($path === '' || $path === '/') ? '' : $path;
    }

    private function headerToken(): string
    {
        $t = trim((string)($_SERVER['HTTP_X_CI_TOKEN'] ?? ''));
        if ($t !== '') {
            return $t;
        }
        $auth = trim((string)($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
        if ($auth === '' && function_exists('getallheaders')) {
            $headers = getallheaders();
            if (is_array($headers)) {
                $auth = trim((string)($headers['Authorization'] ?? $headers['authorization'] ?? ''));
            }
        }
        if (preg_match('/^Bearer\s+(.+)$/i', $auth, $m) === 1) {
            return trim((string)$m[1]);
        }
        return '';
    }

    private function isSecure(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
            return true;
        }
        if (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') {
            return true;
        }
        // Lokale Entwicklung nicht blockieren.
        return in_array((string)($_SERVER['REMOTE_ADDR'] ?? ''), ['127.0.0.1', '::1'], true);
    }

    private function clientIp(): string
    {
        return (string)($_SERVER['REMOTE_ADDR'] ?? '');
    }

    /** @param array<string,mixed> $payload */
    private function json(array $payload, int $status): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
