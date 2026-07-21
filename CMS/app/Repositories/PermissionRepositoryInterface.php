<?php
declare(strict_types=1);

namespace App\Repositories;

interface PermissionRepositoryInterface
{
    public function listAll(): array;
    public function findByKey(string $key): ?array;
    public function existsKey(string $key): bool;
}

