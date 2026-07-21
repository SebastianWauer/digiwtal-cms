<?php
declare(strict_types=1);

class AuditController
{
    public function __construct(private PDO $pdo) {}

    public function index(): void
    {
        AdminAuth::requireAuth();

        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 50;
        $offset  = ($page - 1) * $perPage;

        $filterAction = trim((string)($_GET['action'] ?? ''));
        $filterEntity = trim((string)($_GET['entity'] ?? ''));

        $where  = '';
        $params = [];
        $conditions = [];

        if ($filterAction !== '') {
            $conditions[] = 'action LIKE ?';
            $params[]     = '%' . $filterAction . '%';
        }
        if ($filterEntity !== '') {
            $conditions[] = 'entity = ?';
            $params[]     = $filterEntity;
        }
        if (!empty($conditions)) {
            $where = 'WHERE ' . implode(' AND ', $conditions);
        }

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM audit_log {$where}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $stmt = $this->pdo->prepare(
            "SELECT id, admin_email, action, entity, entity_id, detail, ip, created_at
             FROM audit_log {$where}
             ORDER BY created_at DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($entries)) $entries = [];

        $totalPages = max(1, (int)ceil($total / $perPage));

        require __DIR__ . '/../views/audit/index.php';
    }
}
