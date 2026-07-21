<?php
declare(strict_types=1);

namespace App\Repositories;

interface SiteSettingsRepositoryInterface
{
    public function getAll(): array;
    public function set(string $key, ?string $value): void;
}

