<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class EventRepositoryDb
{
    private ?bool $supportsMultiCategoryDateRange = null;
    private ?bool $supportsCategoryLinks = null;
    private bool $schemaEnsured = false;

    public function __construct(private PDO $pdo) {}

    public function listActive(?int $categoryId = null, ?int $year = null): array
    {
        if (!$this->supportsMultiCategoryDateRange()) {
            return $this->listActiveLegacy($categoryId, $year);
        }

        $sql = "
            SELECT
              e.id, e.title, e.subtitle, e.description, e.event_date, e.event_date_from, e.event_date_to, e.image_media_id,
              e.youtube_url, e.sort_order, e.is_published, e.is_deleted, e.updated_at,
              GROUP_CONCAT(DISTINCT c.name ORDER BY c.sort_order ASC, c.name ASC SEPARATOR ', ') AS category_names,
              GROUP_CONCAT(DISTINCT c.slug ORDER BY c.sort_order ASC, c.slug ASC SEPARATOR ',') AS category_slugs
            FROM events e
            LEFT JOIN event_category_map ecm ON ecm.event_id = e.id
            LEFT JOIN event_categories c ON c.id = ecm.category_id AND c.is_deleted = 0
            WHERE e.is_deleted = 0
        ";
        $params = [];
        if ($categoryId !== null && $categoryId > 0) {
            $sql .= " AND EXISTS (
                SELECT 1
                FROM event_category_map x
                WHERE x.event_id = e.id AND x.category_id = :cid
            )";
            $params[':cid'] = $categoryId;
        }
        if ($year !== null && $year > 0) {
            $yearStart = sprintf('%04d-01-01', $year);
            $yearEnd = sprintf('%04d-12-31', $year);
            $sql .= " AND COALESCE(e.event_date_to, e.event_date_from, DATE(e.event_date)) IS NOT NULL
                AND COALESCE(e.event_date_from, DATE(e.event_date)) IS NOT NULL
                AND COALESCE(e.event_date_from, DATE(e.event_date)) <= :year_end
                AND COALESCE(e.event_date_to, e.event_date_from, DATE(e.event_date)) >= :year_start";
            $params[':year_start'] = $yearStart;
            $params[':year_end'] = $yearEnd;
        }
        $sql .= " GROUP BY e.id ORDER BY
            CASE
                WHEN COALESCE(e.event_date_to, e.event_date_from, DATE(e.event_date)) IS NOT NULL
                 AND COALESCE(e.event_date_to, e.event_date_from, DATE(e.event_date)) < CURDATE()
                THEN 1 ELSE 0
            END ASC,
            COALESCE(e.event_date_from, DATE(e.event_date)) ASC,
            e.id DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public function listDeleted(): array
    {
        if (!$this->supportsMultiCategoryDateRange()) {
            return $this->listDeletedLegacy();
        }

        $stmt = $this->pdo->query("
            SELECT
              e.id, e.title, e.subtitle, e.description, e.event_date, e.event_date_from, e.event_date_to, e.image_media_id,
              e.youtube_url, e.sort_order, e.is_published, e.is_deleted, e.updated_at,
              GROUP_CONCAT(DISTINCT c.name ORDER BY c.sort_order ASC, c.name ASC SEPARATOR ', ') AS category_names,
              GROUP_CONCAT(DISTINCT c.slug ORDER BY c.sort_order ASC, c.slug ASC SEPARATOR ',') AS category_slugs
            FROM events e
            LEFT JOIN event_category_map ecm ON ecm.event_id = e.id
            LEFT JOIN event_categories c ON c.id = ecm.category_id AND c.is_deleted = 0
            WHERE e.is_deleted = 1
            GROUP BY e.id
            ORDER BY e.updated_at DESC, e.id DESC
        ");
        $rows = $stmt ? $stmt->fetchAll() : [];
        return is_array($rows) ? $rows : [];
    }

    public function listAvailableYears(): array
    {
        if ($this->supportsMultiCategoryDateRange()) {
            $stmt = $this->pdo->query("
                SELECT DISTINCT YEAR(COALESCE(e.event_date_from, DATE(e.event_date))) AS y
                FROM events e
                WHERE e.is_deleted = 0
                  AND COALESCE(e.event_date_from, DATE(e.event_date)) IS NOT NULL
                ORDER BY y DESC
            ");
        } else {
            $stmt = $this->pdo->query("
                SELECT DISTINCT YEAR(e.event_date) AS y
                FROM events e
                WHERE e.is_deleted = 0
                  AND e.event_date IS NOT NULL
                ORDER BY y DESC
            ");
        }
        $rows = $stmt ? $stmt->fetchAll() : [];
        if (!is_array($rows)) {
            return [];
        }
        $years = [];
        foreach ($rows as $r) {
            $y = (int)($r['y'] ?? 0);
            if ($y > 0) {
                $years[] = $y;
            }
        }
        return array_values(array_unique($years));
    }

    public function countDeleted(): int
    {
        return (int)$this->pdo->query("SELECT COUNT(*) FROM events WHERE is_deleted = 1")->fetchColumn();
    }

    public function findById(int $id): ?array
    {
        if (!$this->supportsMultiCategoryDateRange()) {
            return $this->findByIdLegacy($id);
        }

        $stmt = $this->pdo->prepare("
            SELECT
              e.id, e.title, e.subtitle, e.description, e.event_date, e.event_date_from, e.event_date_to, e.image_media_id,
              e.youtube_url, e.sort_order, e.is_published, e.is_deleted,
              GROUP_CONCAT(DISTINCT c.name ORDER BY c.sort_order ASC, c.name ASC SEPARATOR ', ') AS category_names,
              GROUP_CONCAT(DISTINCT c.slug ORDER BY c.sort_order ASC, c.slug ASC SEPARATOR ',') AS category_slugs,
              GROUP_CONCAT(DISTINCT c.id ORDER BY c.sort_order ASC, c.id ASC SEPARATOR ',') AS category_ids_csv
            FROM events e
            LEFT JOIN event_category_map ecm ON ecm.event_id = e.id
            LEFT JOIN event_categories c ON c.id = ecm.category_id AND c.is_deleted = 0
            WHERE e.id = :id
            GROUP BY e.id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            return null;
        }
        $row['category_image_media_map'] = $this->loadCategoryImageMediaMap($id);
        $row['category_links_map'] = $this->loadCategoryLinksMap($id);
        return $row;
    }

    public function save(
        ?int $id,
        array $categoryIds,
        array $categoryImageMediaIds,
        array $categoryLinksMap,
        string $title,
        string $subtitle,
        string $description,
        ?string $eventDateFrom,
        ?string $eventDateTo,
        ?int $imageMediaId,
        string $youtubeUrl,
        int $sortOrder,
        bool $isPublished
    ): int {
        if (!$this->supportsMultiCategoryDateRange()) {
            $primaryCategoryId = $categoryIds[0] ?? null;
            $legacyDate = $eventDateFrom !== null ? ($eventDateFrom . ' 00:00:00') : null;
            return $this->saveLegacy(
                $id,
                $primaryCategoryId !== null ? (int)$primaryCategoryId : null,
                $title,
                $subtitle,
                $description,
                $legacyDate,
                $imageMediaId,
                $youtubeUrl,
                $sortOrder,
                $isPublished
            );
        }

        $categoryIds = array_values(array_unique(array_map(static fn($v): int => (int)$v, $categoryIds)));
        $categoryIds = array_values(array_filter($categoryIds, static fn(int $v): bool => $v > 0));
        $primaryCategoryId = $categoryIds[0] ?? null;

        if ($id !== null && $id > 0) {
            $stmt = $this->pdo->prepare("
                UPDATE events
                SET
                  category_id = :primary_category_id,
                  title = :title,
                  subtitle = :subtitle,
                  description = :description,
                  event_date_from = :event_date_from,
                  event_date_to = :event_date_to,
                  event_date = :event_date_legacy,
                  image_media_id = :image_media_id,
                  youtube_url = :youtube_url,
                  sort_order = :sort_order,
                  is_published = :is_published
                WHERE id = :id
                LIMIT 1
            ");
            $stmt->execute([
                ':id' => $id,
                ':primary_category_id' => $primaryCategoryId,
                ':title' => $title,
                ':subtitle' => $subtitle,
                ':description' => $description,
                ':event_date_from' => $eventDateFrom,
                ':event_date_to' => $eventDateTo,
                ':event_date_legacy' => $eventDateFrom !== null ? ($eventDateFrom . ' 00:00:00') : null,
                ':image_media_id' => $imageMediaId,
                ':youtube_url' => $youtubeUrl,
                ':sort_order' => $sortOrder,
                ':is_published' => $isPublished ? 1 : 0,
            ]);
            $this->syncCategories($id, $categoryIds);
            $this->syncCategoryImages($id, $categoryIds, $categoryImageMediaIds);
            $this->syncCategoryLinks($id, $categoryIds, $categoryLinksMap);
            return $id;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO events
              (category_id, title, subtitle, description, event_date_from, event_date_to, event_date, image_media_id, youtube_url, sort_order, is_published, is_deleted)
            VALUES
              (:primary_category_id, :title, :subtitle, :description, :event_date_from, :event_date_to, :event_date_legacy, :image_media_id, :youtube_url, :sort_order, :is_published, 0)
        ");
        $stmt->execute([
            ':primary_category_id' => $primaryCategoryId,
            ':title' => $title,
            ':subtitle' => $subtitle,
            ':description' => $description,
            ':event_date_from' => $eventDateFrom,
            ':event_date_to' => $eventDateTo,
            ':event_date_legacy' => $eventDateFrom !== null ? ($eventDateFrom . ' 00:00:00') : null,
            ':image_media_id' => $imageMediaId,
            ':youtube_url' => $youtubeUrl,
            ':sort_order' => $sortOrder,
            ':is_published' => $isPublished ? 1 : 0,
        ]);
        $newId = (int)$this->pdo->lastInsertId();
        $this->syncCategories($newId, $categoryIds);
        $this->syncCategoryImages($newId, $categoryIds, $categoryImageMediaIds);
        $this->syncCategoryLinks($newId, $categoryIds, $categoryLinksMap);
        return $newId;
    }

    public function softDelete(int $id): void
    {
        $stmt = $this->pdo->prepare("UPDATE events SET is_deleted = 1 WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
    }

    public function restore(int $id): void
    {
        $stmt = $this->pdo->prepare("UPDATE events SET is_deleted = 0 WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
    }

    public function purgeDeleted(): int
    {
        $stmt = $this->pdo->prepare("DELETE FROM events WHERE is_deleted = 1");
        $stmt->execute();
        return (int)$stmt->rowCount();
    }

    private function syncCategories(int $eventId, array $categoryIds): void
    {
        $del = $this->pdo->prepare("DELETE FROM event_category_map WHERE event_id = :id");
        $del->execute([':id' => $eventId]);

        if ($categoryIds === []) {
            return;
        }

        $ins = $this->pdo->prepare("
            INSERT IGNORE INTO event_category_map (event_id, category_id)
            VALUES (:event_id, :category_id)
        ");
        foreach ($categoryIds as $cid) {
            $ins->execute([
                ':event_id' => $eventId,
                ':category_id' => $cid,
            ]);
        }
    }

    private function syncCategoryImages(int $eventId, array $categoryIds, array $categoryImageMediaIds): void
    {
        if (!$this->supportsCategoryImageMap()) {
            return;
        }

        $allowedCategoryIds = array_values(array_unique(array_map(static fn($v): int => (int)$v, $categoryIds)));
        $allowedCategoryIds = array_values(array_filter($allowedCategoryIds, static fn(int $v): bool => $v > 0));
        $allowedLookup = array_fill_keys($allowedCategoryIds, true);

        $del = $this->pdo->prepare("DELETE FROM event_category_media WHERE event_id = :id");
        $del->execute([':id' => $eventId]);

        if ($allowedCategoryIds === []) {
            return;
        }

        $ins = $this->pdo->prepare("
            INSERT INTO event_category_media (event_id, category_id, media_id)
            VALUES (:event_id, :category_id, :media_id)
            ON DUPLICATE KEY UPDATE media_id = VALUES(media_id)
        ");

        foreach ($categoryImageMediaIds as $cidRaw => $midRaw) {
            $cid = (int)$cidRaw;
            $mid = (int)$midRaw;
            if ($cid <= 0 || $mid <= 0) {
                continue;
            }
            if (!isset($allowedLookup[$cid])) {
                continue;
            }
            $ins->execute([
                ':event_id' => $eventId,
                ':category_id' => $cid,
                ':media_id' => $mid,
            ]);
        }
    }

    private function loadCategoryImageMediaMap(int $eventId): array
    {
        if (!$this->supportsCategoryImageMap()) {
            return [];
        }
        $st = $this->pdo->prepare("
            SELECT category_id, media_id
            FROM event_category_media
            WHERE event_id = :event_id
        ");
        $st->execute([':event_id' => $eventId]);
        $rows = $st->fetchAll();
        $out = [];
        foreach (is_array($rows) ? $rows : [] as $r) {
            if (!is_array($r)) continue;
            $cid = (int)($r['category_id'] ?? 0);
            $mid = (int)($r['media_id'] ?? 0);
            if ($cid > 0 && $mid > 0) {
                $out[$cid] = $mid;
            }
        }
        return $out;
    }

    private function syncCategoryLinks(int $eventId, array $categoryIds, array $categoryLinksMap): void
    {
        if (!$this->supportsCategoryLinks()) {
            return;
        }

        $allowedCategoryIds = array_values(array_unique(array_map(static fn($v): int => (int)$v, $categoryIds)));
        $allowedCategoryIds = array_values(array_filter($allowedCategoryIds, static fn(int $v): bool => $v > 0));
        $allowedLookup = array_fill_keys($allowedCategoryIds, true);

        $del = $this->pdo->prepare("DELETE FROM event_category_links WHERE event_id = :id");
        $del->execute([':id' => $eventId]);

        if ($allowedCategoryIds === []) {
            return;
        }

        $ins = $this->pdo->prepare("
            INSERT INTO event_category_links (event_id, category_id, link_type, label, url, pdf_media_id, youtube_start_at, youtube_end_at, sort_order)
            VALUES (:event_id, :category_id, :link_type, :label, :url, :pdf_media_id, :youtube_start_at, :youtube_end_at, :sort_order)
        ");

        foreach ($categoryLinksMap as $cidRaw => $linksRaw) {
            $cid = (int)$cidRaw;
            if ($cid <= 0 || !isset($allowedLookup[$cid]) || !is_array($linksRaw)) {
                continue;
            }
            $order = 10;
            foreach ($linksRaw as $linkRow) {
                if (!is_array($linkRow)) {
                    continue;
                }
                $label = trim((string)($linkRow['label'] ?? ''));
                $url = trim((string)($linkRow['url'] ?? ''));
                $type = strtolower(trim((string)($linkRow['type'] ?? 'link')));
                if (!in_array($type, ['link', 'youtube', 'pdf'], true)) {
                    $type = 'link';
                }
                $pdfMediaId = (int)($linkRow['pdf_media_id'] ?? 0);
                $youtubeStartAt = trim((string)($linkRow['youtube_start_at'] ?? ''));
                $youtubeEndAt = trim((string)($linkRow['youtube_end_at'] ?? ''));
                if ($label === '' || $url === '') {
                    if (!($type === 'pdf' && $pdfMediaId > 0)) {
                        continue;
                    }
                }
                if ($url === '' && $type === 'pdf' && $pdfMediaId > 0) {
                    $url = '/media/file?id=' . $pdfMediaId;
                }
                if ($label === '' || $url === '') {
                    continue;
                }
                $ins->execute([
                    ':event_id' => $eventId,
                    ':category_id' => $cid,
                    ':link_type' => $type,
                    ':label' => mb_substr($label, 0, 120, 'UTF-8'),
                    ':url' => mb_substr($url, 0, 2048, 'UTF-8'),
                    ':pdf_media_id' => $pdfMediaId > 0 ? $pdfMediaId : null,
                    ':youtube_start_at' => $type === 'youtube' && $youtubeStartAt !== '' ? $youtubeStartAt : null,
                    ':youtube_end_at' => $type === 'youtube' && $youtubeEndAt !== '' ? $youtubeEndAt : null,
                    ':sort_order' => $order,
                ]);
                $order += 10;
            }
        }
    }

    private function loadCategoryLinksMap(int $eventId): array
    {
        if (!$this->supportsCategoryLinks()) {
            return [];
        }
        $st = $this->pdo->prepare("
            SELECT category_id, link_type, label, url, pdf_media_id, youtube_start_at, youtube_end_at, sort_order
            FROM event_category_links
            WHERE event_id = :event_id
            ORDER BY category_id ASC, sort_order ASC, id ASC
        ");
        $st->execute([':event_id' => $eventId]);
        $rows = $st->fetchAll();
        $out = [];
        foreach (is_array($rows) ? $rows : [] as $r) {
            if (!is_array($r)) continue;
            $cid = (int)($r['category_id'] ?? 0);
            $type = strtolower(trim((string)($r['link_type'] ?? 'link')));
            if (!in_array($type, ['link', 'youtube', 'pdf'], true)) {
                $type = 'link';
            }
            $label = trim((string)($r['label'] ?? ''));
            $url = trim((string)($r['url'] ?? ''));
            $pdfMediaId = (int)($r['pdf_media_id'] ?? 0);
            if ($url === '' && $type === 'pdf' && $pdfMediaId > 0) {
                $url = '/media/file?id=' . $pdfMediaId;
            }
            if ($cid <= 0 || $label === '' || $url === '') {
                continue;
            }
            if (!isset($out[$cid]) || !is_array($out[$cid])) {
                $out[$cid] = [];
            }
            $out[$cid][] = [
                'type' => $type,
                'label' => $label,
                'url' => $url,
                'pdf_media_id' => $pdfMediaId > 0 ? $pdfMediaId : 0,
                'youtube_start_at' => trim((string)($r['youtube_start_at'] ?? '')),
                'youtube_end_at' => trim((string)($r['youtube_end_at'] ?? '')),
                'sort_order' => (int)($r['sort_order'] ?? 0),
            ];
        }
        return $out;
    }

    private function supportsCategoryImageMap(): bool
    {
        return $this->tableExists('event_category_media');
    }

    private function supportsCategoryLinks(): bool
    {
        $this->ensureSchemaBestEffort();
        if ($this->supportsCategoryLinks !== null) {
            return $this->supportsCategoryLinks;
        }
        $this->supportsCategoryLinks = $this->tableExists('event_category_links');
        return $this->supportsCategoryLinks;
    }

    private function supportsMultiCategoryDateRange(): bool
    {
        $this->ensureSchemaBestEffort();

        if ($this->supportsMultiCategoryDateRange !== null) {
            return $this->supportsMultiCategoryDateRange;
        }

        $this->supportsMultiCategoryDateRange =
            $this->tableExists('event_category_map')
            && $this->columnExists('events', 'event_date_from')
            && $this->columnExists('events', 'event_date_to');

        return $this->supportsMultiCategoryDateRange;
    }

    private function ensureSchemaBestEffort(): void
    {
        if ($this->schemaEnsured) {
            return;
        }
        $this->schemaEnsured = true;

        try {
            if ($this->tableExists('events')) {
                if (!$this->columnExists('events', 'event_date_from')) {
                    $this->pdo->exec("ALTER TABLE `events` ADD COLUMN `event_date_from` DATE NULL AFTER `description`");
                }
                if (!$this->columnExists('events', 'event_date_to')) {
                    $this->pdo->exec("ALTER TABLE `events` ADD COLUMN `event_date_to` DATE NULL AFTER `event_date_from`");
                }
                if (!$this->columnExists('events', 'subtitle')) {
                    $this->pdo->exec("ALTER TABLE `events` ADD COLUMN `subtitle` VARCHAR(255) NULL AFTER `title`");
                }
                if ($this->columnExists('events', 'event_date_from') && $this->columnExists('events', 'event_date') && $this->columnExists('events', 'event_date_to')) {
                    $this->pdo->exec("
                        UPDATE `events`
                        SET
                          `event_date_from` = COALESCE(`event_date_from`, DATE(`event_date`)),
                          `event_date_to` = COALESCE(`event_date_to`, DATE(`event_date`))
                        WHERE `event_date` IS NOT NULL
                    ");
                }
            }
        } catch (\Throwable) {
            // Best effort only.
        }

        try {
            if (!$this->tableExists('event_category_map')) {
                $this->pdo->exec("
                    CREATE TABLE IF NOT EXISTS `event_category_map` (
                      `event_id` BIGINT UNSIGNED NOT NULL,
                      `category_id` BIGINT UNSIGNED NOT NULL,
                      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                      PRIMARY KEY (`event_id`, `category_id`),
                      KEY `idx_event_category_map_category` (`category_id`, `event_id`),
                      CONSTRAINT `fk_event_category_map_event`
                        FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE CASCADE,
                      CONSTRAINT `fk_event_category_map_category`
                        FOREIGN KEY (`category_id`) REFERENCES `event_categories`(`id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ");
            } else {
                $idx = $this->pdo->query("SHOW INDEX FROM `event_category_map`");
                $rows = $idx ? $idx->fetchAll(PDO::FETCH_ASSOC) : [];
                $primaryCols = [];
                $singleEventUniqueKeys = [];
                foreach (is_array($rows) ? $rows : [] as $r) {
                    if (!is_array($r)) {
                        continue;
                    }
                    $keyName = (string)($r['Key_name'] ?? '');
                    $col = (string)($r['Column_name'] ?? '');
                    $nonUnique = (int)($r['Non_unique'] ?? 1);
                    $seq = (int)($r['Seq_in_index'] ?? 0);
                    if ($keyName === 'PRIMARY') {
                        $primaryCols[$seq] = $col;
                    }
                    if ($nonUnique === 0 && $keyName !== 'PRIMARY' && $col === 'event_id') {
                        $singleEventUniqueKeys[$keyName] = true;
                    }
                }
                ksort($primaryCols);
                $primaryCols = array_values($primaryCols);
                $needsPkFix = !($primaryCols === ['event_id', 'category_id']);
                if ($needsPkFix) {
                    try {
                        $this->pdo->exec("ALTER TABLE `event_category_map` DROP PRIMARY KEY");
                    } catch (\Throwable) {
                        // Continue and try adding expected PK.
                    }
                    $this->pdo->exec("ALTER TABLE `event_category_map` ADD PRIMARY KEY (`event_id`, `category_id`)");
                }
                foreach (array_keys($singleEventUniqueKeys) as $keyName) {
                    $esc = str_replace('`', '``', $keyName);
                    try {
                        $this->pdo->exec("ALTER TABLE `event_category_map` DROP INDEX `$esc`");
                    } catch (\Throwable) {
                        // Ignore.
                    }
                }
            }
        } catch (\Throwable) {
            // Best effort only.
        }

        try {
            if (!$this->tableExists('event_category_links')) {
                $this->pdo->exec("
                    CREATE TABLE IF NOT EXISTS `event_category_links` (
                      `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                      `event_id` BIGINT UNSIGNED NOT NULL,
                      `category_id` BIGINT UNSIGNED NOT NULL,
                      `link_type` VARCHAR(20) NOT NULL DEFAULT 'link',
                      `label` VARCHAR(120) NOT NULL,
                      `url` VARCHAR(2048) NULL DEFAULT NULL,
                      `pdf_media_id` BIGINT UNSIGNED NULL DEFAULT NULL,
                      `youtube_start_at` DATETIME NULL DEFAULT NULL,
                      `youtube_end_at` DATETIME NULL DEFAULT NULL,
                      `sort_order` INT NOT NULL DEFAULT 10,
                      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                      `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                      PRIMARY KEY (`id`),
                      KEY `idx_event_category_links_event` (`event_id`, `category_id`, `sort_order`),
                      KEY `idx_event_category_links_pdf_media` (`pdf_media_id`),
                      CONSTRAINT `fk_event_category_links_event`
                        FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE CASCADE,
                      CONSTRAINT `fk_event_category_links_category`
                        FOREIGN KEY (`category_id`) REFERENCES `event_categories`(`id`) ON DELETE CASCADE,
                      CONSTRAINT `fk_event_category_links_pdf_media`
                        FOREIGN KEY (`pdf_media_id`) REFERENCES `media_items`(`id`) ON DELETE SET NULL
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ");
            } else {
                if (!$this->columnExists('event_category_links', 'link_type')) {
                    $this->pdo->exec("ALTER TABLE `event_category_links` ADD COLUMN `link_type` VARCHAR(20) NOT NULL DEFAULT 'link' AFTER `category_id`");
                }
                if (!$this->columnExists('event_category_links', 'pdf_media_id')) {
                    $this->pdo->exec("ALTER TABLE `event_category_links` ADD COLUMN `pdf_media_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `url`");
                }
                if (!$this->columnExists('event_category_links', 'youtube_start_at')) {
                    $this->pdo->exec("ALTER TABLE `event_category_links` ADD COLUMN `youtube_start_at` DATETIME NULL DEFAULT NULL AFTER `pdf_media_id`");
                }
                if (!$this->columnExists('event_category_links', 'youtube_end_at')) {
                    $this->pdo->exec("ALTER TABLE `event_category_links` ADD COLUMN `youtube_end_at` DATETIME NULL DEFAULT NULL AFTER `youtube_start_at`");
                }
            }
        } catch (\Throwable) {
            // Best effort only.
        }
    }

    private function tableExists(string $table): bool
    {
        try {
            $this->pdo->query("SELECT 1 FROM `$table` LIMIT 1");
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        $tableEsc = str_replace('`', '``', $table);
        $colEsc = str_replace('`', '``', $column);
        try {
            $this->pdo->query("SELECT `$colEsc` FROM `$tableEsc` LIMIT 0");
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function listActiveLegacy(?int $categoryId = null, ?int $year = null): array
    {
        $sql = "
            SELECT
              e.id, e.category_id, e.title, e.subtitle, e.description, e.event_date, e.image_media_id,
              e.youtube_url, e.sort_order, e.is_published, e.is_deleted, e.updated_at,
              c.name AS category_name, c.slug AS category_slug
            FROM events e
            LEFT JOIN event_categories c ON c.id = e.category_id
            WHERE e.is_deleted = 0
        ";
        $params = [];
        if ($categoryId !== null && $categoryId > 0) {
            $sql .= " AND e.category_id = :cid";
            $params[':cid'] = $categoryId;
        }
        if ($year !== null && $year > 0) {
            $yearStart = sprintf('%04d-01-01', $year);
            $yearEnd = sprintf('%04d-12-31', $year);
            $sql .= " AND e.event_date IS NOT NULL
                AND DATE(e.event_date) >= :year_start
                AND DATE(e.event_date) <= :year_end";
            $params[':year_start'] = $yearStart;
            $params[':year_end'] = $yearEnd;
        }
        $sql .= " ORDER BY
            CASE
                WHEN e.event_date IS NOT NULL AND DATE(e.event_date) < CURDATE()
                THEN 1 ELSE 0
            END ASC,
            e.event_date ASC,
            e.id DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    private function listDeletedLegacy(): array
    {
        $stmt = $this->pdo->query("
            SELECT
              e.id, e.category_id, e.title, e.subtitle, e.description, e.event_date, e.image_media_id,
              e.youtube_url, e.sort_order, e.is_published, e.is_deleted, e.updated_at,
              c.name AS category_name, c.slug AS category_slug
            FROM events e
            LEFT JOIN event_categories c ON c.id = e.category_id
            WHERE e.is_deleted = 1
            ORDER BY e.updated_at DESC, e.id DESC
        ");
        $rows = $stmt ? $stmt->fetchAll() : [];
        return is_array($rows) ? $rows : [];
    }

    private function findByIdLegacy(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT
              e.id, e.category_id, e.title, e.subtitle, e.description, e.event_date, e.image_media_id,
              e.youtube_url, e.sort_order, e.is_published, e.is_deleted,
              c.name AS category_name, c.slug AS category_slug
            FROM events e
            LEFT JOIN event_categories c ON c.id = e.category_id
            WHERE e.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            return null;
        }
        $categoryId = (int)($row['category_id'] ?? 0);
        $row['category_ids_csv'] = $categoryId > 0 ? (string)$categoryId : '';
        $row['event_date_from'] = (string)(isset($row['event_date']) && $row['event_date'] !== '' ? date('Y-m-d', (int)strtotime((string)$row['event_date'])) : '');
        $row['event_date_to'] = (string)$row['event_date_from'];
        $row['category_image_media_map'] = [];
        $row['category_links_map'] = [];
        return $row;
    }

    private function saveLegacy(
        ?int $id,
        ?int $categoryId,
        string $title,
        string $subtitle,
        string $description,
        ?string $eventDate,
        ?int $imageMediaId,
        string $youtubeUrl,
        int $sortOrder,
        bool $isPublished
    ): int {
        if ($id !== null && $id > 0) {
            $stmt = $this->pdo->prepare("
                UPDATE events
                SET
                  category_id = :category_id,
                  title = :title,
                  subtitle = :subtitle,
                  description = :description,
                  event_date = :event_date,
                  image_media_id = :image_media_id,
                  youtube_url = :youtube_url,
                  sort_order = :sort_order,
                  is_published = :is_published
                WHERE id = :id
                LIMIT 1
            ");
            $stmt->execute([
                ':id' => $id,
                ':category_id' => $categoryId,
                ':title' => $title,
                ':subtitle' => $subtitle,
                ':description' => $description,
                ':event_date' => $eventDate,
                ':image_media_id' => $imageMediaId,
                ':youtube_url' => $youtubeUrl,
                ':sort_order' => $sortOrder,
                ':is_published' => $isPublished ? 1 : 0,
            ]);
            return $id;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO events
              (category_id, title, subtitle, description, event_date, image_media_id, youtube_url, sort_order, is_published, is_deleted)
            VALUES
              (:category_id, :title, :subtitle, :description, :event_date, :image_media_id, :youtube_url, :sort_order, :is_published, 0)
        ");
        $stmt->execute([
            ':category_id' => $categoryId,
            ':title' => $title,
            ':subtitle' => $subtitle,
            ':description' => $description,
            ':event_date' => $eventDate,
            ':image_media_id' => $imageMediaId,
            ':youtube_url' => $youtubeUrl,
            ':sort_order' => $sortOrder,
            ':is_published' => $isPublished ? 1 : 0,
        ]);
        return (int)$this->pdo->lastInsertId();
    }
}
