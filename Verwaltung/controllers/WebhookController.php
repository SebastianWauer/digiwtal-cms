<?php
declare(strict_types=1);

class WebhookController
{
    public function __construct(
        private WebhookTokenRepository $webhookRepo,
        private DeploymentRepository $deploymentRepo,
        private DeployService $deployService,
        private CustomerRepository $customerRepo,
        private AuditLogger $audit
    ) {}

    public function trigger(): void
    {
        $rawToken = trim((string)($_SERVER['HTTP_X_WEBHOOK_TOKEN'] ?? ''));
        if ($rawToken === '') {
            $this->json(['error' => 'Missing X-Webhook-Token header'], 401);
            return;
        }

        // One-time backfill for legacy rows without token_hash.
        $this->webhookRepo->backfillMissingTokenHashes();

        $tokenHash = hash('sha256', $rawToken);
        $matched = $this->webhookRepo->findByTokenHash($tokenHash);

        if ($matched === null) {
            $this->json(['error' => 'Invalid token'], 403);
            return;
        }

        $customerId = (int)$matched['customer_id'];
        $deployType = (string)($matched['deploy_type'] ?? 'cms');
        $customer = $this->customerRepo->findById($customerId);

        if ($customer === null || (int)($customer['is_active'] ?? 0) !== 1) {
            $this->json(['error' => 'Customer not found or inactive'], 404);
            return;
        }

        foreach ($this->deploymentRepo->listRunning() as $dep) {
            if ((int)($dep['customer_id'] ?? 0) === $customerId) {
                $this->json(['error' => 'Deployment already running'], 409);
                return;
            }
        }

        $deploymentId = $this->deploymentRepo->create(
            $customerId,
            $deployType,
            'webhook_token:' . (int)$matched['id']
        );

        try {
            $queued = $this->enqueueDeployWorker('run', $deploymentId, $customerId, $deployType);
            $this->webhookRepo->updateLastUsed((int)$matched['id']);

            if (!$queued) {
                $this->deploymentRepo->markFinished($deploymentId, 'failed');
                $this->json(['error' => 'Deploy queue failed', 'deployment_id' => $deploymentId], 500);
                return;
            }

            $this->audit->log(
                'webhook.deploy',
                'deployment',
                $deploymentId,
                "customer_id: {$customerId}, type: {$deployType}, token_label: " . (string)($matched['label'] ?? '')
            );

            $this->json([
                'ok' => true,
                'queued' => true,
                'deployment_id' => $deploymentId,
                'customer_id' => $customerId,
                'type' => $deployType,
            ], 202);
        } catch (Throwable $e) {
            FileLogger::channel('verwaltung')->error('[WEBHOOK] Deploy failed: ' . $e->getMessage());
            $this->json(['error' => 'Deploy failed', 'deployment_id' => $deploymentId], 500);
        }
    }

    private function enqueueDeployWorker(string $mode, int $deploymentId, int $customerId, string $type): bool
    {
        $workerPath = dirname(__DIR__) . '/scripts/deploy_worker.php';
        if (!is_file($workerPath)) {
            FileLogger::channel('verwaltung')->error('[WEBHOOK] Worker-Script fehlt: ' . $workerPath);
            return false;
        }

        $cmd = 'php '
            . escapeshellarg($workerPath)
            . ' --mode=' . escapeshellarg($mode)
            . ' --deployment-id=' . (int)$deploymentId
            . ' --customer-id=' . (int)$customerId
            . ' --type=' . escapeshellarg($type)
            . ' > /dev/null 2>&1 &';

        exec($cmd, $output, $exitCode);
        if ($exitCode !== 0) {
            FileLogger::channel('verwaltung')->error('[WEBHOOK] Worker konnte nicht gestartet werden. exit=' . $exitCode);
            return false;
        }

        return true;
    }

    private function json(array $payload, int $status): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($payload);
    }
}
