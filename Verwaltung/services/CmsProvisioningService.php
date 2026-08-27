<?php
declare(strict_types=1);

class CmsProvisioningService
{
    /**
     * Erzeugt die .env der CMS-Instanz.
     *
     * Frueher standen hier nur APP_ENV, die DB-Werte und optional SETUP_TOKEN.
     * Damit blieben HEALTH_TOKEN, MIGRATION_TOKEN und DEPLOY_TOKEN leer - und
     * eine leere Erwartung heisst im CMS "Zugriff verboten". Der Health-Endpunkt
     * antwortete auf jede Anfrage mit 403, die Verwaltung konnte die von ihr
     * selbst aufgesetzten Instanzen also nicht ueberwachen.
     *
     * @param array<string,string> $tokens Schluessel: setup, migration, health, deploy
     */
    public function buildEnvContent(
        array $access,
        string $dbPassword,
        array $tokens = [],
        ?string $cmsBaseUrl = null,
        ?string $updatesAvailableUntil = null
    ): string {
        $lines = [
            $this->envLine('APP_ENV', 'production'),
            $this->envLine('DB_HOST', (string)($access['db_host'] ?? '')),
            $this->envLine('DB_PORT', (string)($access['db_port'] ?? 3306)),
            $this->envLine('DB_NAME', (string)($access['db_name'] ?? '')),
            $this->envLine('DB_USER', (string)($access['db_user'] ?? '')),
            $this->envLine('DB_PASS', $dbPassword),
        ];

        foreach (['SETUP_TOKEN' => 'setup', 'MIGRATION_TOKEN' => 'migration',
                  'HEALTH_TOKEN' => 'health', 'DEPLOY_TOKEN' => 'deploy'] as $envKey => $tokenKey) {
            $value = trim((string)($tokens[$tokenKey] ?? ''));
            if ($value !== '') {
                $lines[] = $this->envLine($envKey, $value);
            }
        }

        // Basis-Pfad: kein eigenes Feld noetig, er steckt bereits im Pfadteil der
        // CMS-URL. Liegt das CMS unter https://kunde.de/verwaltung, ist der
        // Basis-Pfad /verwaltung; bei eigener Subdomain bleibt er leer.
        $lines[] = $this->envLine('CMS_BASE_PATH', self::basePathFromUrl($cmsBaseUrl));
        $lines[] = $this->envLine(
            'UPDATES_AVAILABLE_UNTIL',
            trim((string)$updatesAvailableUntil) !== '' ? (string)$updatesAvailableUntil : 'unlimited'
        );

        return implode("\n", $lines) . "\n";
    }

    /**
     * Erzeugt die .env des Frontends.
     *
     * Wurde vorher gar nicht geschrieben: der Ordner wurde hochgeladen, blieb
     * aber ohne CMS_API_URL - das CMS stand danach, die Website nicht.
     */
    public function buildFrontendEnvContent(?string $cmsBaseUrl, ?string $frontendBaseUrl): string
    {
        $cms = $cmsBaseUrl !== null ? rtrim($cmsBaseUrl, '/') : '';

        $lines = [
            $this->envLine('CMS_API_URL', $cms !== '' ? $cms . '/api.php/api/v1' : ''),
            $this->envLine('CMS_API_TOKEN', ''),
            $this->envLine('CMS_TIMEOUT', '5'),
            $this->envLine('CMS_CACHE_TTL', '300'),
            $this->envLine('FRONTEND_BASE_URL', $frontendBaseUrl !== null ? rtrim($frontendBaseUrl, '/') : ''),
            $this->envLine('CMS_SITEMAP_URL', $cms !== '' ? $cms . '/sitemap.xml' : ''),
        ];

        return implode("\n", $lines) . "\n";
    }

    /**
     * Sammelt die vier Betriebs-Tokens aus dem Vault.
     *
     * Fuer Setup und Migration gibt es keine eigenen Vault-Felder; beide fallen
     * auf das Deploy-Token zurueck. Das entspricht dem, was resolveSetupToken()
     * ohnehin schon tat. Die Tokens sind damit pro Kunde verschieden, aber nicht
     * pro Zweck - eine getrennte Ablage waere der naechste Schritt.
     *
     * @return array<string,string>
     */
    public function resolveTokens(int $customerId, array $access, array $encrypted): array
    {
        $deploy = $this->decryptOptional($customerId, $encrypted, 'deploy_token');
        $health = $this->decryptOptional($customerId, $encrypted, 'health_token');
        $setup  = $this->resolveSetupToken($customerId, $access, $encrypted) ?? $deploy;

        return [
            'setup'     => $setup,
            'migration' => $deploy,
            'health'    => $health,
            'deploy'    => $deploy,
        ];
    }

    /** Oeffentlicher Zugang zur Basis-URL des CMS (fuer Deploy und .env). */
    public function resolveCmsBaseUrl(array $customer, array $access): ?string
    {
        return $this->baseUrl($customer, $access);
    }

    /** Basis-URL der oeffentlichen Website. */
    public function resolveFrontendBaseUrl(array $customer, array $access): ?string
    {
        foreach (['health_frontend_url', 'canonical_base'] as $key) {
            $value = trim((string)($access[$key] ?? ''));
            if ($value !== '') {
                return rtrim($value, '/');
            }
        }

        $domain = trim((string)($customer['domain'] ?? ''));
        if ($domain === '') {
            return null;
        }
        if (!preg_match('#^https?://#i', $domain)) {
            $domain = 'https://' . $domain;
        }

        return rtrim($domain, '/');
    }

    /** Pfadteil einer URL als Basis-Pfad, '' fuer Domain-Root. */
    public static function basePathFromUrl(?string $url): string
    {
        if ($url === null || trim($url) === '') {
            return '';
        }
        $path = parse_url(trim($url), PHP_URL_PATH);
        if (!is_string($path)) {
            return '';
        }
        $path = rtrim($path, '/');

        return ($path === '' || $path === '/') ? '' : $path;
    }

    private function decryptOptional(int $customerId, array $encrypted, string $prefix): string
    {
        $enc = (string)($encrypted[$prefix . '_enc'] ?? '');
        $nonce = (string)($encrypted[$prefix . '_nonce'] ?? '');
        $tag = (string)($encrypted[$prefix . '_tag'] ?? '');
        if ($enc === '' || $nonce === '' || $tag === '') {
            return '';
        }

        try {
            return trim(VaultCrypto::decrypt($enc, $nonce, $tag, 'cust:' . $customerId));
        } catch (Throwable) {
            return '';
        }
    }

    public function provisionCustomer(array $customer, array $access, array $encrypted, callable $logger): void
    {
        $customerId = (int)($customer['id'] ?? 0);
        $dbPassword = $this->decryptRequired($customerId, $encrypted, 'db_password', 'DB-Passwort fehlt.');
        $adminPassword = $this->decryptRequired($customerId, $encrypted, 'cms_admin_password', 'CMS-Admin-Passwort fehlt.');
        $setupToken = $this->resolveSetupToken($customerId, $access, $encrypted);
        if ($setupToken !== null) {
            $access['setup_token'] = $setupToken;
        }

        $this->validateProvisioningInput($customer, $access);

        if ($this->provisionViaRemoteSetup($customer, $access, $adminPassword, $logger)) {
            return;
        }

        $logger('[PROVISION] Remote-Setup nicht erfolgreich. Versuche direkten DB-Fallback...');
        $this->provisionViaDirectDatabase($access, $dbPassword, $adminPassword, $logger);
    }

    private function validateProvisioningInput(array $customer, array $access): void
    {
        $required = [
            'db_host' => 'DB-Host fehlt.',
            'db_name' => 'DB-Name fehlt.',
            'db_user' => 'DB-Benutzer fehlt.',
            'cms_admin_email' => 'CMS-Admin-E-Mail fehlt.',
        ];

        foreach ($required as $key => $message) {
            if (trim((string)($access[$key] ?? '')) === '') {
                throw new RuntimeException($message);
            }
        }

        $email = (string)($access['cms_admin_email'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('CMS-Admin-E-Mail ist ungültig.');
        }

    }

    private function provisionViaRemoteSetup(array $customer, array $access, string $adminPassword, callable $logger): bool
    {
        if (!function_exists('curl_init')) {
            $logger('[PROVISION] cURL fehlt. Remote-Setup nicht möglich.');
            return false;
        }

        $baseUrl = $this->baseUrl($customer, $access);
        if ($baseUrl === null) {
            $logger('[PROVISION] Keine gültige Basis-URL für Remote-Setup.');
            return false;
        }

        $cookieFile = tempnam(sys_get_temp_dir(), 'cms_setup_cookie_');
        if ($cookieFile === false) {
            $logger('[PROVISION] Cookie-Datei konnte nicht erstellt werden.');
            return false;
        }

        try {
            $logger('[PROVISION] Remote-Setup via ' . $baseUrl . '/setup');

            $setupToken = trim((string)($access['setup_token'] ?? ''));
            if ($setupToken === '') {
                $logger('[PROVISION] Kein Setup-Token vorhanden (SETUP_TOKEN).');
                return false;
            }

            // Die API haengt hinter api.php - genau wie CMS_API_URL im Frontend
            // (".../api.php/api/v1"). Ein Aufruf von /api/setup/init landet beim
            // Admin-Front-Controller index.php und damit auf 404; die
            // Provisionierung fiel deshalb immer in den direkten DB-Weg.
            $init = $this->httpRequest(
                'POST',
                $baseUrl . '/api.php/api/setup/init',
                [],
                $cookieFile,
                ['X-Setup-Token: ' . $setupToken, 'Accept: application/json']
            );
            if ($init['status'] === 404) {
                $logger('[PROVISION] API /api/setup/init nicht verfügbar.');
                return false;
            }
            if ($init['status'] === 409) {
                $logger('[PROVISION] Setup bereits abgeschlossen. Behandle als bereits installiert.');
                return true;
            }
            if ($init['status'] >= 400) {
                $logger('[PROVISION] POST /api/setup/init fehlgeschlagen mit HTTP ' . $init['status']);
                return false;
            }

            $initData = json_decode($init['body'], true);
            $token = is_array($initData) ? trim((string)($initData['csrf_token'] ?? '')) : '';
            if ($token === '') {
                $logger('[PROVISION] /api/setup/init lieferte keinen CSRF-Token.');
                return false;
            }

            $step1 = $this->httpRequest('POST', $baseUrl . '/setup/step1', ['_token' => $token], $cookieFile);
            if ($step1['status'] >= 400) {
                $logger('[PROVISION] POST /setup/step1 fehlgeschlagen mit HTTP ' . $step1['status']);
                return false;
            }

            $step2 = $this->httpRequest('POST', $baseUrl . '/setup/step2', ['_token' => $token], $cookieFile);
            if ($step2['status'] >= 400) {
                $logger('[PROVISION] POST /setup/step2 fehlgeschlagen mit HTTP ' . $step2['status']);
                return false;
            }

            $finish = $this->httpRequest('POST', $baseUrl . '/setup/finish', [
                '_token' => $token,
                'admin_email' => (string)$access['cms_admin_email'],
                'admin_password' => $adminPassword,
                'admin_password_confirm' => $adminPassword,
                'site_name' => (string)($access['site_name'] ?? ''),
                'canonical_base' => (string)($access['canonical_base'] ?? ''),
            ], $cookieFile);

            if ($finish['status'] >= 400) {
                $logger('[PROVISION] Setup-Finish fehlgeschlagen mit HTTP ' . $finish['status']);
                return false;
            }

            // Nachweis statt Vermutung: Die Setup-Schritte antworten auch im
            // Fehlerfall mit 302 - step2 leitet bei fehlgeschlagenen Migrationen
            // auf sich selbst zurueck. Wer nur den Status prueft, meldet Erfolg
            // und hinterlaesst eine leere Installation. /api/setup/init liefert
            // 409, sobald das CMS wirklich installiert ist; alles andere heisst,
            // dass der Assistent nicht durchgelaufen ist.
            $verify = $this->httpRequest(
                'POST',
                $baseUrl . '/api.php/api/setup/init',
                [],
                $cookieFile,
                ['X-Setup-Token: ' . $setupToken, 'Accept: application/json']
            );
            if ($verify['status'] !== 409) {
                $logger('[PROVISION] Setup meldete keinen Fehler, die Installation ist aber nicht abgeschlossen '
                    . '(Pruefung lieferte HTTP ' . $verify['status'] . ' statt 409).');
                return false;
            }

            $logger('[PROVISION] Remote-Setup erfolgreich abgeschlossen und geprueft.');
            return true;
        } catch (Throwable $e) {
            $logger('[PROVISION] Remote-Setup Exception: ' . $e->getMessage());
            return false;
        } finally {
            @unlink($cookieFile);
        }
    }

    private function provisionViaDirectDatabase(array $access, string $dbPassword, string $adminPassword, callable $logger): void
    {
        $this->loadCmsProvisioningDependencies();

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            (string)$access['db_host'],
            (int)($access['db_port'] ?? 3306),
            (string)$access['db_name']
        );

        $pdo = new PDO($dsn, (string)$access['db_user'], $dbPassword, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $migrationsDir = dirname(__DIR__, 2) . '/CMS/migrations';
        $result = \App\Setup\MigrationsRunner::run($pdo, $migrationsDir);
        foreach ($result['log'] ?? [] as $line) {
            $logger('[PROVISION][DB] ' . $line);
        }
        if (!($result['ok'] ?? false)) {
            throw new RuntimeException('DB-Migrationen fehlgeschlagen.');
        }

        $pdo->prepare(
            "INSERT INTO roles (`key`, name) VALUES ('admin', 'Administrator')
             ON DUPLICATE KEY UPDATE name = VALUES(name)"
        )->execute();

        $email = (string)$access['cms_admin_email'];
        $userStmt = $pdo->prepare('SELECT id, username FROM users WHERE email = ? LIMIT 1');
        $userStmt->execute([$email]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);

        $passwordHash = password_hash($adminPassword, PASSWORD_DEFAULT);
        if (!is_string($passwordHash) || $passwordHash === '') {
            throw new RuntimeException('CMS-Admin-Passwort konnte nicht gehasht werden.');
        }

        if (is_array($user)) {
            $userId = (int)$user['id'];
            $pdo->prepare(
                'UPDATE users
                 SET password_hash = ?, enabled = 1, is_deleted = 0, deleted_at = NULL, updated_at = NOW()
                 WHERE id = ?'
            )->execute([$passwordHash, $userId]);
            $logger('[PROVISION][DB] Bestehender CMS-Admin aktualisiert: ' . $email);
        } else {
            $username = $this->generateUniqueUsername($pdo, $email);
            $pdo->prepare(
                'INSERT INTO users (username, password_hash, enabled, created_at, updated_at, is_deleted, name, email)
                 VALUES (?, ?, 1, NOW(), NOW(), 0, ?, ?)'
            )->execute([$username, $passwordHash, $username, $email]);
            $userId = (int)$pdo->lastInsertId();
            $logger('[PROVISION][DB] Neuer CMS-Admin angelegt: ' . $email);
        }

        $roleId = (int)$pdo->query("SELECT id FROM roles WHERE `key` = 'admin' LIMIT 1")->fetchColumn();
        if ($roleId <= 0) {
            throw new RuntimeException('Admin-Rolle im CMS nicht gefunden.');
        }

        $pdo->prepare(
            'INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)'
        )->execute([$userId, $roleId]);

        $this->upsertSetting($pdo, 'site_name', (string)($access['site_name'] ?? ''));
        $this->upsertSetting($pdo, 'seo_canonical_base', (string)($access['canonical_base'] ?? ''));
        $this->upsertSetting($pdo, 'seo_robots_default', 'index,follow');
        $this->upsertSetting($pdo, 'app_installed', '1');

        $logger('[PROVISION][DB] Direkte DB-Provisionierung erfolgreich abgeschlossen.');
    }

    private function loadCmsProvisioningDependencies(): void
    {
        require_once dirname(__DIR__, 2) . '/CMS/app/Setup/MigrationsRunner.php';
    }

    private function generateUniqueUsername(PDO $pdo, string $email): string
    {
        $base = preg_replace('/[^a-z0-9_]/i', '', explode('@', $email)[0] ?? '') ?: 'cms_admin';
        if (strtolower($base) === 'admin') {
            $base = 'cms_admin';
        }

        $username = $base;
        $i = 1;
        while (true) {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
            $stmt->execute([$username]);
            if ($stmt->fetchColumn() === false) {
                return $username;
            }
            $username = $base . '_' . $i;
            $i++;
        }
    }

    private function upsertSetting(PDO $pdo, string $key, string $value): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO site_settings (`key`, `value`)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)'
        );
        $stmt->execute([$key, $value]);
    }

    private function decryptRequired(int $customerId, array $encrypted, string $prefix, string $message): string
    {
        $enc = (string)($encrypted[$prefix . '_enc'] ?? '');
        $nonce = (string)($encrypted[$prefix . '_nonce'] ?? '');
        $tag = (string)($encrypted[$prefix . '_tag'] ?? '');
        if ($enc === '' || $nonce === '' || $tag === '') {
            throw new RuntimeException($message);
        }

        return VaultCrypto::decrypt($enc, $nonce, $tag, 'cust:' . $customerId);
    }

    private function baseUrl(array $customer, array $access): ?string
    {
        $healthCmsUrl = trim((string)($access['health_cms_url'] ?? ''));
        if ($healthCmsUrl !== '') {
            return rtrim($healthCmsUrl, '/');
        }

        $canonical = trim((string)($access['canonical_base'] ?? ''));
        if ($canonical !== '') {
            return rtrim($canonical, '/');
        }

        $domain = trim((string)($customer['domain'] ?? ''));
        if ($domain === '') {
            return null;
        }
        if (!preg_match('#^https?://#i', $domain)) {
            $domain = 'https://' . $domain;
        }

        return rtrim($domain, '/');
    }

    private function httpRequest(string $method, string $url, array $fields, string $cookieFile, array $headers = []): array
    {
        $ch = curl_init();
        $requestHeaders = array_merge([
            'User-Agent: DIGIWTAL-Provisioner/1.0',
        ], $headers);
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_COOKIEJAR => $cookieFile,
            CURLOPT_COOKIEFILE => $cookieFile,
            CURLOPT_HTTPHEADER => $requestHeaders,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
        }

        $body = curl_exec($ch);
        if (!is_string($body)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('HTTP Request fehlgeschlagen: ' . $error);
        }

        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return ['status' => $status, 'body' => $body];
    }

    private function resolveSetupToken(int $customerId, array $access, array $encrypted): ?string
    {
        $fromAccess = trim((string)($access['setup_token'] ?? ''));
        if ($fromAccess !== '') {
            return $fromAccess;
        }

        $deployEnc = (string)($encrypted['deploy_token_enc'] ?? '');
        $deployNonce = (string)($encrypted['deploy_token_nonce'] ?? '');
        $deployTag = (string)($encrypted['deploy_token_tag'] ?? '');
        if ($deployEnc !== '' && $deployNonce !== '' && $deployTag !== '') {
            try {
                $decrypted = VaultCrypto::decrypt($deployEnc, $deployNonce, $deployTag, 'cust:' . $customerId);
                if (trim($decrypted) !== '') {
                    return trim($decrypted);
                }
            } catch (Throwable) {
                // ignore, try env fallback
            }
        }

        $fromEnv = trim((string)(getenv('CMS_SETUP_TOKEN') ?: ''));
        return $fromEnv !== '' ? $fromEnv : null;
    }

    private function envLine(string $key, string $value): string
    {
        $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
        return $key . '="' . $escaped . '"';
    }
}
