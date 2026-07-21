<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\MediaUsageRepositoryDb;
use App\Repositories\MediaRepositoryDb;
use PDO;

final class MediaUsageService
{
    public function __construct(
        private PDO $pdo,
        private MediaUsageRepositoryDb $usageRepo,
        private MediaRepositoryDb $mediaRepo
    ) {}

    /**
     * Synchronisiert Media-Usages für eine Seite anhand content_json.
     * Minimalinvasiv: erkennt nur kanonische Media-URLs aus dem MediaManager:
     *   /media/file?id=123
     *   /media/thumb?id=123
     *
     * @param int $pageId
     * @param string $contentJson
     */
    public function syncPageUsages(int $pageId, string $contentJson): void
    {
        if ($pageId <= 0) return;

        $data = json_decode($contentJson, true);
        if ($data === null && trim($contentJson) !== '' && strtolower(trim($contentJson)) !== 'null') {
            // invalid JSON -> nichts syncen (nicht kaputtmachen)
            return;
        }

        // Welche Media-IDs waren vorher für diese Seite verknüpft? (damit wir usage_count für "alte" IDs korrekt auf 0 setzen können)
        $beforeIds = $this->listDistinctMediaIdsForEntity('page', $pageId);

        // Neue Usages extrahieren
        $rows = [];
        $this->scanForMediaUrls($data, '', $rows);

        // dedupe
        $uniq = [];
        foreach ($rows as $r) {
            $mid = (int)($r['media_id'] ?? 0);
            $fk  = (string)($r['field_key'] ?? '');
            if ($mid <= 0 || $fk === '') continue;
            $uniq[$mid . '|' . $fk] = ['media_id' => $mid, 'field_key' => $fk];
        }
        $rows = array_values($uniq);

        $this->pdo->beginTransaction();
        try {
            $this->usageRepo->deleteForEntity('page', $pageId);
            $this->usageRepo->insertIgnoreBulk('page', $pageId, $rows);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        // usage_count Cache aktualisieren (betroffene media_ids: vorher + nachher)
        $afterIds = [];
        foreach ($rows as $r) $afterIds[] = (int)$r['media_id'];

        $all = array_values(array_unique(array_filter(array_merge($beforeIds, $afterIds), fn($x) => (int)$x > 0)));
        if (!$all) return;

        $counts = $this->usageRepo->countForMediaBulk($all);

        foreach ($all as $mid) {
            $c = (int)($counts[$mid] ?? 0);
            $this->mediaRepo->setUsageCount((int)$mid, $c);
        }
    }
    
    /**
     * Synchronisiert Media-Usages für globale Site-Settings (site_settings Tabelle).
     * Erkennt alle Keys, die auf "_media_id" enden und einen numerischen Wert > 0 haben.
     *
     * @param array<string,mixed> $settings
     */
    public function syncSiteSettingsUsages(array $settings): void
    {
        // globales Singleton-Entity (damit deleteForEntity greift)
        $entityType = 'site_settings';
        $entityId   = 1;

        // Vorher verknüpfte Media-IDs merken (für usage_count Recalc)
        $beforeIds = $this->listDistinctMediaIdsForEntity($entityType, $entityId);

        $rows = [];
        foreach ($settings as $k => $v) {
            $key = trim((string)$k);
            if ($key === '') continue;
            if (!str_ends_with($key, '_media_id')) continue;

            $id = (int)$v;
            if ($id <= 0) continue;

            $rows[] = ['media_id' => $id, 'field_key' => $key];
        }

        // dedupe
        $uniq = [];
        foreach ($rows as $r) {
            $mid = (int)($r['media_id'] ?? 0);
            $fk  = (string)($r['field_key'] ?? '');
            if ($mid <= 0 || $fk === '') continue;
            $uniq[$mid . '|' . $fk] = ['media_id' => $mid, 'field_key' => $fk];
        }
        $rows = array_values($uniq);

        $this->pdo->beginTransaction();
        try {
            $this->usageRepo->deleteForEntity($entityType, $entityId);
            $this->usageRepo->insertIgnoreBulk($entityType, $entityId, $rows);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        $afterIds = [];
        foreach ($rows as $r) $afterIds[] = (int)$r['media_id'];

        $all = array_values(array_unique(array_filter(array_merge($beforeIds, $afterIds), fn($x) => (int)$x > 0)));
        if (!$all) return;

        $counts = $this->usageRepo->countForMediaBulk($all);
        foreach ($all as $mid) {
            $c = (int)($counts[$mid] ?? 0);
            $this->mediaRepo->setUsageCount((int)$mid, $c);
        }
    }

    /**
     * Synchronisiert Media-Usages für ein Event (Hauptbild + Kategorie-Bilder).
     *
     * @param array<int,int> $categoryImageMediaIds category_id => media_id
     */
    public function syncEventUsages(int $eventId, array $categoryImageMediaIds = [], array $categoryPdfMediaIds = []): void
    {
        if ($eventId <= 0) return;

        $entityType = 'event';
        $entityId = $eventId;
        $beforeIds = $this->listDistinctMediaIdsForEntity($entityType, $entityId);

        $rows = [];
        foreach ($categoryImageMediaIds as $categoryId => $mediaId) {
            $cid = (int)$categoryId;
            $mid = (int)$mediaId;
            if ($cid <= 0 || $mid <= 0) continue;
            $rows[] = ['media_id' => $mid, 'field_key' => 'category_image_media_ids[' . $cid . ']'];
        }
        foreach ($categoryPdfMediaIds as $categoryId => $mediaIds) {
            $cid = (int)$categoryId;
            if ($cid <= 0 || !is_array($mediaIds)) continue;
            foreach (array_values($mediaIds) as $index => $mediaId) {
                $mid = (int)$mediaId;
                if ($mid <= 0) continue;
                $rows[] = ['media_id' => $mid, 'field_key' => 'category_links[' . $cid . '][pdf_media_id][' . (int)$index . ']'];
            }
        }

        $rows = $this->dedupeRows($rows);

        $this->pdo->beginTransaction();
        try {
            $this->usageRepo->deleteForEntity($entityType, $entityId);
            $this->usageRepo->insertIgnoreBulk($entityType, $entityId, $rows);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        $afterIds = [];
        foreach ($rows as $r) $afterIds[] = (int)$r['media_id'];
        $this->recalcUsageCounts(array_values(array_unique(array_filter(array_merge($beforeIds, $afterIds), fn($x) => (int)$x > 0))));
    }

    /**
     * Entfernt alle Media-Usages eines Entities und recalculates usage_count.
     */
    public function clearEntityUsages(string $entityType, int $entityId): void
    {
        $entityType = trim($entityType);
        if ($entityType === '' || $entityId <= 0) return;

        $beforeIds = $this->listDistinctMediaIdsForEntity($entityType, $entityId);
        if ($beforeIds === []) {
            return;
        }

        $this->pdo->beginTransaction();
        try {
            $this->usageRepo->deleteForEntity($entityType, $entityId);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        $this->recalcUsageCounts($beforeIds);
    }

    /**
     * Baut alle Event-Usages vollständig neu auf (aktive Events).
     */
    public function rebuildAllEventUsages(): void
    {
        $beforeIds = $this->listDistinctMediaIdsForEntityType('event');

        $rowsByEvent = [];
        $st = $this->pdo->query("SELECT id FROM events WHERE is_deleted = 0");
        $events = $st ? $st->fetchAll() : [];
        foreach (is_array($events) ? $events : [] as $r) {
            if (!is_array($r)) continue;
            $eid = (int)($r['id'] ?? 0);
            if ($eid <= 0) continue;
            $rowsByEvent[$eid] = $rowsByEvent[$eid] ?? [];
        }

        if ($this->tableExists('event_category_media')) {
            $st2 = $this->pdo->query("SELECT event_id, category_id, media_id FROM event_category_media");
            $rows = $st2 ? $st2->fetchAll() : [];
            foreach (is_array($rows) ? $rows : [] as $r) {
                if (!is_array($r)) continue;
                $eid = (int)($r['event_id'] ?? 0);
                $cid = (int)($r['category_id'] ?? 0);
                $mid = (int)($r['media_id'] ?? 0);
                if ($eid <= 0 || $cid <= 0 || $mid <= 0) continue;
                $rowsByEvent[$eid][] = [
                    'media_id' => $mid,
                    'field_key' => 'category_image_media_ids[' . $cid . ']',
                ];
            }
        }

        if ($this->tableExists('event_category_links') && $this->columnExists('event_category_links', 'pdf_media_id')) {
            $st3 = $this->pdo->query("
                SELECT event_id, category_id, pdf_media_id
                FROM event_category_links
                WHERE pdf_media_id IS NOT NULL
                ORDER BY event_id ASC, category_id ASC, sort_order ASC, id ASC
            ");
            $rows = $st3 ? $st3->fetchAll() : [];
            $pdfIndexByEventCategory = [];
            foreach (is_array($rows) ? $rows : [] as $r) {
                if (!is_array($r)) continue;
                $eid = (int)($r['event_id'] ?? 0);
                $cid = (int)($r['category_id'] ?? 0);
                $mid = (int)($r['pdf_media_id'] ?? 0);
                if ($eid <= 0 || $cid <= 0 || $mid <= 0) continue;
                $bucket = $eid . ':' . $cid;
                $index = (int)($pdfIndexByEventCategory[$bucket] ?? 0);
                $rowsByEvent[$eid][] = [
                    'media_id' => $mid,
                    'field_key' => 'category_links[' . $cid . '][pdf_media_id][' . $index . ']',
                ];
                $pdfIndexByEventCategory[$bucket] = $index + 1;
            }
        }

        foreach ($rowsByEvent as $eid => $rows) {
            $rowsByEvent[$eid] = $this->dedupeRows($rows);
        }

        $this->pdo->beginTransaction();
        try {
            $del = $this->pdo->prepare("DELETE FROM media_usages WHERE entity_type = :t");
            $del->execute([':t' => 'event']);
            foreach ($rowsByEvent as $eid => $rows) {
                if ($rows === []) continue;
                $this->usageRepo->insertIgnoreBulk('event', (int)$eid, $rows);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        $afterIds = [];
        foreach ($rowsByEvent as $rows) {
            foreach ($rows as $r) {
                $afterIds[] = (int)($r['media_id'] ?? 0);
            }
        }
        $this->recalcUsageCounts(array_values(array_unique(array_filter(array_merge($beforeIds, $afterIds), fn($x) => (int)$x > 0))));
    }

    /**
     * Synchronisiert Media-Usages für eine Event-Kategorie (Serien-Logo).
     */
    public function syncEventCategoryUsage(int $categoryId, ?int $logoMediaId): void
    {
        if ($categoryId <= 0) return;

        $entityType = 'event_category';
        $entityId = $categoryId;
        $beforeIds = $this->listDistinctMediaIdsForEntity($entityType, $entityId);

        $rows = [];
        $logoId = (int)$logoMediaId;
        if ($logoId > 0) {
            $rows[] = ['media_id' => $logoId, 'field_key' => 'logo_media_id'];
        }
        $rows = $this->dedupeRows($rows);

        $this->pdo->beginTransaction();
        try {
            $this->usageRepo->deleteForEntity($entityType, $entityId);
            $this->usageRepo->insertIgnoreBulk($entityType, $entityId, $rows);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        $afterIds = [];
        foreach ($rows as $r) $afterIds[] = (int)($r['media_id'] ?? 0);
        $this->recalcUsageCounts(array_values(array_unique(array_filter(array_merge($beforeIds, $afterIds), fn($x) => (int)$x > 0))));
    }

    /**
     * Baut alle Event-Kategorie-Usages vollständig neu auf (aktive Kategorien).
     */
    public function rebuildAllEventCategoryUsages(): void
    {
        $beforeIds = $this->listDistinctMediaIdsForEntityType('event_category');

        if (!$this->tableExists('event_categories') || !$this->columnExists('event_categories', 'logo_media_id')) {
            return;
        }

        $rowsByCategory = [];
        $st = $this->pdo->query("SELECT id, logo_media_id FROM event_categories WHERE is_deleted = 0");
        $rows = $st ? $st->fetchAll() : [];
        foreach (is_array($rows) ? $rows : [] as $r) {
            if (!is_array($r)) continue;
            $cid = (int)($r['id'] ?? 0);
            if ($cid <= 0) continue;
            $rowsByCategory[$cid] = [];
            $logoId = (int)($r['logo_media_id'] ?? 0);
            if ($logoId > 0) {
                $rowsByCategory[$cid][] = ['media_id' => $logoId, 'field_key' => 'logo_media_id'];
            }
            $rowsByCategory[$cid] = $this->dedupeRows($rowsByCategory[$cid]);
        }

        $this->pdo->beginTransaction();
        try {
            $del = $this->pdo->prepare("DELETE FROM media_usages WHERE entity_type = :t");
            $del->execute([':t' => 'event_category']);
            foreach ($rowsByCategory as $cid => $usageRows) {
                if ($usageRows === []) continue;
                $this->usageRepo->insertIgnoreBulk('event_category', (int)$cid, $usageRows);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        $afterIds = [];
        foreach ($rowsByCategory as $usageRows) {
            foreach ($usageRows as $ur) {
                $afterIds[] = (int)($ur['media_id'] ?? 0);
            }
        }
        $this->recalcUsageCounts(array_values(array_unique(array_filter(array_merge($beforeIds, $afterIds), fn($x) => (int)$x > 0))));
    }

    /**
     * Rekursiver Scan (Arrays/Strings) nach /media/file?id=123 bzw /media/thumb?id=123
     *
     * @param mixed $node
     * @param string $path
     * @param array<int,array{media_id:int,field_key:string}> $out
     */
    private function scanForMediaUrls(mixed $node, string $path, array &$out): void
    {
        if (is_string($node)) {
            $mid = $this->extractMediaIdFromUrl($node);
            if ($mid > 0) {
                $out[] = ['media_id' => $mid, 'field_key' => $path !== '' ? $path : 'content'];
            }
            return;
        }

        if (is_array($node)) {
            foreach ($node as $k => $v) {
                $key = is_int($k) ? '[' . $k . ']' : (string)$k;
                $next = ($path === '') ? $key : ($path . '.' . $key);
                $this->scanForMediaUrls($v, $next, $out);
            }
        }
    }

    private function extractMediaIdFromUrl(string $s): int
    {
        // canonical:
        // /media/file?id=123
        // /media/thumb?id=123
        if (preg_match('~\/media\/(file|thumb)\?[^#]*\bid\s*=\s*([0-9]+)~i', $s, $m)) {
            return (int)$m[2];
        }
        return 0;
    }

    /**
     * @param array<int,array{media_id:int,field_key:string}> $rows
     * @return array<int,array{media_id:int,field_key:string}>
     */
    private function dedupeRows(array $rows): array
    {
        $uniq = [];
        foreach ($rows as $r) {
            $mid = (int)($r['media_id'] ?? 0);
            $fk = (string)($r['field_key'] ?? '');
            if ($mid <= 0 || $fk === '') continue;
            $uniq[$mid . '|' . $fk] = ['media_id' => $mid, 'field_key' => $fk];
        }
        return array_values($uniq);
    }

    /**
     * @param array<int,int> $mediaIds
     */
    private function recalcUsageCounts(array $mediaIds): void
    {
        $all = array_values(array_unique(array_filter($mediaIds, fn($x) => (int)$x > 0)));
        if ($all === []) return;

        $counts = $this->usageRepo->countForMediaBulk($all);
        foreach ($all as $mid) {
            $c = (int)($counts[$mid] ?? 0);
            $this->mediaRepo->setUsageCount((int)$mid, $c);
        }
    }

    /**
     * @return array<int,int>
     */
    private function listDistinctMediaIdsForEntity(string $entityType, int $entityId): array
    {
        $entityType = trim($entityType);
        if ($entityType === '' || $entityId <= 0) return [];

        $st = $this->pdo->prepare("
            SELECT DISTINCT media_id
            FROM media_usages
            WHERE entity_type = :t AND entity_id = :id
        ");
        $st->execute([':t' => $entityType, ':id' => $entityId]);
        $rows = $st->fetchAll();

        $out = [];
        if (is_array($rows)) {
            foreach ($rows as $r) {
                if (!is_array($r)) continue;
                $mid = (int)($r['media_id'] ?? 0);
                if ($mid > 0) $out[] = $mid;
            }
        }
        return array_values(array_unique($out));
    }

    /**
     * @return array<int,int>
     */
    private function listDistinctMediaIdsForEntityType(string $entityType): array
    {
        $entityType = trim($entityType);
        if ($entityType === '') return [];

        $st = $this->pdo->prepare("
            SELECT DISTINCT media_id
            FROM media_usages
            WHERE entity_type = :t
        ");
        $st->execute([':t' => $entityType]);
        $rows = $st->fetchAll();

        $out = [];
        if (is_array($rows)) {
            foreach ($rows as $r) {
                if (!is_array($r)) continue;
                $mid = (int)($r['media_id'] ?? 0);
                if ($mid > 0) $out[] = $mid;
            }
        }
        return array_values(array_unique($out));
    }

    private function tableExists(string $table): bool
    {
        $table = trim($table);
        if ($table === '') return false;
        $st = $this->pdo->prepare("SHOW TABLES LIKE :t");
        $st->execute([':t' => $table]);
        return (bool)$st->fetchColumn();
    }

    private function columnExists(string $table, string $column): bool
    {
        $table = trim($table);
        $column = trim($column);
        if ($table === '' || $column === '') return false;
        $st = $this->pdo->prepare("SHOW COLUMNS FROM `$table` LIKE :c");
        $st->execute([':c' => $column]);
        return (bool)$st->fetchColumn();
    }
}
