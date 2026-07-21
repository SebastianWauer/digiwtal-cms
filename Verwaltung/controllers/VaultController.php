<?php
declare(strict_types=1);

class VaultController
{
    private CustomerRepository $customerRepo;
    private ServerCredentialRepository $vaultRepo;

    public function __construct(CustomerRepository $customerRepo, ServerCredentialRepository $vaultRepo)
    {
        $this->customerRepo = $customerRepo;
        $this->vaultRepo = $vaultRepo;
    }

    public function index(int $customerId): void
    {
        AdminAuth::requireAuth();
        
        $customer = $this->customerRepo->findById($customerId);
        if ($customer === null) {
            http_response_code(404);
            echo '<!DOCTYPE html><html><head><title>404</title></head><body><h1>404 Not Found</h1></body></html>';
            exit;
        }
        
        $entries = $this->vaultRepo->listByCustomer($customerId);
        $success = $_SESSION['flash_success'] ?? null;
        $errors = $_SESSION['flash_errors'] ?? null;
        unset($_SESSION['flash_success'], $_SESSION['flash_errors']);
        
        require __DIR__ . '/../views/vault/index.php';
    }

    public function create(int $customerId): void
    {
        AdminAuth::requireAuth();
        
        $customer = $this->customerRepo->findById($customerId);
        if ($customer === null) {
            http_response_code(404);
            echo '<!DOCTYPE html><html><head><title>404</title></head><body><h1>404 Not Found</h1></body></html>';
            exit;
        }
        
        $errors = $_SESSION['flash_errors'] ?? [];
        $old = $_SESSION['flash_old'] ?? [];
        unset($_SESSION['flash_errors'], $_SESSION['flash_old']);
        
        require __DIR__ . '/../views/vault/create.php';
    }

    public function store(int $customerId): void
    {
        AdminAuth::requireAuth();
        
        if (!Csrf::verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_errors'] = ['CSRF token invalid'];
            header('Location: /admin/customers/' . $customerId . '/vault/create');
            exit;
        }

        $customer = $this->customerRepo->findById($customerId);
        if ($customer === null) {
            http_response_code(404);
            echo '<!DOCTYPE html><html><head><title>404</title></head><body><h1>404 Not Found</h1></body></html>';
            exit;
        }

        $label = trim($_POST['label'] ?? '');
        $host = trim($_POST['host'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $secret = (string)($_POST['secret'] ?? '');

        $errors = [];

        if (strlen($label) > 100) {
            $errors[] = 'Label darf maximal 100 Zeichen lang sein.';
        }

        if (strlen($host) > 255) {
            $errors[] = 'Host darf maximal 255 Zeichen lang sein.';
        }

        if (strlen($username) > 255) {
            $errors[] = 'Username darf maximal 255 Zeichen lang sein.';
        }

        if ($secret === '') {
            $errors[] = 'Secret ist erforderlich.';
        }

        if (!empty($errors)) {
            $_SESSION['flash_errors'] = $errors;
            $_SESSION['flash_old'] = compact('label', 'host', 'username');
            header('Location: /admin/customers/' . $customerId . '/vault/create');
            exit;
        }

        try {
            $aad = 'cust:' . $customerId;
            $enc = VaultCrypto::encrypt($secret, $aad);
            $this->vaultRepo->create($customerId, $label, $host, $username, $enc);
            $_SESSION['flash_success'] = 'Zugangsdaten erfolgreich gespeichert.';
        } catch (Throwable $e) {
            $_SESSION['flash_errors'] = ['Encryption failed: ' . $e->getMessage()];
            $_SESSION['flash_old'] = compact('label', 'host', 'username');
            header('Location: /admin/customers/' . $customerId . '/vault/create');
            exit;
        }

        header('Location: /admin/customers/' . $customerId . '/vault');
        exit;
    }

    public function reveal(int $credId): void
    {
        AdminAuth::requireAuth();
        
        if (!Csrf::verify($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            echo '<!DOCTYPE html><html><head><title>403</title></head><body><h1>403 Forbidden</h1></body></html>';
            exit;
        }

        $meta = $this->vaultRepo->findMeta($credId);
        if ($meta === null) {
            http_response_code(404);
            echo '<!DOCTYPE html><html><head><title>404</title></head><body><h1>404 Not Found</h1></body></html>';
            exit;
        }

        $encrypted = $this->vaultRepo->findEncrypted($credId);
        if ($encrypted === null) {
            http_response_code(500);
            echo '<!DOCTYPE html><html><head><title>500</title></head><body><h1>500 Internal Server Error</h1></body></html>';
            exit;
        }

        try {
            $aad = 'cust:' . (int)$meta['customer_id'];
            $secret = VaultCrypto::decrypt(
                (string)$encrypted['secret_ciphertext'],
                (string)$encrypted['secret_nonce'],
                (string)$encrypted['secret_tag'],
                $aad
            );
        } catch (Throwable $e) {
            http_response_code(500);
            echo '<!DOCTYPE html><html><head><title>500</title></head><body><h1>500 Internal Server Error</h1></body></html>';
            exit;
        }

        require __DIR__ . '/../views/vault/reveal.php';
    }

    public function rotate(int $credId): void
    {
        AdminAuth::requireAuth();
        
        if (!Csrf::verify($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            echo '<!DOCTYPE html><html><head><title>403</title></head><body><h1>403 Forbidden</h1></body></html>';
            exit;
        }

        $meta = $this->vaultRepo->findMeta($credId);
        if ($meta === null) {
            http_response_code(404);
            echo '<!DOCTYPE html><html><head><title>404</title></head><body><h1>404 Not Found</h1></body></html>';
            exit;
        }

        $newSecret = (string)($_POST['secret'] ?? '');
        if ($newSecret === '') {
            http_response_code(400);
            echo '<!DOCTYPE html><html><head><title>400</title></head><body><h1>400 Bad Request</h1></body></html>';
            exit;
        }

        try {
            $aad = 'cust:' . (int)$meta['customer_id'];
            $enc = VaultCrypto::encrypt($newSecret, $aad);
            $this->vaultRepo->updateSecret($credId, $enc);
            $_SESSION['flash_success'] = 'Secret erfolgreich aktualisiert.';
        } catch (Throwable $e) {
            $_SESSION['flash_errors'] = ['Encryption failed: ' . $e->getMessage()];
            header('Location: /admin/customers/' . (int)$meta['customer_id'] . '/vault');
            exit;
        }

        header('Location: /admin/customers/' . (int)$meta['customer_id'] . '/vault');
        exit;
    }

    public function delete(int $credId): void
    {
        AdminAuth::requireAuth();
        
        if (!Csrf::verify($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            echo '<!DOCTYPE html><html><head><title>403</title></head><body><h1>403 Forbidden</h1></body></html>';
            exit;
        }

        $meta = $this->vaultRepo->findMeta($credId);
        if ($meta === null) {
            http_response_code(404);
            echo '<!DOCTYPE html><html><head><title>404</title></head><body><h1>404 Not Found</h1></body></html>';
            exit;
        }

        $customerId = (int)$meta['customer_id'];
        $this->vaultRepo->delete($credId);
        
        $_SESSION['flash_success'] = 'Zugangsdaten gelöscht.';
        header('Location: /admin/customers/' . $customerId . '/vault');
        exit;
    }
}
