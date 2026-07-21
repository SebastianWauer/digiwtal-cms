<?php
declare(strict_types=1);

namespace App\Repositories;

interface UserPrefRepositoryInterface
{
    public function get(int $userId, string $key, ?string $default = null): ?string;
    public function set(int $userId, string $key, string $value): void;
    public function delete(int $userId, string $key): void;
}

