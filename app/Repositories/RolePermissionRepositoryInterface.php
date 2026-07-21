<?php
declare(strict_types=1);

namespace App\Repositories;

interface RolePermissionRepositoryInterface
{
    public function userHasPermission(int $userId, string $permissionKey): bool;
}

