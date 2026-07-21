<?php
declare(strict_types=1);

namespace App\Repositories;

interface UserRoleRepositoryInterface
{
    public function roleIdsForUser(int $userId): array;
    public function setRoles(int $userId, array $roleIds): void;
    public function roleNamesForUsers(array $userIds): array;
    public function adminUserIdsForUsers(array $userIds): array;
}
