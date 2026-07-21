<?php
declare(strict_types=1);

class DashboardController
{
    private CustomerRepository $customerRepo;

    public function __construct(CustomerRepository $customerRepo)
    {
        $this->customerRepo = $customerRepo;
    }

    public function index(): void
    {
        AdminAuth::requireAuth();
        
        $customers = $this->customerRepo->listAllWithHealth();
        
        foreach ($customers as &$customer) {
            $isActive = (int)($customer['is_active'] ?? 0) === 1;
            $status = (string)($customer['health_status'] ?? 'unknown');
            $lastSuccessfulHealthAt = (string)($customer['last_successful_health_at'] ?? '');
            $staleHealth = true;

            if ($lastSuccessfulHealthAt !== '') {
                $successfulTs = strtotime($lastSuccessfulHealthAt);
                $staleHealth = $successfulTs === false || (time() - $successfulTs) > 1800;
            }

            $customer['stale_health'] = $staleHealth;
            
            if (!$isActive) {
                $customer['ampel'] = 'red';
            } elseif ($status === 'healthy' && !$staleHealth) {
                $customer['ampel'] = 'green';
            } elseif ($status === 'degraded' || $staleHealth) {
                $customer['ampel'] = 'yellow';
            } else {
                $customer['ampel'] = 'red';
            }
        }
        unset($customer);
        
        require __DIR__ . '/../views/dashboard/index.php';
    }
}
