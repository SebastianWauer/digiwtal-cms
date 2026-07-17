<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class NewsRepositoryDb
{
    public function __construct(private PDO $pdo) {}

    public function listActive(?int $categoryId = null): array
    {
        $sql = "
            SELECT n.id, n.title, n.slug, n.teaser, n.content_json, n.category_id, n.image_media_id, n.is_published, n.published_at, n.is_deleted, n.updated_at,
                   c.name AS category_name, c.slug AS category_slug
            FROM news n
            LEFT JOIN news_categories c ON c.id = n.category_id
            WHERE n.is_deleted = 0
        ";
        $params = [];
        if ($categoryId !== null && $categoryId > 0) {
            $sql .= " AND n.category_id = :cid";
            $params[':cid'] = $categoryId;
        }
        $sql .= " ORDER BY COALESCE(n.published_at, n.created_at) DESC, n.id DESC";
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public function listDeleted(): array
    {
        $st = $this->pdo->query("SELECT n.id, n.title, n.slug, n.teaser, n.published_at, n.updated_at, c.name AS category_name FROM news n LEFT JOIN news_categories c ON c.id = n.category_id WHERE n.is_deleted = 1 ORDER BY n.updated_at DESC, n.id DESC");
        $rows = $st ? $st->fetchAll() : [];
        return is_array($rows) ? $rows : [];
    }

    public function countDeleted(): int
    {
        return (int)$this->pdo->query("SELECT COUNT(*) FROM news WHERE is_deleted = 1")->fetchColumn();
    }

    public function findById(int $id): ?array
    {
        $st = $this->pdo->prepare("SELECT * FROM news WHERE id = :id LIMIT 1");
        $st->execute([':id' => $id]);
        $row = $st->fetch();
        return is_array($row) ? $row : null;
    }

    public function findBySlug(string $slug): ?array
    {
        $st = $this->pdo->prepare("SELECT n.*, c.name AS category_name, c.slug AS category_slug FROM news n LEFT JOIN news_categories c ON c.id = n.category_id WHERE n.slug = :slug AND n.is_deleted = 0 LIMIT 1");
        $st->execute([':slug' => $slug]);
        $row = $st->fetch();
        return is_array($row) ? $row : null;
    }

    public function save(?int $id, string $title, string $slug, string $teaser, string $contentJson, ?int $categoryId, ?int $imageMediaId, bool $isPublished, ?string $publishedAt): int
    {
        if ($id !== null && $id > 0) {
            $st = $this->pdo->prepare("UPDATE news SET title=:title, slug=:slug, teaser=:teaser, content_json=:content_json, category_id=:category_id, image_media_id=:image_media_id, is_published=:is_published, published_at=:published_at WHERE id=:id LIMIT 1");
            $st->execute([
                ':id' => $id,
                ':title' => $title,
                ':slug' => $slug,
                ':teaser' => $teaser,
                ':content_json' => $contentJson,
                ':category_id' => $categoryId,
                ':image_media_id' => $imageMediaId,
                ':is_published' => $isPublished ? 1 : 0,
                ':published_at' => $publishedAt,
            ]);
            return $id;
        }

        $st = $this->pdo->prepare("INSERT INTO news (title, slug, teaser, content_json, category_id, image_media_id, is_published, published_at, is_deleted) VALUES (:title,:slug,:teaser,:content_json,:category_id,:image_media_id,:is_published,:published_at,0)");
        $st->execute([
            ':title' => $title,
            ':slug' => $slug,
            ':teaser' => $teaser,
            ':content_json' => $contentJson,
            ':category_id' => $categoryId,
            ':image_media_id' => $imageMediaId,
            ':is_published' => $isPublished ? 1 : 0,
            ':published_at' => $publishedAt,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function slugExistsForOther(string $slug, int $exceptId = 0): bool
    {
        if ($exceptId > 0) {
            $st = $this->pdo->prepare("SELECT 1 FROM news WHERE slug = :slug AND id <> :id LIMIT 1");
            $st->execute([':slug' => $slug, ':id' => $exceptId]);
            return (bool)$st->fetchColumn();
        }
        $st = $this->pdo->prepare("SELECT 1 FROM news WHERE slug = :slug LIMIT 1");
        $st->execute([':slug' => $slug]);
        return (bool)$st->fetchColumn();
    }

    public function softDelete(int $id): void
    {
        $st = $this->pdo->prepare("UPDATE news SET is_deleted = 1 WHERE id = :id LIMIT 1");
        $st->execute([':id' => $id]);
    }

    public function restore(int $id): void
    {
        $st = $this->pdo->prepare("UPDATE news SET is_deleted = 0 WHERE id = :id LIMIT 1");
        $st->execute([':id' => $id]);
    }

    public function purgeDeleted(): int
    {
        $st = $this->pdo->prepare("DELETE FROM news WHERE is_deleted = 1");
        $st->execute();
        return (int)$st->rowCount();
    }
}
