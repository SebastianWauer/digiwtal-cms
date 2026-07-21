<?php
declare(strict_types=1);

namespace App\Repositories;

interface UserRepositoryInterface
{
    public function countActiveEnabledNonSystem(): int;
    public function countEnabled(): int;
    public function listActive(): array;
    public function listDeleted(): array;
    public function countDeleted(): int;
    public function findById(int $id): ?array;
    public function findByUsername(string $username): ?array;
    public function findByEmail(string $email): ?array;
    public function isAdminUser(int $userId): bool;
    public function countActiveAdmins(): int;
    public function isSystemUserAnyState(int $userId): bool;
    public function save(?int $id, string $username, string $name, ?string $email, bool $enabled, ?string $newPassword): array;
    public function softDelete(int $id): void;
    public function restore(int $id): void;
    public function purgeDeleted(): int;
}

