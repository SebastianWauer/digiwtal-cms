<?php
declare(strict_types=1);

class DeploymentRepository
{
    public function __construct(private PDO $pdo) {}

    public function create(int $customerId, string $type, string $triggeredBy = 'manual'): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO deployments (customer_id, type, triggered_by)
             VALUES (?, ?, ?)'
        );
        $stmt->execute([$customerId, $type, $triggeredBy]);
        return (int)$this->pdo->lastInsertId();
    }

    public function updateStatus(int $id, string $status, ?string $logAppend = null): void
    {
        if ($logAppend !== null) {
            $stmt = $this->pdo->prepare(
                'UPDATE deployments
                 SET status = ?, log = CONCAT(COALESCE(log, ""), ?)
                 WHERE id = ?'
            );
            $stmt->execute([$status, $logAppend, $id]);
        } else {
            $stmt = $this->pdo->prepare(
                'UPDATE deployments SET status = ? WHERE id = ?'
            );
            $stmt->execute([$status, $id]);
        }
    }

    public function markStarted(int $id): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE deployments SET started_at = NOW() WHERE id = ?'
        );
        $stmt->execute([$id]);
    }

    public function markFinished(int $id, string $status): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE deployments SET finished_at = NOW(), status = ? WHERE id = ?'
        );
        $stmt->execute([$status, $id]);
    }

    public function markStoppedAsFailed(int $id, string $reason): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE deployments
             SET finished_at = NOW(),
                 status = ?,
                 log = CONCAT(COALESCE(log, ""), ?)
             WHERE id = ?'
        );
        $stmt->execute(['failed', "\n" . $reason . "\n", $id]);
    }

    public function listByCustomer(int $customerId, int $limit = 20): array
    {
        $limit = max(1, min($limit, 200));
        $stmt = $this->pdo->prepare(
            'SELECT d.id, d.customer_id, d.type, d.version_from, d.version_to, d.status, d.log,
                    d.triggered_by, d.started_at, d.finished_at, d.created_at,
                    (
                        SELECT b.backup_path
                        FROM deployment_backups b
                        WHERE b.customer_id = d.customer_id
                        ORDER BY b.created_at DESC
                        LIMIT 1
                    ) AS latest_backup_path
             FROM deployments d
             WHERE d.customer_id = ?
             ORDER BY d.created_at DESC
             LIMIT ' . $limit
        );
        $stmt->execute([$customerId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, customer_id, type, version_from, version_to, status, log, triggered_by, started_at, finished_at, created_at
             FROM deployments
             WHERE id = ?
             LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    public function listRunning(): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, customer_id, type, status, created_at, started_at
             FROM deployments
             WHERE status = ?
             ORDER BY created_at DESC'
        );
        $stmt->execute(['running']);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    }

    public function createBackupRecord(int $deploymentId, int $customerId, string $backupPath, int $fileCount): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO deployment_backups (deployment_id, customer_id, backup_path, file_count)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$deploymentId, $customerId, $backupPath, $fileCount]);
        return (int)$this->pdo->lastInsertId();
    }

    public function findLatestBackup(int $customerId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT b.id, b.deployment_id, b.backup_path, b.file_count, b.created_at
             FROM deployment_backups b
             WHERE b.customer_id = ?
             ORDER BY b.created_at DESC
             LIMIT 1'
        );
        $stmt->execute([$customerId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    public function findBackupByDeployment(int $customerId, int $deploymentId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT b.id, b.deployment_id, b.backup_path, b.file_count, b.created_at
             FROM deployment_backups b
             WHERE b.customer_id = ?
               AND b.deployment_id = ?
             ORDER BY b.created_at DESC
             LIMIT 1'
        );
        $stmt->execute([$customerId, $deploymentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }
}
