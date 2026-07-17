<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class RoleRepositoryDb implements RoleRepositoryInterface
{
    private const SYSTEM_ROLE_KEY = 'admin';

    public function __construct(private PDO $pdo) {}

    public function isSystemRoleKey(string $key): bool
    {
        return strtolower(trim($key)) === self::SYSTEM_ROLE_KEY;
    }

    public function isSystemRoleId(int $roleId): bool
    {
        if ($roleId <= 0) return false;
        $row = $this->findById($roleId);
        if (!$row) return false;
        return $this->isSystemRoleKey((string)($row['key'] ?? ''));
    }

    /** @return array<int,array> */
    public function listActive(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, `key`, name, is_deleted, deleted_at
            FROM roles
            WHERE (is_deleted = 0 OR is_deleted IS NULL)
              AND `key` <> :sys
            ORDER BY `key` ASC
        ");
        $stmt->execute([':sys' => self::SYSTEM_ROLE_KEY]);
        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    /** @return array<int,array> */
    public function listDeleted(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, `key`, name, is_deleted, deleted_at
            FROM roles
            WHERE is_deleted = 1
              AND `key` <> :sys
            ORDER BY deleted_at DESC, id DESC
        ");
        $stmt->execute([':sys' => self::SYSTEM_ROLE_KEY]);
        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public function countDeleted(): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM roles WHERE is_deleted = 1 AND `key` <> :sys");
        $stmt->execute([':sys' => self::SYSTEM_ROLE_KEY]);
        return (int)$stmt->fetchColumn();
    }

    /** @return array|null */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT id, `key`, name, is_deleted, deleted_at FROM roles WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /** @return array|null */
    public function findByKey(string $key): ?array
    {
        $key = strtolower(trim($key));
        $stmt = $this->pdo->prepare("SELECT id, `key`, name, is_deleted, deleted_at FROM roles WHERE `key` = :k LIMIT 1");
        $stmt->execute([':k' => $key]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    private function countAssignments(int $roleId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM user_roles WHERE role_id = :rid");
        $stmt->execute([':rid' => $roleId]);
        return (int)$stmt->fetchColumn();
    }

    /** @return array{ok:bool,id?:int,flash?:array} */
    public function save(?int $id, string $key, string $name): array
    {
        $key = strtolower(trim($key));
        $name = trim($name);

        // Systemrolle: niemals anlegen oder bearbeiten
        if ($this->isSystemRoleKey($key)) {
            return ['ok' => false, 'flash' => ['type' => 'error', 'msg' => 'Die Systemrolle "admin" darf nicht angelegt oder bearbeitet werden.']];
        }

        if ($key === '' || !preg_match('/^[a-z0-9_-]{2,64}$/', $key)) {
            return ['ok' => false, 'flash' => ['type' => 'error', 'msg' => 'Rollen-Key ist ungültig (2–64, a-z, 0-9, _-).']];
        }
        if ($name === '') {
            return ['ok' => false, 'flash' => ['type' => 'error', 'msg' => 'Rollen-Name darf nicht leer sein.']];
        }

        $existing = $this->findByKey($key);
        if ($existing && (int)($existing['id'] ?? 0) !== (int)($id ?? 0)) {
            return ['ok' => false, 'flash' => ['type' => 'error', 'msg' => 'Rollen-Key ist bereits vergeben.']];
        }

        if ($id && $id > 0) {
            $row = $this->findById($id);
            if (!$row) return ['ok' => false, 'flash' => ['type' => 'error', 'msg' => 'Rolle nicht gefunden.']];

            // Systemrolle: niemals bearbeiten, auch nicht über ID-Bypass
            if ($this->isSystemRoleKey((string)($row['key'] ?? ''))) {
                return ['ok' => false, 'flash' => ['type' => 'error', 'msg' => 'Die Systemrolle "admin" darf nicht bearbeitet werden.']];
            }

            $stmt = $this->pdo->prepare("UPDATE roles SET `key` = :k, name = :n WHERE id = :id");
            $stmt->execute([':k' => $key, ':n' => $name, ':id' => $id]);
            return ['ok' => true, 'id' => $id, 'flash' => ['type' => 'ok', 'msg' => 'Rolle gespeichert.']];
        }

        // Neu anlegen (admin ist schon geblockt)
        $stmt = $this->pdo->prepare("INSERT INTO roles (`key`, name) VALUES (:k, :n)");
        $stmt->execute([':k' => $key, ':n' => $name]);
        $newId = (int)$this->pdo->lastInsertId();

        return ['ok' => true, 'id' => $newId > 0 ? $newId : null, 'flash' => ['type' => 'ok', 'msg' => 'Rolle angelegt.']];
    }

    /** @return array{ok:bool,flash?:array} */
    public function softDelete(int $id): array
    {
        $row = $this->findById($id);
        if (!$row) return ['ok' => false, 'flash' => ['type' => 'error', 'msg' => 'Rolle nicht gefunden.']];

        // Systemrolle: niemals löschen
        if ($this->isSystemRoleKey((string)($row['key'] ?? ''))) {
            return ['ok' => false, 'flash' => ['type' => 'error', 'msg' => 'Die Systemrolle "admin" darf nicht gelöscht werden.']];
        }

        $assigned = $this->countAssignments($id);
        if ($assigned > 0) {
            return ['ok' => false, 'flash' => ['type' => 'error', 'msg' => 'Die Rolle ist noch Benutzern zugewiesen und kann nicht gelöscht werden.']];
        }

        $stmt = $this->pdo->prepare("UPDATE roles SET is_deleted = 1, deleted_at = NOW() WHERE id = :id AND (is_deleted = 0 OR is_deleted IS NULL)");
        $stmt->execute([':id' => $id]);

        return ['ok' => true, 'flash' => ['type' => 'ok', 'msg' => 'Rolle gelöscht.']];
    }

    public function restore(int $id): void
    {
        // Systemrolle: niemals restorebar
        if ($this->isSystemRoleId($id)) {
            return;
        }

        $stmt = $this->pdo->prepare("UPDATE roles SET is_deleted = 0, deleted_at = NULL WHERE id = :id AND is_deleted = 1");
        $stmt->execute([':id' => $id]);
    }
    public function purgeDeleted(): int
    {
        // Defense-in-depth: Admin-Rolle nie hard-deleten
        $st = $this->pdo->prepare("DELETE FROM roles WHERE is_deleted = 1 AND `key` <> 'admin'");
        $st->execute();
        return (int)$st->rowCount();
    }
}
