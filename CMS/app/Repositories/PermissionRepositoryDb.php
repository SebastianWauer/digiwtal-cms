<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class PermissionRepositoryDb implements PermissionRepositoryInterface
{
    public function __construct(private PDO $pdo) {}

    /** @return array<int,array{id:int,key:string,label:string,group_key:string,created_at:string}> */
    public function listAll(): array
    {
        $stmt = $this->pdo->query("
            SELECT id, `key`, `label`, `group_key`, created_at
            FROM permissions
            ORDER BY group_key ASC, `key` ASC
        ");
        $rows = $stmt ? $stmt->fetchAll() : [];
        return is_array($rows) ? $rows : [];
    }

    /** @return array{id:int,key:string,label:string,group_key:string,created_at:string}|null */
    public function findByKey(string $key): ?array
    {
        $key = trim($key);
        if ($key === '') return null;

        $stmt = $this->pdo->prepare("
            SELECT id, `key`, `label`, `group_key`, created_at
            FROM permissions
            WHERE `key` = :k
            LIMIT 1
        ");
        $stmt->execute([':k' => $key]);

        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function existsKey(string $key): bool
    {
        $key = trim($key);
        if ($key === '') return false;

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM permissions WHERE `key` = :k");
        $stmt->execute([':k' => $key]);

        return ((int)$stmt->fetchColumn()) > 0;
    }
}
