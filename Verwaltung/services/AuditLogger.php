<?php
declare(strict_types=1);

/**
 * Schreibt Admin-Aktionen in die audit_log-Tabelle.
 * Niemals sensible Daten (Passwörter, Keys) in detail speichern!
 */
class AuditLogger
{
    public function __construct(private PDO $pdo) {}

    public function log(
        string $action,
        string $entity = '',
        ?int $entityId = null,
        string $detail = ''
    ): void {
        try {
            $adminId    = isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null;
            $adminEmail = (string)($_SESSION['admin_email'] ?? '');
            $ip         = $this->currentIp();

            $stmt = $this->pdo->prepare(
                'INSERT INTO audit_log (admin_id, admin_email, action, entity, entity_id, detail, ip)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $adminId,
                $adminEmail,
                $action,
                $entity,
                $entityId,
                $detail !== '' ? $detail : null,
                $ip
            ]);
        } catch (Throwable $e) {
            FileLogger::channel('verwaltung')->error('[AUDIT] Log failed: ' . $e->getMessage());
        }
    }

    private function currentIp(): string
    {
        $forwarded = (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
        if ($forwarded !== '') {
            return trim(explode(',', $forwarded)[0]);
        }
        return (string)($_SERVER['REMOTE_ADDR'] ?? '');
    }
}
