<?php
declare(strict_types=1);

class CustomerController
{
    private CustomerRepository $customerRepo;
    private AuditLogger $audit;

    public function __construct(CustomerRepository $customerRepo, AuditLogger $audit)
    {
        $this->customerRepo = $customerRepo;
        $this->audit = $audit;
    }

    public function index(): void
    {
        AdminAuth::requireAuth();

        $customers = $this->customerRepo->listAllWithHealth();

        foreach ($customers as &$customer) {
            $isActive = (int)($customer['is_active'] ?? 0) === 1;
            $status   = (string)($customer['health_status'] ?? 'unknown');

            if (!$isActive) {
                $customer['ampel'] = 'red';
            } elseif ($status === 'healthy') {
                $customer['ampel'] = 'green';
            } elseif ($status === 'degraded') {
                $customer['ampel'] = 'yellow';
            } else {
                $customer['ampel'] = 'red';
            }
        }
        unset($customer);

        $success = $_SESSION['flash_success'] ?? null;
        $errors  = $_SESSION['flash_errors']  ?? null;
        unset($_SESSION['flash_success'], $_SESSION['flash_errors']);

        require __DIR__ . '/../views/customers/index.php';
    }

    public function create(): void
    {
        AdminAuth::requireAuth();
        $this->requireSuperadmin();

        $errors = $_SESSION['flash_errors'] ?? [];
        $old    = $_SESSION['flash_old']    ?? [];
        unset($_SESSION['flash_errors'], $_SESSION['flash_old']);

        require __DIR__ . '/../views/customers/create.php';
    }

    public function store(): void
    {
        AdminAuth::requireAuth();
        $this->requireSuperadmin();

        if (!Csrf::verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_errors'] = ['CSRF token invalid'];
            header('Location: /admin/customers/create');
            exit;
        }

        $name      = trim($_POST['name']      ?? '');
        $domain    = trim($_POST['domain']    ?? '');
        $email     = trim($_POST['email']     ?? '');
        $aboStatus = (string)($_POST['abo_status'] ?? 'active');
        $notes     = trim($_POST['notes']     ?? '');
        $isActive  = isset($_POST['is_active']) ? 1 : 0;

        $errors = [];

        if (strlen($name) < 2 || strlen($name) > 255) {
            $errors[] = 'Name muss zwischen 2 und 255 Zeichen lang sein.';
        }

        if (strlen($domain) > 255) {
            $errors[] = 'Domain darf maximal 255 Zeichen lang sein.';
        }

        if ($domain !== '' && !preg_match('/^[a-z0-9.-]+$/i', $domain)) {
            $errors[] = 'Domain darf nur a-z, 0-9, Punkt und Bindestrich enthalten.';
        }

        if (preg_match('#^https?://#i', $domain) || str_contains($domain, '/')) {
            $errors[] = 'Domain darf kein Protokoll oder Slashes enthalten.';
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'E-Mail-Adresse ist ungültig.';
        }

        if (!in_array($aboStatus, ['active', 'cancelled', 'suspended'], true)) {
            $aboStatus = 'active';
        }

        if (!empty($errors)) {
            $_SESSION['flash_errors'] = $errors;
            $_SESSION['flash_old']    = compact('name', 'domain', 'email', 'aboStatus', 'notes', 'isActive');
            header('Location: /admin/customers/create');
            exit;
        }

        $newId = $this->customerRepo->create($name, $domain, $email ?? '', $aboStatus ?? 'active', $notes ?? '', $isActive ?? 1);
        $this->audit->log('customer.create', 'customer', $newId, "Name: {$name}");

        $_SESSION['flash_success'] = 'Kunde erfolgreich erstellt.';
        header('Location: /admin/customers');
        exit;
    }

    public function edit(int $id): void
    {        
        AdminAuth::requireAuth();
        $this->requireSuperadmin();

        $customer = $this->customerRepo->findById($id);
        if ($customer === null) {
            http_response_code(404);
            echo '<!DOCTYPE html><html><head><title>404</title></head><body><h1>404 Not Found</h1></body></html>';
            exit;
        }

        $errors = $_SESSION['flash_errors'] ?? [];
        $old = $_SESSION['flash_old'] ?? [];
        unset($_SESSION['flash_errors'], $_SESSION['flash_old']);

        $viewPath = __DIR__ . '/../views/customers/edit.php';
        if (!is_file($viewPath)) {
            throw new RuntimeException("View file not found: customers/edit.php");
        }
        
        require $viewPath;
    }

    public function update(int $id): void
    {
        AdminAuth::requireAuth();
        $this->requireSuperadmin();

        if (!Csrf::verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_errors'] = ['CSRF token invalid'];
            header('Location: /admin/customers/' . $id . '/edit');
            exit;
        }

        $name      = trim($_POST['name']      ?? '');
        $domain    = trim($_POST['domain']    ?? '');
        $email     = trim($_POST['email']     ?? '');
        $aboStatus = (string)($_POST['abo_status'] ?? 'active');
        $notes     = trim($_POST['notes']     ?? '');

        $errors = [];

        if (strlen($name) < 2 || strlen($name) > 255) {
            $errors[] = 'Name muss zwischen 2 und 255 Zeichen lang sein.';
        }

        if (strlen($domain) > 255) {
            $errors[] = 'Domain darf maximal 255 Zeichen lang sein.';
        }

        if ($domain !== '' && !preg_match('/^[a-z0-9.-]+$/i', $domain)) {
            $errors[] = 'Domain darf nur a-z, 0-9, Punkt und Bindestrich enthalten.';
        }

        if (preg_match('#^https?://#i', $domain) || str_contains($domain, '/')) {
            $errors[] = 'Domain darf kein Protokoll oder Slashes enthalten.';
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'E-Mail-Adresse ist ungültig.';
        }

        if (!in_array($aboStatus, ['active', 'cancelled', 'suspended'], true)) {
            $aboStatus = 'active';
        }

        if (!empty($errors)) {
            $_SESSION['flash_errors'] = $errors;
            $_SESSION['flash_old']    = compact('name', 'domain', 'email', 'aboStatus', 'notes');
            header('Location: /admin/customers/' . $id . '/edit');
            exit;
        }

        $this->customerRepo->update($id, $name, $domain, $email ?? '', $aboStatus ?? 'active', $notes ?? '');
        $this->audit->log('customer.update', 'customer', $id, "Name: {$name}");

        $_SESSION['flash_success'] = 'Kunde erfolgreich aktualisiert.';
        header('Location: /admin/customers');
        exit;
    }

    public function toggle(int $id): void
    {
        AdminAuth::requireAuth();
        $this->requireSuperadmin();

        if (!Csrf::verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_errors'] = ['CSRF token invalid'];
            header('Location: /admin/customers');
            exit;
        }

        $customer = $this->customerRepo->findById($id);
        if ($customer === null) {
            header('Location: /admin/customers');
            exit;
        }

        $newActive = ((int)($customer['is_active'] ?? 0) === 1) ? 0 : 1;
        $this->customerRepo->toggleActive($id, $newActive);
        $this->audit->log('customer.toggle', 'customer', $id, 'is_active: ' . $newActive);

        $_SESSION['flash_success'] = 'Kundenstatus geändert.';
        header('Location: /admin/customers');
        exit;
    }

    private function requireSuperadmin(): void
    {
        if (($_SESSION['admin_role'] ?? 'operator') !== 'superadmin') {
            http_response_code(403);
            echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>403</title></head>'
                . '<body><main><h1>403 - Kein Zugriff</h1>'
                . '<p>Nur Superadmins dürfen Kunden anlegen oder verändern.</p>'
                . '<p><a href="/admin/customers">Zurück zur Kundenübersicht</a></p></main></body></html>';
            exit;
        }
    }
}
