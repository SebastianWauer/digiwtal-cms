<?php
declare(strict_types=1);

class CustomerDetailController
{
    public function __construct(
        private CustomerRepository $customerRepo,
        private PDO $pdo
    ) {}

    public function show(int $customerId): void
    {
        AdminAuth::requireAuth();

        $customer = $this->customerRepo->findById($customerId);
        if ($customer === null) {
            http_response_code(404);
            echo '<!DOCTYPE html><html><head><title>404</title></head><body><h1>404 Not Found</h1></body></html>';
            exit;
        }

        $stmt = $this->pdo->prepare(
            'SELECT id, status, checked_at, response_ms, cms_version, php_version, frontend_version, raw_response
             FROM health_checks
             WHERE customer_id = ?
             ORDER BY checked_at DESC
             LIMIT 50'
        );
        $stmt->execute([$customerId]);
        $healthHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($healthHistory)) $healthHistory = [];

        foreach ($healthHistory as &$entry) {
            $raw = json_decode((string)($entry['raw_response'] ?? ''), true);
            $entry['cms_status'] = (string)($entry['status'] ?? 'unknown');
            $entry['frontend_status'] = 'n/a';
            $entry['frontend_response_ms'] = null;

            if (is_array($raw)) {
                $entry['cms_status'] = (string)($raw['status'] ?? $entry['cms_status']);
                $frontendRaw = $raw['frontend_health'] ?? null;
                if (is_array($frontendRaw) && (($frontendRaw['checked'] ?? false) === true)) {
                    $entry['frontend_status'] = (string)($frontendRaw['status'] ?? 'unknown');
                    $entry['frontend_response_ms'] = isset($frontendRaw['response_ms']) ? (int)$frontendRaw['response_ms'] : null;
                }
            }
        }
        unset($entry);

        $latestCheck = !empty($healthHistory) ? $healthHistory[0] : null;

        require __DIR__ . '/../views/customers/show.php';
    }
}
