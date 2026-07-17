<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class MediaUsageRepositoryDb implements MediaUsageRepositoryInterface
{
    public function __construct(private PDO $pdo) {}

    /**
     * @return array<int,array<string,mixed>>
     */
    public function listForMedia(int $mediaId): array
    {
        $st = $this->pdo->prepare("
            SELECT
              id, media_id, entity_type, entity_id, field_key, created_at
            FROM media_usages
            WHERE media_id = :mid
            ORDER BY created_at DESC, id DESC
        ");
        $st->execute([':mid' => $mediaId]);
        $rows = $st->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /**
     * Löscht alle Usages für ein Entity (z.B. page#12).
     */
    public function deleteForEntity(string $entityType, int $entityId): void
    {
        $entityType = trim($entityType);
        if ($entityType === '' || $entityId <= 0) return;

        $st = $this->pdo->prepare("
            DELETE FROM media_usages
            WHERE entity_type = :t AND entity_id = :id
        ");
        $st->execute([':t' => $entityType, ':id' => $entityId]);
    }

    /**
     * Insert/Ignore für eine Usage-Zeile (Unique Index schützt vor Duplikaten).
     */
    public function insertIgnore(int $mediaId, string $entityType, int $entityId, string $fieldKey): void
    {
        $entityType = trim($entityType);
        $fieldKey   = trim($fieldKey);
        if ($mediaId <= 0 || $entityType === '' || $entityId <= 0 || $fieldKey === '') return;

        $st = $this->pdo->prepare("
            INSERT IGNORE INTO media_usages (media_id, entity_type, entity_id, field_key, created_at)
            VALUES (:mid, :t, :eid, :fk, NOW())
        ");
        $st->execute([
            ':mid' => $mediaId,
            ':t'   => $entityType,
            ':eid' => $entityId,
            ':fk'  => $fieldKey,
        ]);
    }

    /**
     * @param array<int,array{media_id:int,field_key:string}> $rows
     */
    public function insertIgnoreBulk(string $entityType, int $entityId, array $rows): void
    {
        $entityType = trim($entityType);
        if ($entityType === '' || $entityId <= 0) return;

        $rows = array_values(array_filter($rows, function ($r) {
            if (!is_array($r)) return false;
            $mid = (int)($r['media_id'] ?? 0);
            $fk  = trim((string)($r['field_key'] ?? ''));
            return $mid > 0 && $fk !== '';
        }));

        if (!$rows) return;

        $st = $this->pdo->prepare("
            INSERT IGNORE INTO media_usages (media_id, entity_type, entity_id, field_key, created_at)
            VALUES (:mid, :t, :eid, :fk, NOW())
        ");

        foreach ($rows as $r) {
            $st->execute([
                ':mid' => (int)$r['media_id'],
                ':t'   => $entityType,
                ':eid' => $entityId,
                ':fk'  => (string)$r['field_key'],
            ]);
        }
    }

    public function countForMedia(int $mediaId): int
    {
        $st = $this->pdo->prepare("SELECT COUNT(*) AS c FROM media_usages WHERE media_id = :mid");
        $st->execute([':mid' => $mediaId]);
        $row = $st->fetch();

        return is_array($row) ? (int)($row['c'] ?? 0) : 0;
    }

    /**
     * @param array<int,int> $mediaIds
     * @return array<int,int> media_id => count
     */
    public function countForMediaBulk(array $mediaIds): array
    {
        $mediaIds = array_values(array_unique(array_map('intval', $mediaIds)));
        $mediaIds = array_values(array_filter($mediaIds, fn($x) => $x > 0));
        if (!$mediaIds) return [];

        $in = implode(',', array_fill(0, count($mediaIds), '?'));
        $st = $this->pdo->prepare("
            SELECT media_id, COUNT(*) AS c
            FROM media_usages
            WHERE media_id IN ($in)
            GROUP BY media_id
        ");
        $st->execute($mediaIds);
        $rows = $st->fetchAll();

        $out = [];
        if (is_array($rows)) {
            foreach ($rows as $r) {
                if (!is_array($r)) continue;
                $mid = (int)($r['media_id'] ?? 0);
                $c   = (int)($r['c'] ?? 0);
                if ($mid > 0) $out[$mid] = $c;
            }
        }
        return $out;
    }
}
