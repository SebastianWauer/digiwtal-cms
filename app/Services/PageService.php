<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\PageRepositoryInterface;

final class PageService
{
    private const MAX_CONTENT_JSON_BYTES = 10_000_000; // 10 MB

    public function __construct(private PageRepositoryInterface $repo) {}

    public function normalizeSlug(string $slug): string
    {
        $slug = trim($slug);
        if ($slug === '') return '/';

        $slug = parse_url($slug, PHP_URL_PATH) ?: $slug;
        $slug = trim((string)$slug);
        if ($slug === '') return '/';

        if ($slug[0] !== '/') $slug = '/' . $slug;
        if ($slug !== '/') $slug = rtrim($slug, '/');

        return $slug === '' ? '/' : $slug;
    }

    // Basic slugify (de-tauglich)
    private function slugifyToken(string $text): string
    {
        $t = trim($text);
        if ($t === '') return '';
        $t = mb_strtolower($t);
        $t = str_replace(['ä','ö','ü','ß'], ['ae','oe','ue','ss'], $t);
        $t = preg_replace('/[^a-z0-9]+/u', '-', $t) ?? $t;
        return trim($t, '-');
    }

    private function slugFromTitle(string $title): string
    {
        $t = $this->slugifyToken($title);

        if ($t === '' || $t === 'home' || $t === 'startseite') {
            // NICHT automatisch "/" machen (das soll bewusst über "Startseite" laufen)
            // Wir nehmen einen harmlosen Standard, damit es nicht kollidiert.
            $t = 'home';
        }

        return '/' . $t;
    }

    /** Eigenes Pfadstück (ohne "/") für eine Unterseite, aus dem Titel abgeleitet. */
    private function segmentFromTitle(string $title): string
    {
        $t = $this->slugifyToken($title);
        return $t === '' ? 'seite' : $t;
    }

    /**
     * Baut die volle URL aus Elternseite + eigenem Pfadstück.
     * $hierarchyById ist die per id indizierte listHierarchy()-Ausgabe.
     */
    private function buildFullSlug(?int $parentId, string $segment, array $hierarchyById): string
    {
        $seg = trim($segment, '/');
        if ($seg === '') $seg = 'seite';

        if ($parentId === null) {
            return $this->normalizeSlug('/' . $seg);
        }

        $parent = $hierarchyById[$parentId] ?? null;
        $parentSlug = is_array($parent) ? rtrim((string)($parent['slug'] ?? '/'), '/') : '';

        return $parentSlug . '/' . $seg;
    }

    /** @return int[] IDs aller Nachkommen (rekursiv) einer Seite. */
    private function collectDescendantIds(int $id, array $hierarchyRows): array
    {
        $childrenByParent = [];
        foreach ($hierarchyRows as $row) {
            $pid = $row['parent_id'] ?? null;
            if ($pid === null) continue;
            $childrenByParent[(int)$pid][] = (int)$row['id'];
        }

        $result = [];
        $queue = $childrenByParent[$id] ?? [];
        while ($queue !== []) {
            $childId = array_shift($queue);
            if (isset($result[$childId])) continue;
            $result[$childId] = true;
            foreach ($childrenByParent[$childId] ?? [] as $grandchild) {
                $queue[] = $grandchild;
            }
        }

        return array_keys($result);
    }

    /** Zieht eine neu berechnete volle URL auf alle Nachkommen durch. */
    private function cascadeSlugToDescendants(int $id, string $newSlug, array $hierarchyRows): void
    {
        $childrenByParent = [];
        foreach ($hierarchyRows as $row) {
            $pid = $row['parent_id'] ?? null;
            if ($pid === null) continue;
            $childrenByParent[(int)$pid][] = $row;
        }

        $queue = [[$id, $newSlug]];
        while ($queue !== []) {
            [$parentId, $parentSlug] = array_shift($queue);
            foreach ($childrenByParent[$parentId] ?? [] as $child) {
                $childSlug = rtrim($parentSlug, '/') . '/' . trim((string)$child['slug_segment'], '/');
                $this->repo->updateSlugOnly((int)$child['id'], $childSlug);
                $queue[] = [(int)$child['id'], $childSlug];
            }
        }
    }

    /**
     * @return array{ok:bool, flash:array|null, id:int}
     */
    public function save(
        ?int $id,
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
        int $navOrder,
        ?int $parentId = null,
        string $redirectType = 'none',
        ?int $redirectTargetPageId = null,
        ?string $redirectTargetUrl = null,
        ?int $createdBy = null
    ): array {
        $title = trim($title);
        if ($title === '') {
            return ['ok' => false, 'flash' => ['type'=>'error','msg'=>'Titel darf nicht leer sein.'], 'id' => (int)($id ?? 0)];
        }

        $hierarchyRows = $this->repo->listHierarchy();
        $hierarchyById = [];
        foreach ($hierarchyRows as $row) {
            $hierarchyById[(int)$row['id']] = $row;
        }

        if ($isHome) {
            $parentId = null; // Startseite ist immer oberste Ebene
        }
        if ($parentId !== null) {
            if ($id !== null && $parentId === $id) {
                return ['ok' => false, 'flash' => ['type'=>'error','msg'=>'Eine Seite kann nicht ihre eigene Elternseite sein.'], 'id' => (int)($id ?? 0)];
            }
            if (!isset($hierarchyById[$parentId])) {
                return ['ok' => false, 'flash' => ['type'=>'error','msg'=>'Übergeordnete Seite wurde nicht gefunden.'], 'id' => (int)($id ?? 0)];
            }
            if ($id !== null && in_array($parentId, $this->collectDescendantIds($id, $hierarchyRows), true)) {
                return ['ok' => false, 'flash' => ['type'=>'error','msg'=>'Übergeordnete Seite darf keine Unterseite dieser Seite sein.'], 'id' => (int)($id ?? 0)];
            }
        }

        $redirectType = trim($redirectType) !== '' ? trim($redirectType) : 'none';
        if (!in_array($redirectType, ['none', 'page', 'url'], true)) {
            return ['ok' => false, 'flash' => ['type'=>'error','msg'=>'Weiterleitungstyp ist ungültig.'], 'id' => (int)($id ?? 0)];
        }
        if ($redirectType === 'page') {
            if ($redirectTargetPageId === null || $redirectTargetPageId <= 0) {
                return ['ok' => false, 'flash' => ['type'=>'error','msg'=>'Bitte eine Ziel-Seite für die Weiterleitung wählen.'], 'id' => (int)($id ?? 0)];
            }
            if ($id !== null && $redirectTargetPageId === $id) {
                return ['ok' => false, 'flash' => ['type'=>'error','msg'=>'Eine Seite kann nicht auf sich selbst weiterleiten.'], 'id' => (int)($id ?? 0)];
            }
            if (!isset($hierarchyById[$redirectTargetPageId])) {
                return ['ok' => false, 'flash' => ['type'=>'error','msg'=>'Ziel-Seite der Weiterleitung wurde nicht gefunden.'], 'id' => (int)($id ?? 0)];
            }
            $redirectTargetUrl = null;
        } elseif ($redirectType === 'url') {
            $redirectTargetUrl = trim((string)$redirectTargetUrl);
            if ($redirectTargetUrl === '' || preg_match('#^(https?://|/)#i', $redirectTargetUrl) !== 1) {
                return ['ok' => false, 'flash' => ['type'=>'error','msg'=>'Weiterleitungs-URL muss mit "http://", "https://" oder "/" beginnen.'], 'id' => (int)($id ?? 0)];
            }
            $redirectTargetPageId = null;
        } else {
            $redirectTargetPageId = null;
            $redirectTargetUrl = null;
        }

        // ✅ Auto-Fallbacks
        $slugSegment = trim($slug, " \t\n\r\0\x0B/");
        if ($slugSegment === '') {
            $slugSegment = $parentId === null
                ? ltrim($this->slugFromTitle($title), '/')
                : $this->segmentFromTitle($title);
        }
        $slug = $this->normalizeSlug($this->buildFullSlug($parentId, $slugSegment, $hierarchyById));

        $frontendTitle = trim($frontendTitle);
        if ($frontendTitle === '') $frontendTitle = $title;

        $subtitle = trim($subtitle);

        $navLabel = trim($navLabel);
        if ($navVisible && $navLabel === '') $navLabel = $title;

        $status = trim($status);
        if ($status === '') $status = 'live';
        if (!in_array($status, ['live','draft'], true)) {
            return ['ok' => false, 'flash' => ['type'=>'error','msg'=>'Status ist ungültig.'], 'id' => (int)($id ?? 0)];
        }

        // Validiert + normalisiert PageBuilder JSON (unbekannte Blöcke/Felder entfernen, Strings trimmen)
        $contentJson = (new \App\PageBuilder\BlockValidator(new \App\PageBuilder\BlockRegistry()))->validateJson($contentJson);

        if (strlen($contentJson) > self::MAX_CONTENT_JSON_BYTES) {
            return [
                'ok' => false,
                'flash' => ['type' => 'error', 'msg' => 'Inhalt zu groß (max. 10 MB).'],
                'id' => (int)($id ?? 0),
            ];
        }

        $decoded = json_decode($contentJson, true);
        if (!is_array($decoded)) {
            return ['ok' => false, 'flash' => ['type'=>'error','msg'=>'Inhalt ist kein gültiges JSON.'], 'id' => (int)($id ?? 0)];
        }

        $navArea  = trim($navArea) !== '' ? trim($navArea) : 'header';
        if (!in_array($navArea, ['header','footer','both'], true)) {
            return ['ok' => false, 'flash' => ['type'=>'error','msg'=>'Navigation-Bereich ist ungültig.'], 'id' => (int)($id ?? 0)];
        }
        if ($navOrder < 0) $navOrder = 0;

        if ($this->repo->slugExists($slug, $id)) {
            return ['ok' => false, 'flash' => ['type'=>'error','msg'=>'Slug ist bereits vergeben.'], 'id' => (int)($id ?? 0)];
        }

        if ($id === null || $id <= 0) {
            $newId = $this->repo->insert(
                $slug,
                $title,
                $frontendTitle,
                $subtitle,
                $status,
                $contentJson,
                $isHome,
                $navVisible,
                $navLabel,
                $navArea,
                $navOrder
            );
            $this->repo->setHierarchyAndRedirect($newId, $parentId, $slugSegment, $redirectType, $redirectTargetPageId, $redirectTargetUrl);
            $this->repo->createRevision($newId, $title, $contentJson, $createdBy);
            $this->repo->pruneRevisions($newId, 50);
            if ($isHome) $this->repo->setHome($newId);
            \App\Core\Hooks::do_action('cms_after_page_save', $newId, $slug);
            return ['ok' => true, 'flash' => ['type'=>'ok','msg'=>'Seite angelegt.'], 'id' => $newId];
        }

        $this->repo->update(
            $id,
            $slug,
            $title,
            $frontendTitle,
            $subtitle,
            $status,
            $contentJson,
            $isHome,
            $navVisible,
            $navLabel,
            $navArea,
            $navOrder
        );
        $this->repo->setHierarchyAndRedirect($id, $parentId, $slugSegment, $redirectType, $redirectTargetPageId, $redirectTargetUrl);
        $this->cascadeSlugToDescendants($id, $slug, $hierarchyRows);
        $this->repo->createRevision($id, $title, $contentJson, $createdBy);
        $this->repo->pruneRevisions($id, 50);
        if ($isHome) $this->repo->setHome($id);

        \App\Core\Hooks::do_action('cms_after_page_save', $id, $slug);
        return ['ok' => true, 'flash' => ['type'=>'ok','msg'=>'Seite gespeichert.'], 'id' => $id];
    }
}
