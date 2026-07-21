<?php
declare(strict_types=1);

class CustomerRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function listAllWithHealth(): array
    {
        $stmt = $this->pdo->query("
            SELECT
                c.id, c.name, c.domain, c.email, c.is_active, c.abo_status,
                c.created_at, c.updated_at,
                COALESCE(hc.status, 'unknown') AS health_status,
                hc.checked_at AS last_check_at,
                (
                    SELECT checked_at
                    FROM health_checks
                    WHERE customer_id = c.id AND status = 'healthy'
                    ORDER BY checked_at DESC
                    LIMIT 1
                ) AS last_successful_health_at,
                hc.cms_version,
                hc.response_ms,
                hc.php_version,
                hc.raw_response,
                (
                    SELECT d.created_at
                    FROM deployments d
                    WHERE d.customer_id = c.id
                    ORDER BY d.created_at DESC
                    LIMIT 1
                ) AS last_deploy_at,
                (
                    SELECT d.status
                    FROM deployments d
                    WHERE d.customer_id = c.id
                    ORDER BY d.created_at DESC
                    LIMIT 1
                ) AS last_deploy_status,
                (
                    SELECT d.type
                    FROM deployments d
                    WHERE d.customer_id = c.id
                    ORDER BY d.created_at DESC
                    LIMIT 1
                ) AS last_deploy_type
            FROM customers c
            LEFT JOIN health_checks hc ON hc.id = (
                SELECT id FROM health_checks
                WHERE customer_id = c.id
                ORDER BY checked_at DESC
                LIMIT 1
            )
            ORDER BY c.is_active DESC, c.name ASC
        ");

        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        if (!is_array($rows)) {
            return [];
        }

        foreach ($rows as &$row) {
            $overall = (string)($row['health_status'] ?? 'unknown');
            $cmsStatus = $overall;
            $frontendStatus = 'n/a';
            $cmsDetail = 'Keine CMS-Details vorhanden.';
            $frontendDetail = 'Kein Frontend-Check konfiguriert.';

            $raw = json_decode((string)($row['raw_response'] ?? ''), true);
            if (is_array($raw)) {
                $cmsRawStatus = (string)($raw['status'] ?? '');
                if ($cmsRawStatus !== '') {
                    $cmsStatus = $cmsRawStatus;
                }
                $cmsDetail = $this->buildCmsHealthDetail($cmsStatus, $raw);

                $frontendRaw = $raw['frontend_health'] ?? null;
                if (is_array($frontendRaw)) {
                    if (($frontendRaw['checked'] ?? false) === true) {
                        $frontendStatus = (string)($frontendRaw['status'] ?? 'unknown');
                        $frontendDetail = $this->buildFrontendHealthDetail($frontendStatus, $frontendRaw);
                    } else {
                        $frontendStatus = 'n/a';
                        $frontendDetail = 'Kein Frontend-Check konfiguriert.';
                    }
                }
            }

            $row['health_cms_status'] = $cmsStatus;
            $row['health_frontend_status'] = $frontendStatus;
            $row['health_cms_detail'] = $cmsDetail;
            $row['health_frontend_detail'] = $frontendDetail;
            unset($row['raw_response']);
        }
        unset($row);

        return $rows;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM customers WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    public function create(
        string $name,
        string $domain,
        string $email     = '',
        string $aboStatus = 'active',
        string $notes     = '',
        int    $isActive  = 1
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO customers (name, domain, email, abo_status, notes, is_active)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$name, $domain, $email, $aboStatus, $notes ?: null, $isActive]);

        $id = (int)$this->pdo->lastInsertId();

        // Initiale Health-Zeile anlegen
        $healthStmt = $this->pdo->prepare(
            'INSERT IGNORE INTO customer_health (customer_id, status) VALUES (?, ?)'
        );
        $healthStmt->execute([$id, 'offline']);

        return $id;
    }

    public function update(
        int    $id,
        string $name,
        string $domain,
        string $email     = '',
        string $aboStatus = 'active',
        string $notes     = ''
    ): void {
        $stmt = $this->pdo->prepare(
            'UPDATE customers
             SET name = ?, domain = ?, email = ?, abo_status = ?, notes = ?
             WHERE id = ?'
        );
        $stmt->execute([$name, $domain, $email, $aboStatus, $notes ?: null, $id]);
    }

    public function toggleActive(int $id, int $isActive): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE customers SET is_active = ? WHERE id = ?'
        );
        $stmt->execute([$isActive, $id]);
    }

    private function buildCmsHealthDetail(string $status, array $raw): string
    {
        $parts = ['CMS: ' . $status];

        if (!empty($raw['cms_version'])) {
            $parts[] = 'CMS-Version: ' . (string)$raw['cms_version'];
        }
        if (!empty($raw['php_version'])) {
            $parts[] = 'PHP: ' . (string)$raw['php_version'];
        }
        if (isset($raw['db_ok'])) {
            $parts[] = 'DB: ' . ((bool)$raw['db_ok'] ? 'ok' : 'fehlerhaft');
        }
        if (isset($raw['storage_writable'])) {
            $parts[] = 'Storage: ' . ((bool)$raw['storage_writable'] ? 'ok' : 'nicht schreibbar');
        }
        if (!empty($raw['error'])) {
            $parts[] = 'Fehler: ' . (string)$raw['error'];
        }
        if (!empty($raw['http_code'])) {
            $parts[] = 'HTTP-Code: ' . (string)$raw['http_code'];
        }
        if (!empty($raw['curl_errno'])) {
            $parts[] = 'cURL errno: ' . (string)$raw['curl_errno'];
        }

        return implode("\n", $parts);
    }

    private function buildFrontendHealthDetail(string $status, array $frontendRaw): string
    {
        $parts = ['Frontend: ' . $status];

        if (!empty($frontendRaw['url'])) {
            $parts[] = 'URL: ' . (string)$frontendRaw['url'];
        }
        if (isset($frontendRaw['response_ms'])) {
            $parts[] = 'Response: ' . (string)$frontendRaw['response_ms'] . 'ms';
        }
        if (!empty($frontendRaw['http_code'])) {
            $parts[] = 'HTTP-Code: ' . (string)$frontendRaw['http_code'];
        }
        if (!empty($frontendRaw['curl_errno'])) {
            $parts[] = 'cURL errno: ' . (string)$frontendRaw['curl_errno'];
        }

        return implode("\n", $parts);
    }
}
