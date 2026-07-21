<?php
declare(strict_types=1);

class CustomerHealthRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function upsertStatus(int $customerId, string $status, ?string $note = null): void
    {
        $noteVal = $note !== null ? $note : '';
        
        $stmt = $this->pdo->prepare("
            INSERT INTO customer_health (customer_id, status, last_check_at, note)
            VALUES (?, ?, NOW(), ?)
            ON DUPLICATE KEY UPDATE
                status = VALUES(status),
                last_check_at = VALUES(last_check_at),
                note = VALUES(note)
        ");
        
        $stmt->execute([$customerId, $status, $noteVal]);
    }
}
