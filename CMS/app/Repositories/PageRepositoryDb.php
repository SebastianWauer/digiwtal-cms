<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class PageRepositoryDb implements PageRepositoryInterface
{
    public function __construct(private PDO $pdo) {}

    /**
     * pages_list Sortierung:
     * 1) Live vor Draft
     * 2) Innerhalb Live: Header -> Footer -> keine Zuordnung
     *    - beides ('both') zählt als Header
     *    - keine Zuordnung: nav_visible = 0 (oder nav_area leer)
     * 3) Position (nav_order) innerhalb Header/Footer
     * 4) Stabil: is_home DESC, slug ASC
     */
    public function listActive(): array
    {
        $stmt = $this->pdo->query("
            SELECT
              id, slug, title,
              status,
              is_home, is_deleted, deleted_at, updated_at,
              nav_visible, nav_area, nav_order
            FROM pages
            WHERE is_deleted = 0
            ORDER BY
              CASE WHEN status = 'live' THEN 0 ELSE 1 END ASC,

              CASE
                WHEN status = 'live' THEN
                  CASE
                    WHEN nav_visible = 1 AND (nav_area = 'header' OR nav_area = 'both') THEN 0
                    WHEN nav_visible = 1 AND nav_area = 'footer' THEN 1
                    ELSE 2
                  END
                ELSE 3
              END ASC,

              CASE
                WHEN status = 'live' AND nav_visible = 1 THEN nav_order
                ELSE 999999
              END ASC,

              is_home DESC,
              slug ASC
        ");
        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public function listDeleted(): array
    {
        $stmt = $this->pdo->query("
            SELECT
              id, slug, title,
              status,
              is_home, is_deleted, deleted_at, updated_at
            FROM pages
            WHERE is_deleted = 1
            ORDER BY deleted_at DESC, slug ASC
        ");
        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public function countDeleted(): int
    {
        return (int)$this->pdo->query("SELECT COUNT(*) FROM pages WHERE is_deleted = 1")->fetchColumn();
    }

    // Legacy (falls noch irgendwo genutzt)
    public function listAll(): array
    {
        $stmt = $this->pdo->query("
            SELECT id, slug, title, is_home, is_deleted, deleted_at, updated_at
            FROM pages
            ORDER BY is_deleted ASC, slug ASC
        ");
        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public function listActiveForPicker(): array
    {
        $stmt = $this->pdo->query("
            SELECT slug, title
            FROM pages
            WHERE is_deleted = 0
            ORDER BY CASE WHEN slug='/' THEN 0 ELSE 1 END, slug ASC
        ");
        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT
              id, slug, title,
              frontend_title, subtitle, status,
              content_json,
              is_home, nav_visible, nav_label, nav_area, nav_order,
              is_deleted, deleted_at
            FROM pages
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function findActiveBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT
              id, slug, title,
              frontend_title, subtitle, status,
              content_json
            FROM pages
            WHERE slug = :s AND is_deleted = 0
            LIMIT 1
        ");
        $stmt->execute([':s' => $slug]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function findPublicBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT
              id, slug, title, frontend_title, subtitle, status, content_json,
              is_home, nav_visible, nav_label, nav_area, nav_order
            FROM pages
            WHERE slug = :s
              AND is_deleted = 0
              AND status = 'live'
            LIMIT 1
        ");
        $stmt->execute([':s' => $slug]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function findPublicHome(): ?array
    {
        $stmt = $this->pdo->query("
            SELECT
              id, slug, title, frontend_title, subtitle, status, content_json,
              is_home, nav_visible, nav_label, nav_area, nav_order
            FROM pages
            WHERE is_home = 1
              AND is_deleted = 0
              AND status = 'live'
            ORDER BY id ASC
            LIMIT 1
        ");
        if (!$stmt) return null;
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function listPublicNav(string $area): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
              slug,
              nav_label,
              title,
              frontend_title,
              nav_area,
              nav_order,
              is_home
            FROM pages
            WHERE is_deleted = 0
              AND status = 'live'
              AND nav_visible = 1
              AND (nav_area = :a OR nav_area = 'both')
            ORDER BY nav_order ASC, slug ASC
        ");
        $stmt->execute([':a' => $area]);
        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public function listNav(string $area): array
    {
        // $area: 'header' oder 'footer'
        $stmt = $this->pdo->prepare("
            SELECT
              id, slug, title, nav_label, nav_area, nav_order
            FROM pages
            WHERE is_deleted = 0
              AND status = 'live'
              AND nav_visible = 1
              AND (nav_area = :a OR nav_area = 'both')
            ORDER BY nav_order ASC, slug ASC
        ");
        $stmt->execute([':a' => $area]);
        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public function insert(
        string $slug,
        string $title,
        string $frontendTitle,
        string $subtitle,
        string $status,
        string $contentJson,
        bool $isHome,
        bool $navVisible,
        string $navLabel,
        string $navArea,
        int $navOrder
    ): int {
        $stmt = $this->pdo->prepare("
            INSERT INTO pages
              (slug, title, frontend_title, subtitle, status, content_json,
               is_home, nav_visible, nav_label, nav_area, nav_order,
               is_deleted, deleted_at)
            VALUES
              (:s, :t, :ft, :st, :status, CAST(:c AS JSON),
               :home, :nv, :nl, :na, :no,
               0, NULL)
        ");
        $stmt->execute([
            ':s'      => $slug,
            ':t'      => $title,
            ':ft'     => $frontendTitle,
            ':st'     => $subtitle,
            ':status' => $status,
            ':c'      => $contentJson,
            ':home'   => $isHome ? 1 : 0,
            ':nv'     => $navVisible ? 1 : 0,
            ':nl'     => $navLabel,
            ':na'     => $navArea,
            ':no'     => $navOrder,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function update(
        int $id,
        string $slug,
        string $title,
        string $frontendTitle,
        string $subtitle,
        string $status,
        string $contentJson,
        bool $isHome,
        bool $navVisible,
        string $navLabel,
        string $navArea,
        int $navOrder
    ): void {
        $stmt = $this->pdo->prepare("
            UPDATE pages
            SET
              slug = :s,
              title = :t,
              frontend_title = :ft,
              subtitle = :st,
              status = :status,
              content_json = CAST(:c AS JSON),
              is_home = :home,
              nav_visible = :nv,
              nav_label = :nl,
              nav_area = :na,
              nav_order = :no
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute([
            ':s'      => $slug,
            ':t'      => $title,
            ':ft'     => $frontendTitle,
            ':st'     => $subtitle,
            ':status' => $status,
            ':c'      => $contentJson,
            ':home'   => $isHome ? 1 : 0,
            ':nv'     => $navVisible ? 1 : 0,
            ':nl'     => $navLabel,
            ':na'     => $navArea,
            ':no'     => $navOrder,
            ':id'     => $id,
        ]);
    }

    public function setHome(int $id): void
    {
        $this->pdo->exec("UPDATE pages SET is_home = 0 WHERE is_deleted = 0");
        $stmt = $this->pdo->prepare("UPDATE pages SET is_home = 1 WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
    }

    public function softDelete(int $id): void
    {
        $randomSuffix = '_deleted_' . bin2hex(random_bytes(4));
        $stmt = $this->pdo->prepare("
            UPDATE pages
            SET is_deleted = 1,
                slug = CONCAT(slug, :suffix),
                deleted_at = NOW()
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute([
            ':id' => $id,
            ':suffix' => $randomSuffix,
        ]);
    }

    public function restore(int $id): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE pages
            SET is_deleted = 0, deleted_at = NULL
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
    }

    public function slugExists(string $slug, ?int $ignoreId = null): bool
    {
        if ($ignoreId !== null) {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM pages WHERE slug = :s AND id <> :id");
            $stmt->execute([':s' => $slug, ':id' => $ignoreId]);
        } else {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM pages WHERE slug = :s");
            $stmt->execute([':s' => $slug]);
        }
        return ((int)$stmt->fetchColumn()) > 0;
    }

    public function createRevision(int $pageId, string $title, string $contentJson, ?int $createdBy = null): void
    {
        $st = $this->pdo->prepare("
            INSERT INTO page_revisions (page_id, content_json, title, created_by, created_at)
            VALUES (:page_id, :content_json, :title, :created_by, NOW())
        ");
        $st->execute([
            ':page_id' => $pageId,
            ':content_json' => $contentJson,
            ':title' => $title,
            ':created_by' => $createdBy,
        ]);
    }

    public function listRevisions(int $pageId, int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        $st = $this->pdo->prepare("
            SELECT id, page_id, title, content_json, created_by, created_at
            FROM page_revisions
            WHERE page_id = :page_id
            ORDER BY created_at DESC, id DESC
            LIMIT {$limit}
        ");
        $st->execute([':page_id' => $pageId]);
        $rows = $st->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public function findRevision(int $pageId, int $revisionId): ?array
    {
        $st = $this->pdo->prepare("
            SELECT id, page_id, title, content_json, created_by, created_at
            FROM page_revisions
            WHERE page_id = :page_id
              AND id = :revision_id
            LIMIT 1
        ");
        $st->execute([
            ':page_id' => $pageId,
            ':revision_id' => $revisionId,
        ]);
        $row = $st->fetch();
        return is_array($row) ? $row : null;
    }

    public function pruneRevisions(int $pageId, int $keep = 50): void
    {
        $keep = max(1, min(500, $keep));
        $st = $this->pdo->prepare("
            DELETE FROM page_revisions
            WHERE page_id = :page_id
              AND id NOT IN (
                SELECT id FROM (
                  SELECT id
                  FROM page_revisions
                  WHERE page_id = :page_id_inner
                  ORDER BY created_at DESC, id DESC
                  LIMIT {$keep}
                ) latest
              )
        ");
        $st->execute([
            ':page_id' => $pageId,
            ':page_id_inner' => $pageId,
        ]);
    }

    public function purgeDeleted(): int
    {
        $st = $this->pdo->prepare("DELETE FROM pages WHERE is_deleted = 1");
        $st->execute();
        return (int)$st->rowCount();
    }
}
