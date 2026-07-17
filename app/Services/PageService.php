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

    private function slugFromTitle(string $title): string
    {
        $t = trim($title);
        if ($t === '') return '/';

        // Basic slugify (de-tauglich)
        $t = mb_strtolower($t);
        $t = str_replace(['ä','ö','ü','ß'], ['ae','oe','ue','ss'], $t);
        $t = preg_replace('/[^a-z0-9]+/u', '-', $t) ?? $t;
        $t = trim($t, '-');

        if ($t === '' || $t === 'home' || $t === 'startseite') {
            // NICHT automatisch "/" machen (das soll bewusst über "Startseite" laufen)
            // Wir nehmen einen harmlosen Standard, damit es nicht kollidiert.
            $t = 'home';
        }

        return '/' . $t;
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
        ?int $createdBy = null
    ): array {
        $title = trim($title);
        if ($title === '') {
            return ['ok' => false, 'flash' => ['type'=>'error','msg'=>'Titel darf nicht leer sein.'], 'id' => (int)($id ?? 0)];
        }

        // ✅ Auto-Fallbacks
        $slug = trim($slug);
        if ($slug === '') {
            $slug = $this->slugFromTitle($title);
        }
        $slug = $this->normalizeSlug($slug);

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
        $this->repo->createRevision($id, $title, $contentJson, $createdBy);
        $this->repo->pruneRevisions($id, 50);
        if ($isHome) $this->repo->setHome($id);

        \App\Core\Hooks::do_action('cms_after_page_save', $id, $slug);
        return ['ok' => true, 'flash' => ['type'=>'ok','msg'=>'Seite gespeichert.'], 'id' => $id];
    }
}
