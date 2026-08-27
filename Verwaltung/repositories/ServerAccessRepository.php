<?php
declare(strict_types=1);

class ServerAccessRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findByCustomer(int $customerId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, customer_id, label, host, port, protocol, username, server_path, html_path,
                    db_host, db_port, db_name, db_user, cms_admin_email, site_name, canonical_base,
                    health_cms_url, health_frontend_url,
                    created_at, updated_at
             FROM server_access WHERE customer_id = ? LIMIT 1'
        );
        $stmt->execute([$customerId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    public function findEncrypted(int $customerId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT health_token_enc, health_token_nonce, health_token_tag,
                    deploy_token_enc, deploy_token_nonce, deploy_token_tag,
                    password_enc, password_nonce, password_tag,
                    private_key_enc, private_key_nonce, private_key_tag,
                    db_password_enc, db_password_nonce, db_password_tag,
                    cms_admin_password_enc, cms_admin_password_nonce, cms_admin_password_tag
             FROM server_access WHERE customer_id = ? LIMIT 1'
        );
        $stmt->execute([$customerId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /**
     * Prueft genau die Angaben, die der zentrale GitHub-Rollout benoetigt.
     * Geheimnisse werden dabei nicht entschluesselt; vollstaendige
     * Ciphertext-/Nonce-/Tag-Saetze genuegen fuer die Vorpruefung.
     *
     * @return array{ready:bool,missing:list<string>}
     */
    public function rolloutReadiness(int $customerId): array
    {
        $access = $this->findByCustomer($customerId);
        $encrypted = $this->findEncrypted($customerId);

        if ($access === null || $encrypted === null) {
            return ['ready' => false, 'missing' => ['Serverzugang']];
        }

        $missing = [];
        $required = [
            'host'                => 'SFTP-Host',
            'username'            => 'Benutzername',
            'server_path'         => 'CMS-Zielpfad',
            'html_path'           => 'Frontend-Zielpfad',
            'health_cms_url'      => 'CMS-URL',
            'health_frontend_url' => 'Frontend-URL',
        ];
        foreach ($required as $key => $label) {
            if (trim((string)($access[$key] ?? '')) === '') {
                $missing[] = $label;
            }
        }

        foreach (['server_path' => 'CMS-Zielpfad', 'html_path' => 'Frontend-Zielpfad'] as $key => $label) {
            $path = rtrim(trim((string)($access[$key] ?? '')), '/');
            if (in_array($path, ['', '/CMS', '/Frontend'], true) && !in_array($label, $missing, true)) {
                $missing[] = $label . ' ist nicht sicher';
            }
        }

        foreach (['password' => 'Server-Passwort', 'health_token' => 'Health-Token'] as $prefix => $label) {
            foreach (['_enc', '_nonce', '_tag'] as $suffix) {
                if (trim((string)($encrypted[$prefix . $suffix] ?? '')) === '') {
                    $missing[] = $label;
                    break;
                }
            }
        }

        return ['ready' => $missing === [], 'missing' => $missing];
    }

    public function upsert(int $customerId, array $data, array $encrypted = []): void
    {
        $existing = $this->findByCustomer($customerId);

        if ($existing === null) {
            $columns = [
                'customer_id', 'label', 'host', 'port', 'protocol', 'username', 'server_path', 'html_path',
                'db_host', 'db_port', 'db_name', 'db_user', 'cms_admin_email', 'site_name', 'canonical_base',
                'health_cms_url', 'health_frontend_url'
            ];
            $params = [
                $customerId,
                $data['label'],
                $data['host'],
                $data['port'],
                $data['protocol'],
                $data['username'],
                $data['server_path'],
                $data['html_path'],
                $data['db_host'],
                $data['db_port'],
                $data['db_name'],
                $data['db_user'],
                $data['cms_admin_email'],
                $data['site_name'],
                $data['canonical_base'],
                $data['health_cms_url'],
                $data['health_frontend_url'],
            ];

            foreach ([
                'health_token_enc' => ['health_token_nonce', 'health_token_tag'],
                'deploy_token_enc' => ['deploy_token_nonce', 'deploy_token_tag'],
                'password_enc' => ['password_nonce', 'password_tag'],
                'db_password_enc' => ['db_password_nonce', 'db_password_tag'],
                'cms_admin_password_enc' => ['cms_admin_password_nonce', 'cms_admin_password_tag'],
            ] as $cipherKey => $extraKeys) {
                if (!empty($encrypted[$cipherKey])) {
                    $columns[] = $cipherKey;
                    $params[] = $encrypted[$cipherKey];
                    foreach ($extraKeys as $key) {
                        $columns[] = $key;
                        $params[] = $encrypted[$key];
                    }
                }
            }

            $sql = 'INSERT INTO server_access (' . implode(', ', $columns) . ') VALUES ('
                . rtrim(str_repeat('?, ', count($params)), ', ') . ')';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        } else {
            $sql = 'UPDATE server_access SET
                        label = ?, host = ?, port = ?, protocol = ?, username = ?, server_path = ?, html_path = ?,
                        db_host = ?, db_port = ?, db_name = ?, db_user = ?, cms_admin_email = ?, site_name = ?, canonical_base = ?,
                        health_cms_url = ?, health_frontend_url = ?';
            $params = [
                $data['label'],
                $data['host'],
                $data['port'],
                $data['protocol'],
                $data['username'],
                $data['server_path'],
                $data['html_path'],
                $data['db_host'],
                $data['db_port'],
                $data['db_name'],
                $data['db_user'],
                $data['cms_admin_email'],
                $data['site_name'],
                $data['canonical_base'],
                $data['health_cms_url'],
                $data['health_frontend_url'],
            ];

            foreach ([
                'health_token_enc' => ['health_token_nonce', 'health_token_tag'],
                'deploy_token_enc' => ['deploy_token_nonce', 'deploy_token_tag'],
                'password_enc' => ['password_nonce', 'password_tag'],
                'db_password_enc' => ['db_password_nonce', 'db_password_tag'],
                'cms_admin_password_enc' => ['cms_admin_password_nonce', 'cms_admin_password_tag'],
            ] as $cipherKey => $extraKeys) {
                if (!empty($encrypted[$cipherKey])) {
                    $sql .= ', ' . $cipherKey . ' = ?';
                    $params[] = $encrypted[$cipherKey];
                    foreach ($extraKeys as $key) {
                        $sql .= ', ' . $key . ' = ?';
                        $params[] = $encrypted[$key];
                    }
                }
            }

            $sql .= ' WHERE customer_id = ?';
            $params[] = $customerId;

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        }
    }

    public function listActiveForHealthCheck(): array
    {
        $stmt = $this->pdo->query("
            SELECT c.id, c.name, sa.host, sa.health_cms_url, sa.health_frontend_url,
                   sa.health_token_enc, sa.health_token_nonce, sa.health_token_tag
            FROM customers c
            INNER JOIN server_access sa ON c.id = sa.customer_id
            WHERE c.abo_status = 'active'
              AND c.is_active = 1
              AND (c.abo_active_until IS NULL OR c.abo_active_until >= CURRENT_DATE)
              AND (sa.health_cms_url != '' OR sa.host != '')
              AND sa.health_token_enc != ''
            ORDER BY c.id ASC
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    }
}
