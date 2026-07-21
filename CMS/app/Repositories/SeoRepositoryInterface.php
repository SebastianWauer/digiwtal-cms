<?php
declare(strict_types=1);

namespace App\Repositories;

interface SeoRepositoryInterface
{
    public function findForEntity(string $entityType, int $entityId): ?array;
    public function upsertForEntity(string $entityType, int $entityId, array $data): void;
}

