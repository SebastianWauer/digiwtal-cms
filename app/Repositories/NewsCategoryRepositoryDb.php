<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class NewsCategoryRepositoryDb
{
    public function __construct(private PDO $pdo) {}

    public function listActive(): array
    {
        $st = $this->pdo->query("SELECT id, name, slug, sort_order FROM news_categories WHERE is_deleted = 0 ORDER BY sort_order ASC, name ASC");
        $rows = $st ? $st->fetchAll() : [];
        return is_array($rows) ? $rows : [];
    }

    public function findById(int $id): ?array
    {
        $st = $this->pdo->prepare("SELECT id, name, slug, sort_order FROM news_categories WHERE id = :id AND is_deleted = 0 LIMIT 1");
        $st->execute([':id' => $id]);
        $row = $st->fetch();
        return is_array($row) ? $row : null;
    }

    public function findBySlug(string $slug): ?array
    {
        $st = $this->pdo->prepare("SELECT id, name, slug, sort_order FROM news_categories WHERE slug = :slug LIMIT 1");
        $st->execute([':slug' => $slug]);
        $row = $st->fetch();
        return is_array($row) ? $row : null;
    }

    public function create(string $name, string $slug, int $sortOrder = 100): int
    {
        $st = $this->pdo->prepare("INSERT INTO news_categories (name, slug, sort_order, is_deleted) VALUES (:name, :slug, :sort_order, 0)");
        $st->execute([':name' => $name, ':slug' => $slug, ':sort_order' => $sortOrder]);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, string $name): void
    {
        $slugBase = self::slugify($name);
        $slug = $slugBase;
        $i = 2;
        while ($this->slugExistsForOther($slug, $id)) {
            $slug = $slugBase . '-' . $i;
            $i++;
        }
        $st = $this->pdo->prepare("UPDATE news_categories SET name = :name, slug = :slug WHERE id = :id AND is_deleted = 0 LIMIT 1");
        $st->execute([':id' => $id, ':name' => $name, ':slug' => $slug]);
    }

    private function slugExistsForOther(string $slug, int $exceptId): bool
    {
        $st = $this->pdo->prepare("SELECT 1 FROM news_categories WHERE slug = :slug AND id <> :id LIMIT 1");
        $st->execute([':slug' => $slug, ':id' => $exceptId]);
        return (bool)$st->fetchColumn();
    }

    public static function slugify(string $name): string
    {
        $slug = function_exists('mb_strtolower') ? mb_strtolower(trim($name), 'UTF-8') : strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9]+/u', '-', $slug) ?? '';
        $slug = trim($slug, '-');
        return $slug !== '' ? $slug : 'news-kategorie';
    }
}
