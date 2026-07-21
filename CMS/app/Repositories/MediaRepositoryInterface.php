<?php
declare(strict_types=1);

namespace App\Repositories;

interface MediaRepositoryInterface
{
    public function create(array $data): int;
    public function updateMediaPath(int $mediaId, string $path): bool;
    public function listActive(?int $folderId = null, string $q = '', string $ext = '', bool $onlyUnused = false, int $limit = 200, int $offset = 0): array;
    public function countActive(?int $folderId = null, string $q = '', string $ext = '', bool $onlyUnused = false): int;
    public function listDeleted(string $q = '', string $ext = '', int $limit = 200, int $offset = 0): array;
    public function findById(int $id): ?array;
    public function insertItem(array $data): int;
    public function updateMeta(int $id, array $data): void;
    public function moveToFolder(int $mediaId, int $folderId): bool;
    public function softDeleteUnusedBulk(array $ids): int;
    public function restore(int $id): bool;
    public function setUsageCount(int $id, int $count): void;
    public function listDeletedForPurge(): array;
    public function purgeDeletedByIds(array $ids): int;
}

