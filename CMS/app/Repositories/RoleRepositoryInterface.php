<?php
declare(strict_types=1);

namespace App\Repositories;

interface RoleRepositoryInterface
{
    public function isSystemRoleKey(string $key): bool;
    public function isSystemRoleId(int $roleId): bool;
    public function listActive(): array;
    public function listDeleted(): array;
    public function countDeleted(): int;
    public function findById(int $id): ?array;
    public function findByKey(string $key): ?array;
    public function save(?int $id, string $key, string $name): array;
    public function softDelete(int $id): array;
    public function restore(int $id): void;
    public function purgeDeleted(): int;
}

