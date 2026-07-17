<?php
declare(strict_types=1);

namespace App\Repositories;

final class SeoRepositoryDb implements SeoRepositoryInterface
{
    public function __construct(private \PDO $pdo) {}

    public function findForEntity(string $entityType, int $entityId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM seo_meta WHERE entity_type = :et AND entity_id = :ei LIMIT 1'
        );
        $stmt->execute([':et' => $entityType, ':ei' => $entityId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function upsertForEntity(string $entityType, int $entityId, array $data): void
    {
        $this->pdo->prepare('
            INSERT INTO seo_meta
              (entity_type, entity_id, meta_title, meta_description, robots, canonical_url, og_title, og_description, og_image_url)
            VALUES
              (:et, :ei, :mt, :md, :ro, :cu, :ot, :od, :oi)
            ON DUPLICATE KEY UPDATE
              meta_title       = VALUES(meta_title),
              meta_description = VALUES(meta_description),
              robots           = VALUES(robots),
              canonical_url    = VALUES(canonical_url),
              og_title         = VALUES(og_title),
              og_description   = VALUES(og_description),
              og_image_url     = VALUES(og_image_url),
              updated_at       = CURRENT_TIMESTAMP
        ')->execute([
            ':et' => $entityType,
            ':ei' => $entityId,
            ':mt' => (string)($data['meta_title']       ?? ''),
            ':md' => (string)($data['meta_description'] ?? ''),
            ':ro' => (string)($data['robots']           ?? ''),
            ':cu' => (string)($data['canonical_url']    ?? ''),
            ':ot' => (string)($data['og_title']         ?? ''),
            ':od' => (string)($data['og_description']   ?? ''),
            ':oi' => (string)($data['og_image_url']     ?? ''),
        ]);
    }
}
