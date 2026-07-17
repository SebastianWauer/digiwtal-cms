<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class RolePermissionRepositoryDb implements RolePermissionRepositoryInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Root-Regel:
     * - Ein User mit Systemrolle `admin` hat IMMER jede Permission.
     * - Für alle anderen bleibt die Permission-Prüfung strikt tabellenbasiert.
     */
    public function userHasPermission(int $userId, string $permissionKey): bool
    {
        $userId = (int)$userId;
        $permissionKey = trim($permissionKey);

        if ($userId <= 0 || $permissionKey === '') return false;

        // Systemrolle => Root
        if ($this->userIsSystemAdmin($userId)) {
            return true;
        }

        // Normaler RBAC-Check
        $stmt = $this->pdo->prepare("
            SELECT 1
            FROM user_roles ur
            JOIN roles r ON r.id = ur.role_id
            JOIN role_permissions rp ON rp.role_id = r.id
            JOIN permissions p ON p.id = rp.permission_id
            WHERE ur.user_id = :uid
              AND (r.is_deleted = 0 OR r.is_deleted IS NULL)
              AND p.`key` = :pkey
            LIMIT 1
        ");
        $stmt->execute([
            ':uid'  => $userId,
            ':pkey' => $permissionKey,
        ]);

        return (bool)$stmt->fetchColumn();
    }

    /**
     * Systemadmin = User besitzt Rolle mit key='admin' (und Rolle nicht deleted).
     * Diese Funktion ist absichtlich klein/robust, weil sie Security-kritisch ist.
     */
    private function userIsSystemAdmin(int $userId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT 1
            FROM user_roles ur
            JOIN roles r ON r.id = ur.role_id
            WHERE ur.user_id = :uid
              AND (r.is_deleted = 0 OR r.is_deleted IS NULL)
              AND r.`key` = 'admin'
            LIMIT 1
        ");
        $stmt->execute([':uid' => $userId]);
        return (bool)$stmt->fetchColumn();
    }
}
