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
              AND (sa.health_cms_url != '' OR sa.host != '')
              AND sa.health_token_enc != ''
            ORDER BY c.id ASC
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    }
}
