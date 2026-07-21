<?php
declare(strict_types=1);

class ServerAccessController
{
    private CustomerRepository $customerRepo;
    private ServerAccessRepository $accessRepo;

    public function __construct(CustomerRepository $customerRepo, ServerAccessRepository $accessRepo)
    {
        $this->customerRepo = $customerRepo;
        $this->accessRepo = $accessRepo;
    }

    public function show(int $customerId): void
    {
        AdminAuth::requireAuth();
        
        $customer = $this->customerRepo->findById($customerId);
        if ($customer === null) {
            http_response_code(404);
            echo '<!DOCTYPE html><html><head><title>404</title></head><body><h1>404 Not Found</h1></body></html>';
            exit;
        }
        
        $access = $this->accessRepo->findByCustomer($customerId);
        $success = $_SESSION['flash_success'] ?? null;
        $errors = $_SESSION['flash_errors'] ?? null;
        $old = $_SESSION['flash_old'] ?? [];
        unset($_SESSION['flash_success'], $_SESSION['flash_errors'], $_SESSION['flash_old']);
        
        require __DIR__ . '/../views/server_access/index.php';
    }

    public function save(int $customerId): void
    {
        AdminAuth::requireAuth();
        
        if (!Csrf::verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_errors'] = ['CSRF token invalid'];
            header('Location: /admin/customers/' . $customerId . '/access');
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
        $port = (int)($_POST['port'] ?? 22);
        $protocol = (string)($_POST['protocol'] ?? 'ftp');
        $username = trim($_POST['username'] ?? '');
        $basePath = $this->normalizeBasePath((string)($_POST['base_path'] ?? ''));
        if ($basePath === '') {
            $basePath = '/';
        }
        $serverPath = $this->buildTargetPath($basePath, 'CMS');
        $htmlPath = $this->buildTargetPath($basePath, 'Frontend');
        $dbHost = trim($_POST['db_host'] ?? '');
        $dbPort = (int)($_POST['db_port'] ?? 3306);
        $dbName = trim($_POST['db_name'] ?? '');
        $dbUser = trim($_POST['db_user'] ?? '');
        $cmsAdminEmail = trim($_POST['cms_admin_email'] ?? '');
        $siteName = trim($_POST['site_name'] ?? '');
        $canonicalBase = rtrim(trim($_POST['canonical_base'] ?? ''), '/');
        $healthCmsUrl = rtrim(trim($_POST['health_cms_url'] ?? ''), '/');
        $healthFrontendUrl = rtrim(trim($_POST['health_frontend_url'] ?? ''), '/');

        $errors = [];

        if (strlen($label) > 100) {
            $errors[] = 'Label darf maximal 100 Zeichen lang sein.';
        }

        if (strlen($host) > 255) {
            $errors[] = 'Host darf maximal 255 Zeichen lang sein.';
        }

        if (preg_match('#^https?://#i', $host) || str_contains($host, '/')) {
            $errors[] = 'Host darf kein Protokoll oder Slashes enthalten.';
        }

        if ($port < 1 || $port > 65535) {
            $errors[] = 'Port muss zwischen 1 und 65535 liegen.';
        }

        if (!in_array($protocol, ['ssh', 'sftp', 'ftp'], true)) {
            $errors[] = 'Ungültiges Protokoll.';
        }
        if ($dbHost !== '' && (preg_match('#^https?://#i', $dbHost) || str_contains($dbHost, '/'))) {
            $errors[] = 'DB-Host darf kein Protokoll oder Slashes enthalten.';
        }
        if ($dbHost !== '' && ($dbPort < 1 || $dbPort > 65535)) {
            $errors[] = 'DB-Port muss zwischen 1 und 65535 liegen.';
        }
        if ($dbName !== '' && strlen($dbName) > 190) {
            $errors[] = 'DB-Name darf maximal 190 Zeichen lang sein.';
        }
        if ($dbUser !== '' && strlen($dbUser) > 190) {
            $errors[] = 'DB-Benutzer darf maximal 190 Zeichen lang sein.';
        }
        if ($cmsAdminEmail !== '' && !filter_var($cmsAdminEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'CMS-Admin-E-Mail ist ungültig.';
        }
        if ($canonicalBase !== '' && !preg_match('#^https?://#i', $canonicalBase)) {
            $errors[] = 'Canonical-Base muss mit https:// oder http:// beginnen.';
        }
        if ($healthCmsUrl !== '' && !preg_match('#^https?://#i', $healthCmsUrl)) {
            $errors[] = 'Health CMS URL muss mit https:// oder http:// beginnen.';
        }
        if ($healthFrontendUrl !== '' && !preg_match('#^https?://#i', $healthFrontendUrl)) {
            $errors[] = 'Health Frontend URL muss mit https:// oder http:// beginnen.';
        }

        if (!empty($errors)) {
            $_SESSION['flash_errors'] = $errors;
            $_SESSION['flash_old'] = compact(
                'label', 'host', 'port', 'protocol', 'username', 'basePath', 'serverPath', 'htmlPath',
                'dbHost', 'dbPort', 'dbName', 'dbUser', 'cmsAdminEmail', 'siteName', 'canonicalBase',
                'healthCmsUrl', 'healthFrontendUrl'
            );
            header('Location: /admin/customers/' . $customerId . '/access');
            exit;
        }

        $data = [
            'label' => $label,
            'host' => $host,
            'port' => $port,
            'protocol' => $protocol,
            'username' => $username,
            'server_path' => $serverPath,
            'html_path' => $htmlPath,
            'db_host' => $dbHost,
            'db_port' => $dbPort,
            'db_name' => $dbName,
            'db_user' => $dbUser,
            'cms_admin_email' => $cmsAdminEmail,
            'site_name' => $siteName,
            'canonical_base' => $canonicalBase,
            'health_cms_url' => $healthCmsUrl,
            'health_frontend_url' => $healthFrontendUrl,
        ];

        $encrypted = [];
        $aad = 'cust:' . $customerId;

        $healthToken = trim($_POST['health_token'] ?? '');
        if ($healthToken !== '') {
            try {
                $enc = VaultCrypto::encrypt($healthToken, $aad);
                $encrypted['health_token_enc'] = $enc['ciphertext_b64'];
                $encrypted['health_token_nonce'] = $enc['nonce_b64'];
                $encrypted['health_token_tag'] = $enc['tag_b64'];
            } catch (Throwable) {
                $errors[] = 'Fehler beim Verschlüsseln des Health-Tokens.';
            }
        }

        $deployToken = trim($_POST['deploy_token'] ?? '');
        if ($deployToken !== '') {
            try {
                $enc = VaultCrypto::encrypt($deployToken, $aad);
                $encrypted['deploy_token_enc'] = $enc['ciphertext_b64'];
                $encrypted['deploy_token_nonce'] = $enc['nonce_b64'];
                $encrypted['deploy_token_tag'] = $enc['tag_b64'];
            } catch (Throwable) {
                $errors[] = 'Fehler beim Verschlüsseln des Deploy-Tokens.';
            }
        }

        $password = trim($_POST['password'] ?? '');
        if ($password !== '') {
            try {
                $enc = VaultCrypto::encrypt($password, $aad);
                $encrypted['password_enc'] = $enc['ciphertext_b64'];
                $encrypted['password_nonce'] = $enc['nonce_b64'];
                $encrypted['password_tag'] = $enc['tag_b64'];
            } catch (Throwable $e) {
                // ADDED: Detailed logging (no secrets)
                $keySet = (getenv('VAULT_KEY_BASE64') !== false && getenv('VAULT_KEY_BASE64') !== '');
                $keyLen = 0;
                if ($keySet) {
                    $decoded = base64_decode((string)getenv('VAULT_KEY_BASE64'), true);
                    $keyLen = $decoded !== false ? strlen($decoded) : 0;
                }
                $gcmAvailable = in_array('aes-256-gcm', openssl_get_cipher_methods(), true);
                
                FileLogger::channel('verwaltung')->error(sprintf(
                    "[VAULT_ERROR] Password encryption failed: %s | VAULT_KEY set: %s, len: %d | GCM available: %s | PHP: %s | OpenSSL: %s",
                    $e->getMessage(),
                    $keySet ? 'yes' : 'no',
                    $keyLen,
                    $gcmAvailable ? 'yes' : 'no',
                    PHP_VERSION,
                    OPENSSL_VERSION_TEXT ?? 'unknown'
                ));
                
                $errors[] = 'Fehler beim Verschlüsseln des Passworts. (Error-ID: ' . substr(md5($e->getMessage()), 0, 8) . ')';
            }
        }

        $dbPassword = trim($_POST['db_password'] ?? '');
        if ($dbPassword !== '') {
            try {
                $enc = VaultCrypto::encrypt($dbPassword, $aad);
                $encrypted['db_password_enc'] = $enc['ciphertext_b64'];
                $encrypted['db_password_nonce'] = $enc['nonce_b64'];
                $encrypted['db_password_tag'] = $enc['tag_b64'];
            } catch (Throwable) {
                $errors[] = 'Fehler beim Verschlüsseln des DB-Passworts.';
            }
        }

        $cmsAdminPassword = (string)($_POST['cms_admin_password'] ?? '');
        if ($cmsAdminPassword !== '') {
            if (strlen($cmsAdminPassword) < 10) {
                $errors[] = 'CMS-Admin-Passwort muss mindestens 10 Zeichen haben.';
            } else {
                try {
                    $enc = VaultCrypto::encrypt($cmsAdminPassword, $aad);
                    $encrypted['cms_admin_password_enc'] = $enc['ciphertext_b64'];
                    $encrypted['cms_admin_password_nonce'] = $enc['nonce_b64'];
                    $encrypted['cms_admin_password_tag'] = $enc['tag_b64'];
                } catch (Throwable) {
                    $errors[] = 'Fehler beim Verschlüsseln des CMS-Admin-Passworts.';
                }
            }
        }

        if (!empty($errors)) {
            $_SESSION['flash_errors'] = $errors;
            $_SESSION['flash_old'] = compact(
                'label', 'host', 'port', 'protocol', 'username', 'serverPath', 'htmlPath',
                'dbHost', 'dbPort', 'dbName', 'dbUser', 'cmsAdminEmail', 'siteName', 'canonicalBase'
            );
            header('Location: /admin/customers/' . $customerId . '/access');
            exit;
        }

        $this->accessRepo->upsert($customerId, $data, $encrypted);

        $_SESSION['flash_success'] = 'Serverzugang gespeichert.';
        header('Location: /admin/customers/' . $customerId . '/access');
        exit;
    }

    private function normalizeBasePath(string $basePath): string
    {
        $basePath = trim($basePath);
        if ($basePath === '') {
            return '';
        }

        $basePath = '/' . trim($basePath, '/');
        return $basePath === '' ? '/' : $basePath;
    }

    private function buildTargetPath(string $basePath, string $segment): string
    {
        $basePath = $this->normalizeBasePath($basePath);
        if ($basePath === '' || $basePath === '/') {
            return '/' . $segment;
        }

        return $basePath . '/' . $segment;
    }
}
