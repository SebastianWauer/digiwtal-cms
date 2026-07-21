<?php
declare(strict_types=1);

namespace App\Repositories;

interface MediaUsageRepositoryInterface
{
    public function listForMedia(int $mediaId): array;
    public function deleteForEntity(string $entityType, int $entityId): void;
    public function insertIgnore(int $mediaId, string $entityType, int $entityId, string $fieldKey): void;
    public function insertIgnoreBulk(string $entityType, int $entityId, array $rows): void;
    public function countForMedia(int $mediaId): int;
    public function countForMediaBulk(array $mediaIds): array;
}

