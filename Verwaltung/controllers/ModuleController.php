<?php
declare(strict_types=1);

class ModuleController
{
    private ModuleRepository $moduleRepo;
    private CustomerRepository $customerRepo;
    private CustomerModuleRepository $customerModuleRepo;

    public function __construct(
        ModuleRepository $moduleRepo,
        CustomerRepository $customerRepo,
        CustomerModuleRepository $customerModuleRepo
    ) {
        $this->moduleRepo = $moduleRepo;
        $this->customerRepo = $customerRepo;
        $this->customerModuleRepo = $customerModuleRepo;
    }

    public function index(): void
    {
        AdminAuth::requireAuth();
        
        $modules = $this->moduleRepo->listAll();
        
        require __DIR__ . '/../views/modules/index.php';
    }

    public function customerModules(int $customerId): void
    {
        AdminAuth::requireAuth();
        
        $customer = $this->customerRepo->findById($customerId);
        if ($customer === null) {
            http_response_code(404);
            echo '<!DOCTYPE html><html><head><title>404</title></head><body><h1>404 Not Found</h1></body></html>';
            exit;
        }
        
        $modules = $this->customerModuleRepo->listByCustomer($customerId);
        $success = $_SESSION['flash_success'] ?? null;
        $errors = $_SESSION['flash_errors'] ?? null;
        unset($_SESSION['flash_success'], $_SESSION['flash_errors']);
        
        require __DIR__ . '/../views/customer_modules/index.php';
    }

    public function toggleModule(int $customerId, int $moduleId): void
    {
        AdminAuth::requireAuth();
        
        if (!Csrf::verify($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            echo '<!DOCTYPE html><html><head><title>403</title></head><body><h1>403 Forbidden</h1></body></html>';
            exit;
        }

        $customer = $this->customerRepo->findById($customerId);
        if ($customer === null) {
            http_response_code(404);
            echo '<!DOCTYPE html><html><head><title>404</title></head><body><h1>404 Not Found</h1></body></html>';
            exit;
        }

        $module = $this->moduleRepo->findById($moduleId);
        if ($module === null) {
            http_response_code(404);
            echo '<!DOCTYPE html><html><head><title>404</title></head><body><h1>404 Not Found</h1></body></html>';
            exit;
        }

        $isEnabled = isset($_POST['is_enabled']) ? 1 : 0;
        $expiresAt = trim($_POST['expires_at'] ?? '');
        $expiresAtVal = null;

        if ($expiresAt !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiresAt)) {
            $expiresAtVal = $expiresAt . ' 23:59:59';
        }

        $this->customerModuleRepo->setStatus($customerId, $moduleId, $isEnabled, $expiresAtVal);
        
        $_SESSION['flash_success'] = 'Modul-Status aktualisiert.';
        header('Location: /admin/customers/' . $customerId . '/modules');
        exit;
    }
}
