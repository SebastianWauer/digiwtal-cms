<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class UserRoleRepositoryDb implements UserRoleRepositoryInterface
{
    public function __construct(private PDO $pdo) {}

    /**
     * Systemrolle ist fest: roles.key = 'admin'
     * Diese Rolle darf niemals über normale User-UI/Requests zugewiesen werden.
     */
    private const SYSTEM_ROLE_KEY = 'admin';

    /** @return int[] */
    private function systemRoleIds(): array
    {
        static $cache = null;
        if (is_array($cache)) return $cache;

        $stmt = $this->pdo->prepare("
            SELECT id
            FROM roles
            WHERE `key` = :k
              AND (is_deleted = 0 OR is_deleted IS NULL)
        ");
        $stmt->execute([':k' => self::SYSTEM_ROLE_KEY]);
        $rows = $stmt->fetchAll();

        $ids = [];
        if (is_array($rows)) {
            foreach ($rows as $r) {
                if (!is_array($r)) continue;
                $id = (int)($r['id'] ?? 0);
                if ($id > 0) $ids[] = $id;
            }
        }

        $cache = array_values(array_unique($ids));
        return $cache;
    }

    /** @param int[] $roleIds @return int[] */
    private function filterOutSystemRoles(array $roleIds): array
    {
        $roleIds = array_values(array_unique(array_map('intval', $roleIds)));
        $roleIds = array_values(array_filter($roleIds, fn($x) => $x > 0));
        if (!$roleIds) return [];

        $sys = $this->systemRoleIds();
        if (!$sys) return $roleIds;

        $sysSet = array_fill_keys($sys, true);
        $roleIds = array_values(array_filter($roleIds, fn($rid) => !isset($sysSet[(int)$rid])));

        return $roleIds;
    }

    /** @return int[] */
    public function roleIdsForUser(int $userId): array
    {
        // Wichtig: normale Rollenabfrage liefert NIE die Systemrolle zurück
        $stmt = $this->pdo->prepare("
            SELECT ur.role_id
            FROM user_roles ur
            JOIN roles r ON r.id = ur.role_id
            WHERE ur.user_id = :uid
              AND (r.is_deleted = 0 OR r.is_deleted IS NULL)
              AND r.`key` <> :sys
            ORDER BY ur.role_id ASC
        ");
        $stmt->execute([':uid' => $userId, ':sys' => self::SYSTEM_ROLE_KEY]);

        $rows = $stmt->fetchAll();
        if (!is_array($rows)) return [];

        $out = [];
        foreach ($rows as $r) {
            if (!is_array($r)) continue;
            $out[] = (int)($r['role_id'] ?? 0);
        }

        $out = array_values(array_filter(array_unique($out), fn($x) => $x > 0));
        return $out;
    }

    /** @param int[] $roleIds */
    public function setRoles(int $userId, array $roleIds): void
    {
        // Backend-autoritativer Schutz: Systemrolle rausfiltern (auch bei manipuliertem POST)
        $roleIds = $this->filterOutSystemRoles($roleIds);

        $this->pdo->beginTransaction();
        try {
            $del = $this->pdo->prepare("DELETE FROM user_roles WHERE user_id = :uid");
            $del->execute([':uid' => $userId]);

            if ($roleIds) {
                $ins = $this->pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (:uid, :rid)");
                foreach ($roleIds as $rid) {
                    $ins->execute([':uid' => $userId, ':rid' => $rid]);
                }
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Rollen-Namen für viele User in einem Query (kein N+1)
     * @param int[] $userIds
     * @return array<int, string[]> map: user_id => [roleName, ...]
     */
    public function roleNamesForUsers(array $userIds): array
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        $userIds = array_values(array_filter($userIds, fn($x) => $x > 0));
        if (!$userIds) return [];

        $placeholders = implode(',', array_fill(0, count($userIds), '?'));

        // Auch hier: Systemrolle niemals in normaler Anzeige ausgeben
        $sql = "
            SELECT ur.user_id, r.name
            FROM user_roles ur
            JOIN roles r ON r.id = ur.role_id
            WHERE ur.user_id IN ($placeholders)
              AND (r.is_deleted = 0 OR r.is_deleted IS NULL)
              AND r.`key` <> '" . self::SYSTEM_ROLE_KEY . "'
            ORDER BY ur.user_id ASC, r.name ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($userIds);
        $rows = $stmt->fetchAll();
        if (!is_array($rows)) return [];

        $out = [];
        foreach ($rows as $r) {
            if (!is_array($r)) continue;
            $uid = (int)($r['user_id'] ?? 0);
            $name = (string)($r['name'] ?? '');
            if ($uid <= 0 || $name === '') continue;
            $out[$uid] ??= [];
            $out[$uid][] = $name;
        }

        return $out;
    }

    /**
     * Liefert die User-IDs, die (unter den übergebenen UserIDs) Admin sind (roles.key='admin')
     * @param int[] $userIds
     * @return int[] admin user ids
     */
    public function adminUserIdsForUsers(array $userIds): array
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        $userIds = array_values(array_filter($userIds, fn($x) => $x > 0));
        if (!$userIds) return [];

        $placeholders = implode(',', array_fill(0, count($userIds), '?'));

        $sql = "
            SELECT DISTINCT ur.user_id
            FROM user_roles ur
            JOIN roles r ON r.id = ur.role_id
            JOIN users u ON u.id = ur.user_id
            WHERE ur.user_id IN ($placeholders)
              AND u.is_deleted = 0
              AND (r.is_deleted = 0 OR r.is_deleted IS NULL)
              AND r.`key` = 'admin'
            ORDER BY ur.user_id ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($userIds);

        $rows = $stmt->fetchAll();
        if (!is_array($rows)) return [];

        $out = [];
        foreach ($rows as $r) {
            if (!is_array($r)) continue;
            $uid = (int)($r['user_id'] ?? 0);
            if ($uid > 0) $out[] = $uid;
        }

        return array_values(array_unique($out));
    }
}
