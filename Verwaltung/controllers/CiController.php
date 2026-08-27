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
        private AuditLogger $audit,
        private HealthMonitor $monitor
    ) {}

    /** GET /api/ci/deploy-target?customer=<id> */
    public function deployTarget(): void
    {
        $matched = $this->authorize();

        $customerId = (int)($_GET['customer'] ?? 0);
        if ($customerId <= 0) {
            $this->json(['ok' => false, 'error' => 'missing_customer'], 400);
        }

        $customer = $this->customerRepo->findById($customerId);
        if ($customer === null || !CustomerRepository::hasActiveSubscription($customer)) {
            $this->json(['ok' => false, 'error' => 'customer_not_found_or_subscription_inactive'], 404);
        }

        $access = $this->accessRepo->findByCustomer($customerId);
        $encrypted = $this->accessRepo->findEncrypted($customerId);
        if ($access === null || $encrypted === null) {
            $this->json(['ok' => false, 'error' => 'no_server_access'], 404);
        }

        $readiness = $this->accessRepo->rolloutReadiness($customerId);
        if (!$readiness['ready']) {
            $this->json([
                'ok' => false,
                'error' => 'incomplete_rollout_configuration',
                'missing' => $readiness['missing'],
            ], 422);
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
                'updates_available_until' => trim((string)($customer['abo_active_until'] ?? '')) ?: 'unlimited',
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
                'embedded'  => self::isPathDeployment($cmsUrl, $frontendUrl),
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
                'url'   => VerwaltungUrl::base(),
                'token' => (string)($this->supportTokens->ensureFor($customerId) ?? ''),
            ],
        ], 200);
    }

    /**
     * GET /api/ci/instances
     *
     * Alle ueberwachbaren Instanzen fuer den geplanten Pruflauf. Die fertige
     * Health-Adresse kommt aus der Verwaltung - so kann in der Pipeline niemand
     * das api.php vergessen, an dem der Cron ein Jahr lang vorbeigelaufen ist.
     */
    public function instances(): void
    {
        $matched = $this->authorize();

        $instances = [];
        foreach ($this->monitor->targets() as $target) {
            $instances[] = [
                'customer'       => $target['id'],
                'name'           => $target['name'],
                'cms_health_url' => HealthMonitor::cmsHealthUrl($target['cms_url']),
                'frontend_url'   => HealthMonitor::normalizeBaseUrl($target['frontend_url']),
                'token'          => $target['token'],
            ];
        }

        $this->audit->log(
            'ci.instances_read',
            'ci_token',
            (int)$matched['id'],
            'instanzen: ' . count($instances) . ', ip: ' . $this->clientIp()
        );

        $this->json(['ok' => true, 'instances' => $instances], 200);
    }

    /**
     * POST /api/ci/health-report
     *
     * Nimmt Messungen der Pipeline entgegen. Bewertet wird hier, nicht dort:
     * HealthMonitor::evaluate() ist die einzige Stelle, an der aus einer
     * Messung ein Status wird - egal ob der Cron gemessen hat oder GitHub.
     */
    public function healthReport(): void
    {
        $matched = $this->authorize();

        $raw = file_get_contents('php://input');
        if (!is_string($raw) || $raw === '' || strlen($raw) > 262144) {
            $this->json(['ok' => false, 'error' => 'invalid_body'], 400);
        }

        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            $this->json(['ok' => false, 'error' => 'invalid_json'], 400);
        }

        $source = (string)($payload['source'] ?? 'ci');
        if (!in_array($source, ['ci', 'rollout'], true)) {
            $this->json(['ok' => false, 'error' => 'invalid_source'], 400);
        }

        $results = $payload['results'] ?? null;
        if (!is_array($results) || $results === []) {
            $this->json(['ok' => false, 'error' => 'no_results'], 400);
        }

        $recorded = [];
        $skipped  = [];

        foreach ($results as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $customerId = (int)($entry['customer'] ?? 0);
            $customer   = $customerId > 0 ? $this->customerRepo->findById($customerId) : null;
            if ($customer === null) {
                $skipped[] = ['customer' => $customerId, 'reason' => 'customer_not_found'];
                continue;
            }

            $cms      = is_array($entry['cms'] ?? null) ? $entry['cms'] : [];
            $frontend = is_array($entry['frontend'] ?? null) ? $entry['frontend'] : ['checked' => false];
            $result   = $this->monitor->evaluate($cms, $frontend, $source);

            try {
                $this->monitor->record($customerId, (string)($customer['name'] ?? ''), $result, $source);
                $recorded[] = ['customer' => $customerId, 'status' => $result['status']];
            } catch (Throwable $e) {
                $skipped[] = ['customer' => $customerId, 'reason' => 'write_failed'];
            }
        }

        // Auch ein Lauf ohne verwertbares Ergebnis ist ein Lebenszeichen der
        // Ueberwachung - sonst meldet das Dashboard spaeter Stillstand, obwohl
        // gemessen wurde.
        $this->monitor->noteRun($source, count($recorded), 'token: ' . (string)($matched['label'] ?? ''));

        $this->audit->log(
            'ci.health_report',
            'ci_token',
            (int)$matched['id'],
            'quelle: ' . $source . ', gespeichert: ' . count($recorded) . ', uebersprungen: ' . count($skipped)
        );

        $this->json(['ok' => true, 'recorded' => $recorded, 'skipped' => $skipped], 200);
    }

    /**
     * POST /api/ci/health-run
     *
     * Fuehrt den Prueflauf direkt in der Verwaltung aus. Der Endpunkt ist fuer
     * Hosting-Cronjobs gedacht, die zwar curl aufrufen koennen, deren
     * PHP-CLI-Pfad aber nicht verlaesslich ist. Authentifiziert wird wie bei
     * allen CI-Endpunkten ueber X-Ci-Token; Geheimnisse stehen damit nicht in
     * der URL oder in Access-Logs.
     */
    public function healthRun(): void
    {
        $matched = $this->authorize();

        $recorded = [];
        $skipped  = [];
        $targets  = $this->monitor->targets();

        foreach ($targets as $target) {
            $customerId = (int)$target['id'];

            try {
                $cms      = HealthMonitor::probeCms((string)$target['cms_url'], (string)$target['token']);
                $frontend = HealthMonitor::probeFrontend((string)$target['frontend_url']);
                $result   = $this->monitor->evaluate($cms, $frontend, 'cron');
                $this->monitor->record($customerId, (string)$target['name'], $result, 'cron');

                $recorded[] = [
                    'customer' => $customerId,
                    'status'   => (string)$result['status'],
                ];
            } catch (Throwable $e) {
                FileLogger::channel('verwaltung')->error(
                    '[HC] http_run_failed customer_id=' . $customerId . ' err=' . $e->getMessage()
                );
                $skipped[] = ['customer' => $customerId, 'reason' => 'check_failed'];
            }
        }

        $this->monitor->noteRun(
            'cron',
            count($recorded),
            'http, token: ' . (string)($matched['label'] ?? '')
        );

        $this->audit->log(
            'ci.health_run',
            'ci_token',
            (int)$matched['id'],
            'ziele: ' . count($targets) . ', gespeichert: ' . count($recorded)
                . ', uebersprungen: ' . count($skipped)
        );

        $this->json([
            'ok'       => true,
            'recorded' => $recorded,
            'skipped'  => $skipped,
        ], 200);
    }

    /**
     * Gemeinsame Eingangskontrolle: gueltiges CI-Token, HTTPS, Spur im Log.
     *
     * @return array<string,mixed> Der Token-Datensatz
     */
    private function authorize(): array
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

        $this->ciTokens->markUsed((int)$matched['id'], $this->clientIp());

        return $matched;
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

    /** CMS und Frontend teilen sich eine Domain, das CMS liegt in einem Unterpfad. */
    private static function isPathDeployment(string $cmsUrl, string $frontendUrl): bool
    {
        $cmsHost = strtolower((string)(parse_url($cmsUrl, PHP_URL_HOST) ?? ''));
        $frontendHost = strtolower((string)(parse_url($frontendUrl, PHP_URL_HOST) ?? ''));
        $basePath = self::basePathFromUrl($cmsUrl);

        return $cmsHost !== ''
            && $frontendHost !== ''
            && $cmsHost === $frontendHost
            && $basePath !== ''
            && preg_match('#^/(?:[A-Za-z0-9._~-]+)(?:/[A-Za-z0-9._~-]+)*$#', $basePath) === 1
            && !in_array('.', explode('/', trim($basePath, '/')), true)
            && !in_array('..', explode('/', trim($basePath, '/')), true);
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
