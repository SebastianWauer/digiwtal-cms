<?php
declare(strict_types=1);

class CustomerModuleRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function listByCustomer(int $customerId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                m.id,
                m.key_name,
                m.display_name,
                m.description,
                COALESCE(cm.is_enabled, 0) AS is_enabled,
                cm.expires_at,
                cm.updated_at
            FROM modules m
            LEFT JOIN customer_modules cm ON m.id = cm.module_id AND cm.customer_id = ?
            ORDER BY m.display_name ASC
        ");
        $stmt->execute([$customerId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    }

    public function listForCustomer(int $customerId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT cm.module_id, cm.is_enabled, cm.created_at
             FROM customer_modules cm
             WHERE cm.customer_id = ? AND cm.is_enabled = 1
             ORDER BY cm.module_id ASC'
        );
        $stmt->execute([$customerId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    }

    public function setStatus(int $customerId, int $moduleId, int $enabled, ?string $expiresAt = null): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO customer_modules (customer_id, module_id, is_enabled, expires_at) 
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                is_enabled = VALUES(is_enabled),
                expires_at = VALUES(expires_at)'
        );
        $stmt->execute([$customerId, $moduleId, $enabled, $expiresAt]);
    }

    public function ensureRelation(int $customerId, int $moduleId): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT IGNORE INTO customer_modules (customer_id, module_id, is_enabled) VALUES (?, ?, 0)'
        );
        $stmt->execute([$customerId, $moduleId]);
    }
}
