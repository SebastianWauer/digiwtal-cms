<?php
declare(strict_types=1);

/**
 * Verwaltung der CI-Tokens und Ausloesen des Deploy-Workflows.
 *
 * Die Pipeline holt sich Zugangsdaten ueber /api/ci/deploy-target. Dafuer
 * braucht sie ein Token - erzeugt und widerrufen wird es hier. Ausserdem laesst
 * sich der Workflow von hier aus starten, damit fuer einen Rollout niemand
 * GitHub oeffnen muss.
 */
class CiTokenController
{
    public function __construct(
        private CiTokenRepository $ciTokens,
        private CustomerRepository $customerRepo,
        private ServerAccessRepository $accessRepo,
        private AuditLogger $audit,
        private HealthMonitor $monitor
    ) {}

    public function index(): void
    {
        AdminAuth::requireAuth();

        $tokens = $this->ciTokens->all();
        $rollout = $this->rolloutOverview();

        $github = [
            'repo'     => (string)(getenv('GITHUB_REPO') ?: ''),
            'workflow' => (string)(getenv('GITHUB_WORKFLOW') ?: 'deploy-instanz.yml'),
            'branch'   => (string)(getenv('GITHUB_BRANCH') ?: 'main'),
            'token_da' => trim((string)(getenv('GITHUB_TOKEN') ?: '')) !== '',
        ];
        $cronScript = realpath(dirname(__DIR__) . '/scripts/health_check.php');
        $cronInfo = [
            'path'       => is_string($cronScript) ? $cronScript : dirname(__DIR__) . '/scripts/health_check.php',
            'exists'     => is_string($cronScript) && is_file($cronScript),
            'executable' => is_string($cronScript) && is_executable($cronScript),
            'php_binary' => PHP_BINARY,
            'php_sapi'   => PHP_SAPI,
        ];
        $success  = $_SESSION['flash_success'] ?? null;
        $errors   = $_SESSION['flash_errors'] ?? [];
        $newToken = $_SESSION['flash_new_token'] ?? null;
        unset($_SESSION['flash_success'], $_SESSION['flash_errors'], $_SESSION['flash_new_token']);

        require __DIR__ . '/../views/ci/index.php';
    }

    /** Fuehrt einen Health-Lauf direkt aus der angemeldeten Verwaltung aus. */
    public function runHealth(): void
    {
        AdminAuth::requireAuth();
        if (!Csrf::verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_errors'] = ['CSRF token invalid'];
            $this->back();
        }

        $recorded = 0;
        $skipped  = [];
        $targets  = $this->monitor->targets();

        foreach ($targets as $target) {
            try {
                $cms = HealthMonitor::probeCms((string)$target['cms_url'], (string)$target['token']);
                $frontend = HealthMonitor::probeFrontend((string)$target['frontend_url']);
                $result = $this->monitor->evaluate($cms, $frontend, 'manual');
                $this->monitor->record((int)$target['id'], (string)$target['name'], $result, 'manual');
                $recorded++;
            } catch (Throwable $e) {
                $customerId = (int)($target['id'] ?? 0);
                FileLogger::channel('verwaltung')->error(
                    '[HC] manual_run_failed customer_id=' . $customerId . ' err=' . $e->getMessage()
                );
                $skipped[] = $customerId;
            }
        }

        try {
            $this->monitor->noteRun('manual', $recorded, 'admin: ' . (string)($_SESSION['admin_email'] ?? ''));
        } catch (Throwable $e) {
            $_SESSION['flash_errors'] = ['Prüflauf beendet, aber das Lebenszeichen konnte nicht gespeichert werden.'];
            $this->back();
        }

        $this->audit->log(
            'health.manual_run',
            'health',
            null,
            'gespeichert: ' . $recorded . ', uebersprungen: ' . count($skipped)
        );

        $_SESSION['flash_success'] = 'Prüflauf abgeschlossen: ' . $recorded . ' Instanz(en) gespeichert.';
        if ($skipped !== []) {
            $_SESSION['flash_errors'] = ['Nicht prüfbare Kunden-IDs: ' . implode(', ', $skipped)];
        }
        $this->back();
    }

    public function store(): void
    {
        AdminAuth::requireAuth();
        if (!Csrf::verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_errors'] = ['CSRF token invalid'];
            $this->back();
        }

        $label = trim((string)($_POST['label'] ?? ''));
        if ($label === '') {
            $label = 'ci';
        }

        $token = $this->ciTokens->create($label);
        $this->audit->log('ci.token_created', 'ci_token', null, 'label: ' . $label);

        $_SESSION['flash_new_token'] = $token;
        $_SESSION['flash_success'] = 'CI-Token angelegt. Er wird nur jetzt einmal angezeigt.';
        $this->back();
    }

    public function revoke(int $id): void
    {
        AdminAuth::requireAuth();
        if (!Csrf::verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_errors'] = ['CSRF token invalid'];
            $this->back();
        }

        $this->ciTokens->revoke($id);
        $this->audit->log('ci.token_revoked', 'ci_token', $id);
        $_SESSION['flash_success'] = 'Token widerrufen.';
        $this->back();
    }

    /** Startet den aktuellen Stand fuer alle berechtigten, fertigen Kunden. */
    public function dispatchAll(): void
    {
        AdminAuth::requireAuth();
        if (!Csrf::verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_errors'] = ['CSRF token invalid'];
            $this->back();
        }

        $repo     = trim((string)(getenv('GITHUB_REPO') ?: ''));
        $token    = trim((string)(getenv('GITHUB_TOKEN') ?: ''));
        $workflow = trim((string)(getenv('GITHUB_WORKFLOW') ?: 'deploy-instanz.yml'));
        $branch   = trim((string)(getenv('GITHUB_BRANCH') ?: 'main'));

        if ($repo === '' || $token === '') {
            $_SESSION['flash_errors'] = ['GITHUB_REPO oder GITHUB_TOKEN fehlt in der .env der Verwaltung.'];
            $this->back();
        }

        $overview = $this->rolloutOverview();
        $started = [];
        $failed = [];

        foreach ($overview['ready'] as $customer) {
            $customerId = (int)$customer['id'];
            $result = $this->dispatchGitHub($customerId, $repo, $token, $workflow, $branch);
            if ($result['ok']) {
                $started[] = (string)$customer['name'];
                $this->audit->log('ci.workflow_dispatched', 'customer', $customerId, 'workflow: ' . $workflow . ', rollout: all');
                continue;
            }

            $failed[] = (string)$customer['name'] . ': ' . $result['detail'];
            $this->audit->log(
                'ci.workflow_dispatch_failed',
                'customer',
                $customerId,
                'HTTP ' . $result['status'] . ', rollout: all'
            );
        }

        if ($started !== []) {
            $_SESSION['flash_success'] = 'Rollout für ' . count($started) . ' Kunde(n) gestartet: '
                . implode(', ', $started) . '. Der Fortschritt steht in GitHub unter Actions.';
        }
        if ($failed !== []) {
            $_SESSION['flash_errors'] = array_map(
                static fn(string $message): string => 'Rollout konnte nicht gestartet werden: ' . $message,
                $failed
            );
        } elseif ($started === []) {
            $_SESSION['flash_errors'] = ['Kein Kunde erfüllt aktuell alle Voraussetzungen für den Rollout.'];
        }

        $this->audit->log(
            'ci.rollout_all',
            'customer',
            null,
            'gestartet: ' . count($started) . ', fehlgeschlagen: ' . count($failed)
                . ', uebersprungen: ' . count($overview['skipped'])
        );

        $this->back();
    }

    /** @return array{ready:list<array<string,mixed>>,skipped:list<array<string,mixed>>} */
    private function rolloutOverview(): array
    {
        $ready = [];
        $skipped = [];

        foreach ($this->customerRepo->listAllWithHealth() as $customer) {
            $reason = null;
            if ((int)($customer['is_active'] ?? 0) !== 1) {
                $reason = 'Kunde ist deaktiviert';
            } elseif ((string)($customer['abo_status'] ?? '') !== 'active') {
                $reason = 'Abo-Status ist nicht aktiv';
            } elseif (!CustomerRepository::hasActiveSubscription($customer)) {
                $until = trim((string)($customer['abo_active_until'] ?? ''));
                $reason = $until !== '' ? 'Abo ist seit ' . date('d.m.Y', strtotime($until)) . ' abgelaufen' : 'Abo ist nicht aktiv';
            }

            if ($reason === null) {
                $readiness = $this->accessRepo->rolloutReadiness((int)$customer['id']);
                if (!$readiness['ready']) {
                    $reason = 'Unvollständig: ' . implode(', ', $readiness['missing']);
                }
            }

            if ($reason === null) {
                $ready[] = $customer;
            } else {
                $customer['rollout_skip_reason'] = $reason;
                $skipped[] = $customer;
            }
        }

        return ['ready' => $ready, 'skipped' => $skipped];
    }

    /** @return array{ok:bool,status:int,detail:string} */
    private function dispatchGitHub(
        int $customerId,
        string $repo,
        string $token,
        string $workflow,
        string $branch
    ): array {
        $body = json_encode([
            'ref' => $branch,
            'inputs' => [
                'kunde'            => (string)$customerId,
                'erstinstallation' => 'false',
                'migrationen'      => 'true',
            ],
        ], JSON_UNESCAPED_SLASHES);

        $url = 'https://api.github.com/repos/' . $repo . '/actions/workflows/'
            . rawurlencode($workflow) . '/dispatches';
        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'status' => 0, 'detail' => 'GitHub-Verbindung konnte nicht initialisiert werden.'];
        }

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/vnd.github+json',
                'Authorization: Bearer ' . $token,
                'X-GitHub-Api-Version: 2022-11-28',
                'Content-Type: application/json',
                'User-Agent: Digiwtal-Verwaltung',
            ],
        ]);
        $response = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($status === 204) {
            return ['ok' => true, 'status' => $status, 'detail' => ''];
        }

        $detail = $curlError !== '' ? $curlError : trim(substr((string)$response, 0, 300));
        return [
            'ok' => false,
            'status' => $status,
            'detail' => 'GitHub HTTP ' . $status . ($detail !== '' ? ': ' . $detail : ''),
        ];
    }

    private function back(): void
    {
        $ziel = (string)($_SERVER['HTTP_REFERER'] ?? '/admin/ci-tokens');
        // Nur interne Ziele zulassen
        if ($ziel === '' || !str_starts_with($ziel, '/')) {
            $parts = parse_url($ziel);
            $ziel = (string)($parts['path'] ?? '/admin/ci-tokens');
        }
        header('Location: ' . $ziel);
        exit;
    }
}
