<?php
declare(strict_types=1);

class WebhookManageController
{
    public function __construct(
        private WebhookTokenRepository $webhookRepo,
        private CustomerRepository $customerRepo,
        private AuditLogger $audit
    ) {}

    public function show(int $customerId): void
    {
        AdminAuth::requireAuth();

        $customer = $this->customerRepo->findById($customerId);
        if ($customer === null) {
            http_response_code(404);
            echo '<h1>404</h1>';
            exit;
        }

        try {
            $tokens = $this->webhookRepo->listByCustomer($customerId);
        } catch (Throwable $e) {
            FileLogger::channel('verwaltung')->error('[WEBHOOK_MANAGE] ' . $e->getMessage());
            $tokens = [];
            $_SESSION['flash_errors'] = ['Webhook-Tokens konnten nicht geladen werden. Bitte SQL-Fehler im Server-Log prüfen.'];
        }
        $success = $_SESSION['flash_success'] ?? null;
        $errors = $_SESSION['flash_errors'] ?? [];
        $newToken = $_SESSION['flash_new_token'] ?? null;
        unset($_SESSION['flash_success'], $_SESSION['flash_errors'], $_SESSION['flash_new_token']);

        require __DIR__ . '/../views/webhooks/index.php';
    }

    public function store(int $customerId): void
    {
        AdminAuth::requireAuth();

        if (!Csrf::verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_errors'] = ['CSRF token invalid'];
            header('Location: /admin/customers/' . $customerId . '/webhooks');
            exit;
        }

        $customer = $this->customerRepo->findById($customerId);
        if ($customer === null) {
            http_response_code(404);
            exit;
        }

        $deployType = in_array($_POST['deploy_type'] ?? '', ['cms', 'frontend', 'full'], true)
            ? (string)$_POST['deploy_type']
            : 'cms';
        $label = substr(trim((string)($_POST['label'] ?? '')), 0, 100);

        $plainToken = bin2hex(random_bytes(32));
        $aad = 'webhook:' . $customerId;

        try {
            $enc = VaultCrypto::encrypt($plainToken, $aad);
        } catch (Throwable $e) {
            $_SESSION['flash_errors'] = ['Fehler beim Verschlüsseln: ' . $e->getMessage()];
            header('Location: /admin/customers/' . $customerId . '/webhooks');
            exit;
        }

        $id = $this->webhookRepo->create(
            $customerId,
            $enc['ciphertext_b64'],
            $enc['nonce_b64'],
            $enc['tag_b64'],
            hash('sha256', $plainToken),
            $deployType,
            $label !== '' ? $label : 'Webhook'
        );

        $this->audit->log(
            'webhook.create',
            'webhook_token',
            $id,
            "customer_id: {$customerId}, type: {$deployType}"
        );

        $_SESSION['flash_new_token'] = $plainToken;
        $_SESSION['flash_success'] = 'Webhook-Token erstellt. Bitte jetzt kopieren - wird nur einmal angezeigt!';
        header('Location: /admin/customers/' . $customerId . '/webhooks');
        exit;
    }

    public function delete(int $tokenId): void
    {
        AdminAuth::requireAuth();

        if (!Csrf::verify($_POST['csrf_token'] ?? '')) {
            header('Location: /admin/dashboard');
            exit;
        }

        $tokens = $this->webhookRepo->listAllEncrypted();
        $customerId = 0;
        foreach ($tokens as $t) {
            if ((int)($t['id'] ?? 0) === $tokenId) {
                $customerId = (int)($t['customer_id'] ?? 0);
                break;
            }
        }

        $this->webhookRepo->delete($tokenId);
        $this->audit->log('webhook.delete', 'webhook_token', $tokenId);

        $_SESSION['flash_success'] = 'Webhook-Token gelöscht.';
        header('Location: /admin/customers/' . $customerId . '/webhooks');
        exit;
    }
}
