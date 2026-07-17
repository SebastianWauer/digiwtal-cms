<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class MediaRepositoryDb implements MediaRepositoryInterface
{
    public function __construct(private PDO $pdo) {}

    // Methode zum Erstellen eines neuen Medieneintrags
public function create(array $data): int
{
    // SQL-Query für das Einfügen eines neuen Medien-Datensatzes in die media_items-Tabelle
    $sql = "INSERT INTO media_items (folder_id, original_filename, display_filename, storage_filename, ext, mime, size_bytes, width, height, title, alt_text, description, focus_x, focus_y, usage_count, created_at)
            VALUES (:folder_id, :original_filename, :display_filename, :storage_filename, :ext, :mime, :size_bytes, :width, :height, :title, :alt_text, :description, :focus_x, :focus_y, :usage_count, :created_at)";
    
    $stmt = $this->pdo->prepare($sql);

    // Binde die Werte aus dem $data-Array an die SQL-Abfrage
    $stmt->bindParam(':folder_id', $data['folder_id'], PDO::PARAM_INT);
    $stmt->bindParam(':original_filename', $data['original_filename'], PDO::PARAM_STR);
    $stmt->bindParam(':display_filename', $data['display_filename'], PDO::PARAM_STR);
    $stmt->bindParam(':storage_filename', $data['storage_filename'], PDO::PARAM_STR);
    $stmt->bindParam(':ext', $data['ext'], PDO::PARAM_STR);
    $stmt->bindParam(':mime', $data['mime'], PDO::PARAM_STR);
    $stmt->bindParam(':size_bytes', $data['size_bytes'], PDO::PARAM_INT);
    $stmt->bindParam(':width', $data['width'], PDO::PARAM_INT);
    $stmt->bindParam(':height', $data['height'], PDO::PARAM_INT);
    $stmt->bindParam(':title', $data['title'], PDO::PARAM_STR);
    $stmt->bindParam(':alt_text', $data['alt_text'], PDO::PARAM_STR);
    $stmt->bindParam(':description', $data['description'], PDO::PARAM_STR);
    $stmt->bindParam(':focus_x', $data['focus_x']);
    $stmt->bindParam(':focus_y', $data['focus_y']);
    $stmt->bindParam(':usage_count', $data['usage_count'], PDO::PARAM_INT);
    $stmt->bindParam(':created_at', $data['created_at'], PDO::PARAM_STR);

    // Führe die Abfrage aus
    $stmt->execute();

    // Gib die ID des neu eingefügten Datensatzes zurück
    return (int)$this->pdo->lastInsertId();
}
public function updateMediaPath(int $mediaId, string $path): bool
{
    $stmt = $this->pdo->prepare("UPDATE media_items SET storage_filename = :path WHERE id = :id");
    $stmt->bindValue(':path', $path, PDO::PARAM_STR);
    $stmt->bindValue(':id', $mediaId, PDO::PARAM_INT);
    return $stmt->execute();
}

    public function listActive(
        ?int $folderId = null,
        string $q = '',
        string $ext = '',
        bool $onlyUnused = false,
        int $limit = 200,
        int $offset = 0
    ): array {
        $q   = trim($q);
        $ext = trim($ext);

        $limit  = max(1, min(500, $limit));
        $offset = max(0, $offset);

        $sql = "
            SELECT
              mi.id,
              mi.folder_id,
              f.name AS folder_name,
              mi.original_filename,
              mi.display_filename,
              mi.storage_filename,
              mi.ext,
              mi.mime,
              mi.size_bytes,
              mi.width,
              mi.height,
              mi.title,
              mi.alt_text,
              mi.description,
              mi.focus_x,
              mi.focus_y,
              mi.usage_count,
              mi.created_at,
              mi.updated_at,
              mi.is_deleted,
              mi.deleted_at
            FROM media_items mi
            LEFT JOIN media_folders f ON f.id = mi.folder_id
            WHERE mi.is_deleted = 0
        ";

        $params = [];

        if ($folderId !== null && $folderId > 0) {
            $sql .= " AND mi.folder_id = :fid";
            $params[':fid'] = (int)$folderId;
        }

        if ($ext !== '') {
            $sql .= " AND mi.ext = :ext";
            $params[':ext'] = $ext;
        }

        if ($onlyUnused) {
            $sql .= " AND mi.usage_count = 0";
        }

        if ($q !== '') {
            $sql .= " AND (
                mi.display_filename LIKE :q
                OR mi.original_filename LIKE :q
                OR mi.title LIKE :q
                OR mi.alt_text LIKE :q
                OR mi.description LIKE :q
            )";
            $params[':q'] = '%' . $q . '%';
        }

        $sql .= "
            ORDER BY mi.created_at DESC, mi.id DESC
            LIMIT {$limit} OFFSET {$offset}
        ";

        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    public function countActive(
        ?int $folderId = null,
        string $q = '',
        string $ext = '',
        bool $onlyUnused = false
    ): int {
        $q   = trim($q);
        $ext = trim($ext);

        $sql = "
            SELECT COUNT(*) AS c
            FROM media_items mi
            WHERE mi.is_deleted = 0
        ";
        $params = [];

        if ($folderId !== null && $folderId > 0) {
            $sql .= " AND mi.folder_id = :fid";
            $params[':fid'] = (int)$folderId;
        }

        if ($ext !== '') {
            $sql .= " AND mi.ext = :ext";
            $params[':ext'] = $ext;
        }

        if ($onlyUnused) {
            $sql .= " AND mi.usage_count = 0";
        }

        if ($q !== '') {
            $sql .= " AND (
                mi.display_filename LIKE :q
                OR mi.original_filename LIKE :q
                OR mi.title LIKE :q
                OR mi.alt_text LIKE :q
                OR mi.description LIKE :q
            )";
            $params[':q'] = '%' . $q . '%';
        }

        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return (int)($st->fetchColumn() ?: 0);
    }

    public function listDeleted(string $q = '', string $ext = '', int $limit = 200, int $offset = 0): array
    {
        $q   = trim($q);
        $ext = trim($ext);

        $limit  = max(1, min(500, $limit));
        $offset = max(0, $offset);

        $sql = "
            SELECT
              mi.id,
              mi.folder_id,
              f.name AS folder_name,
              mi.original_filename,
              mi.display_filename,
              mi.storage_filename,
              mi.ext,
              mi.mime,
              mi.size_bytes,
              mi.width,
              mi.height,
              mi.title,
              mi.alt_text,
              mi.description,
              mi.focus_x,
              mi.focus_y,
              mi.usage_count,
              mi.created_at,
              mi.updated_at,
              mi.is_deleted,
              mi.deleted_at
            FROM media_items mi
            LEFT JOIN media_folders f ON f.id = mi.folder_id
            WHERE mi.is_deleted = 1
        ";

        $params = [];

        if ($ext !== '') {
            $sql .= " AND mi.ext = :ext";
            $params[':ext'] = $ext;
        }

        if ($q !== '') {
            $sql .= " AND (
                mi.display_filename LIKE :q
                OR mi.original_filename LIKE :q
                OR mi.title LIKE :q
                OR mi.alt_text LIKE :q
                OR mi.description LIKE :q
            )";
            $params[':q'] = '%' . $q . '%';
        }

        $sql .= "
            ORDER BY mi.deleted_at DESC, mi.id DESC
            LIMIT {$limit} OFFSET {$offset}
        ";

        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    public function findById(int $id): ?array
    {
        $st = $this->pdo->prepare("
            SELECT
              mi.*,
              f.name AS folder_name
            FROM media_items mi
            LEFT JOIN media_folders f ON f.id = mi.folder_id
            WHERE mi.id = :id
            LIMIT 1
        ");
        $st->execute([':id' => $id]);
        $row = $st->fetch();

        return is_array($row) ? $row : null;
    }

    public function insertItem(array $data): int
    {
        $st = $this->pdo->prepare("
            INSERT INTO media_items (
              folder_id,
              original_filename,
              display_filename,
              storage_filename,
              ext,
              mime,
              size_bytes,
              width,
              height,
              created_at
            ) VALUES (
              :folder_id,
              :original_filename,
              :display_filename,
              :storage_filename,
              :ext,
              :mime,
              :size_bytes,
              :width,
              :height,
              NOW()
            )
        ");
        $st->execute([
            ':folder_id'          => $data['folder_id'] ?? null,
            ':original_filename'  => $data['original_filename'] ?? '',
            ':display_filename'   => $data['display_filename'] ?? '',
            ':storage_filename'   => $data['storage_filename'] ?? '',
            ':ext'                => $data['ext'] ?? '',
            ':mime'               => $data['mime'] ?? '',
            ':size_bytes'         => (int)($data['size_bytes'] ?? 0),
            ':width'              => $data['width'] ?? null,
            ':height'             => $data['height'] ?? null,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function updateMeta(int $id, array $data): void
    {
        $st = $this->pdo->prepare("
            UPDATE media_items
            SET
              folder_id = :folder_id,
              display_filename = :display_filename,
              title = :title,
              alt_text = :alt_text,
              description = :description,
              focus_x = :focus_x,
              focus_y = :focus_y,
              updated_at = NOW()
            WHERE id = :id
            LIMIT 1
        ");
        $st->execute([
            ':id'               => $id,
            ':folder_id'        => (int)($data['folder_id'] ?? 1),
            ':display_filename' => (string)($data['display_filename'] ?? ''),
            ':title'            => $data['title'] ?? null,
            ':alt_text'         => $data['alt_text'] ?? null,
            ':description'      => $data['description'] ?? null,
            ':focus_x'          => $data['focus_x'] ?? null,
            ':focus_y'          => $data['focus_y'] ?? null,
        ]);
    }

    public function moveToFolder(int $mediaId, int $folderId): bool
    {
        if ($mediaId <= 0 || $folderId <= 0) return false;

        $st = $this->pdo->prepare("
            UPDATE media_items
            SET folder_id = :fid, updated_at = NOW()
            WHERE id = :id
              AND is_deleted = 0
            LIMIT 1
        ");
        $st->execute([
            ':id'  => $mediaId,
            ':fid' => $folderId,
        ]);

        return ((int)$st->rowCount() > 0);
    }

    public function softDeleteUnusedBulk(array $ids): int
    {
        $ids = array_values(array_filter(array_map('intval', $ids), fn($v) => $v > 0));
        if (!$ids) return 0;

        $in = implode(',', array_fill(0, count($ids), '?'));
        $st = $this->pdo->prepare("
            UPDATE media_items
            SET is_deleted = 1, deleted_at = NOW()
            WHERE id IN ($in)
              AND usage_count = 0
              AND is_deleted = 0
        ");
        $st->execute($ids);

        return (int)$st->rowCount();
    }

    public function restore(int $id): bool
    {
        $st = $this->pdo->prepare("
            UPDATE media_items
            SET is_deleted = 0, deleted_at = NULL, updated_at = NOW()
            WHERE id = :id
              AND is_deleted = 1
            LIMIT 1
        ");
        $st->execute([':id' => $id]);

        return ((int)$st->rowCount() > 0);
    }

    public function setUsageCount(int $id, int $count): void
    {
        $st = $this->pdo->prepare("
            UPDATE media_items
            SET usage_count = :c, updated_at = NOW()
            WHERE id = :id
            LIMIT 1
        ");
        $st->execute([
            ':id' => $id,
            ':c'  => max(0, $count),
        ]);
    }
    public function listDeletedForPurge(): array
    {
        $st = $this->pdo->prepare("
            SELECT id, storage_filename
            FROM media_items
            WHERE is_deleted = 1
            AND usage_count = 0
        ");
        $st->execute();
        $rows = $st->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public function purgeDeletedByIds(array $ids): int
    {
        $ids = array_values(array_filter(array_map('intval', $ids), static fn($v) => $v > 0));
        if (!$ids) return 0;

        $in = implode(',', array_fill(0, count($ids), '?'));

        $st = $this->pdo->prepare("
            DELETE FROM media_items
            WHERE id IN ($in)
            AND is_deleted = 1
            AND usage_count = 0
        ");
        $st->execute($ids);

        return (int)$st->rowCount();
    }
}
