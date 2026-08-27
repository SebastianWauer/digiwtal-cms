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
        private AuditLogger $audit
    ) {}

    public function index(): void
    {
        AdminAuth::requireAuth();

        $tokens = $this->ciTokens->all();
        $kunden = $this->customerRepo->listAllWithHealth();

        $github = [
            'repo'     => (string)(getenv('GITHUB_REPO') ?: ''),
            'workflow' => (string)(getenv('GITHUB_WORKFLOW') ?: 'deploy-instanz.yml'),
            'branch'   => (string)(getenv('GITHUB_BRANCH') ?: 'main'),
            'token_da' => trim((string)(getenv('GITHUB_TOKEN') ?: '')) !== '',
        ];
        $healthRunUrl = VerwaltungUrl::base() . '/api/ci/health-run';

        $success  = $_SESSION['flash_success'] ?? null;
        $errors   = $_SESSION['flash_errors'] ?? [];
        $newToken = $_SESSION['flash_new_token'] ?? null;
        unset($_SESSION['flash_success'], $_SESSION['flash_errors'], $_SESSION['flash_new_token']);

        require __DIR__ . '/../views/ci/index.php';
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

    /** Startet den Deploy-Workflow auf GitHub fuer einen Kunden. */
    public function dispatch(int $customerId): void
    {
        AdminAuth::requireAuth();
        if (!Csrf::verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_errors'] = ['CSRF token invalid'];
            $this->back();
        }

        $customer = $this->customerRepo->findById($customerId);
        if ($customer === null) {
            $_SESSION['flash_errors'] = ['Kunde nicht gefunden.'];
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

        $body = json_encode([
            'ref' => $branch,
            'inputs' => [
                'kunde'            => (string)$customerId,
                'erstinstallation' => !empty($_POST['erstinstallation']) ? 'true' : 'false',
                'migrationen'      => empty($_POST['keine_migrationen']) ? 'true' : 'false',
            ],
        ], JSON_UNESCAPED_SLASHES);

        $url = 'https://api.github.com/repos/' . $repo . '/actions/workflows/'
             . rawurlencode($workflow) . '/dispatches';

        $ch = curl_init($url);
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
        $status   = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        // 204 = angenommen. GitHub liefert keinen Inhalt zurueck.
        if ($status === 204) {
            $this->audit->log('ci.workflow_dispatched', 'customer', $customerId, 'workflow: ' . $workflow);
            $_SESSION['flash_success'] = 'Rollout fuer "' . (string)($customer['name'] ?? '')
                . '" gestartet. Der Fortschritt steht in GitHub unter Actions.';
        } else {
            $detail = $curlErr !== '' ? $curlErr : substr((string)$response, 0, 300);
            $this->audit->log('ci.workflow_dispatch_failed', 'customer', $customerId, 'HTTP ' . $status);
            $_SESSION['flash_errors'] = ['GitHub antwortete mit HTTP ' . $status . ': ' . $detail];
        }

        $this->back();
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
