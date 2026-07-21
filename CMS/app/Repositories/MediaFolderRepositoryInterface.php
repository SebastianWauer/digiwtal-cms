<?php
declare(strict_types=1);

namespace App\Repositories;

interface MediaFolderRepositoryInterface
{
    public function listAll(): array;
    public function findById(int $id): ?array;
    public function findByParentAndName(?int $parentId, string $name): ?array;
    public function createFolder(int $parentId, string $name, int $sortOrder = 0): int;
    public function updateName(int $id, string $name): void;
    public function moveFolder(int $id, ?int $parentId): bool;
    public function deleteFolder(int $id): bool;
    public function countChildren(int $id): int;
    public function countMediaItems(int $id): int;
}
