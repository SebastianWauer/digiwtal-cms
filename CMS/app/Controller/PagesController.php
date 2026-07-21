<?php
declare(strict_types=1);

namespace App\Controller;

use App\Repositories\PageRepositoryDb;
use App\Repositories\MediaRepositoryDb;
use App\Repositories\MediaUsageRepositoryDb;
use App\Repositories\SeoRepositoryDb;
use App\Repositories\SiteSettingsRepositoryDb;
use App\Repositories\EventCategoryRepositoryDb;
use App\Repositories\NewsCategoryRepositoryDb;
use App\Services\PageService;
use App\Services\MediaUsageService;
use App\Services\SeoService;
use App\Setup\EnsureDefaultPages;

final class PagesController
{
    public function preview(): void
    {
        $user = \admin_require_perm('pages.view');

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo 'Method Not Allowed';
            return;
        }

        [$rawUser, $rawTheme, $_pdo, $repo] = $this->deps($user);
        unset($rawUser, $rawTheme);

        $rawBody = (string)file_get_contents('php://input');
        $data = json_decode($rawBody, true);
        if (!is_array($data)) {
            $data = $_POST;
        }
        if (!is_array($data)) {
            $data = [];
        }

        $pageId = (int)($data['id'] ?? 0);
        $page = $pageId > 0 ? $repo->findById($pageId) : null;
        if (!is_array($page)) {
            $page = [
                'id' => 0,
                'slug' => (string)($data['slug'] ?? ''),
                'title' => (string)($data['title'] ?? ''),
                'frontend_title' => (string)($data['frontend_title'] ?? ''),
                'subtitle' => (string)($data['subtitle'] ?? ''),
                'is_home' => !empty($data['is_home']) ? 1 : 0,
            ];
        }

        $page['slug'] = (string)($data['slug'] ?? $page['slug'] ?? '');
        $page['title'] = (string)($data['title'] ?? $page['title'] ?? '');
        $page['frontend_title'] = (string)($data['frontend_title'] ?? $page['frontend_title'] ?? '');
        $page['subtitle'] = (string)($data['subtitle'] ?? $page['subtitle'] ?? '');
        $page['is_home'] = !empty($data['is_home']) ? 1 : (int)($page['is_home'] ?? 0);

        $contentJson = (string)($data['content_json'] ?? '');
        if ($contentJson === '') {
            $contentJson = (string)($page['content_json'] ?? '{"blocks":[]}');
        }

        $decoded = json_decode($contentJson, true);
        $blocks = [];
        if (is_array($decoded)) {
            if (array_is_list($decoded)) {
                $blocks = $decoded;
            } elseif (isset($decoded['blocks']) && is_array($decoded['blocks'])) {
                $blocks = $decoded['blocks'];
            }
        }
        try {
            $blocks = $this->enrichBlocksWithFocusFromDb($_pdo, $blocks);
            $blocks = $this->enrichBlocksWithEventsFromDb($_pdo, $blocks);
        } catch (\Throwable $e) {
            // Preview darf nie komplett ausfallen: bei Fehlern ohne Focus-Enrichment weiter rendern.
            if (\class_exists('FileLogger')) {
                \FileLogger::channel('cms')->error('preview_enrich_blocks_failed', [
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $seo = [
            'meta_title'       => (string)($data['seo_meta_title'] ?? ''),
            'meta_description' => (string)($data['seo_meta_description'] ?? ''),
            'robots'           => (string)($data['seo_robots'] ?? ''),
            'canonical_url'    => (string)($data['seo_canonical_url'] ?? ''),
            'og_title'         => (string)($data['seo_og_title'] ?? ''),
            'og_description'   => (string)($data['seo_og_description'] ?? ''),
            'og_image_url'     => (string)($data['seo_og_image_url'] ?? ''),
        ];

        $settingsRepo = new SiteSettingsRepositoryDb($_pdo);
        $settings = $settingsRepo->getAll();
        $siteName = (string)($settings['site_title'] ?? $settings['site_name'] ?? 'Website');
        $pageTitle = trim((string)($page['frontend_title'] ?? ''));
        if ($pageTitle === '') {
            $pageTitle = (string)($page['title'] ?? 'Seite');
        }
        $pageSubtitle = trim((string)($page['subtitle'] ?? ''));
        $internalTitle = trim((string)($page['title'] ?? ''));
        if ($internalTitle === '') {
            $internalTitle = $pageTitle;
        }
        $title = $internalTitle . ' - ' . $siteName;
        $slug = trim((string)($page['slug'] ?? ''), '/');
        if ($slug === '') {
            $slug = 'home';
        }

        $navItems = $this->buildPreviewNavigationItems($_pdo);

        $frontendView = dirname(__DIR__, 3) . '/Frontend/app/view.php';
        if (!is_file($frontendView)) {
            http_response_code(500);
            echo 'Frontend-View-Helper nicht gefunden.';
            return;
        }

        require_once $frontendView;
        if (!function_exists('render')) {
            http_response_code(500);
            echo 'Frontend-Renderer nicht verfÃ¼gbar.';
            return;
        }

        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('X-Robots-Tag: noindex, nofollow');
        $cmsBaseUrl = $this->cmsBaseUrlFromRequest();
        $faviconMediaId = (int)($settings['favicon_media_id'] ?? 0);
        $faviconUrl = $faviconMediaId > 0
            ? $this->absolutizeCmsMediaUrl('/media/file?id=' . $faviconMediaId, $cmsBaseUrl)
            : '';
        $logoId = (int)($settings['cms_logo_light_media_id'] ?? 0);
        if ($logoId <= 0) {
            $logoId = (int)($settings['logo_media_id'] ?? 0);
        }
        $headerLogoUrl = $logoId > 0
            ? $this->absolutizeCmsMediaUrl('/media/file?id=' . $logoId, $cmsBaseUrl)
            : '';
        $assetBaseUrl = rtrim((string)\App\Core\Env::get('FRONTEND_BASE_URL', ''), '/');
        $previewMainOnly = true;

        try {
            ob_start();
            render('themes/default/layout.php', compact(
                'siteName',
                'title',
                'pageTitle',
                'pageSubtitle',
                'blocks',
                'navItems',
                'slug',
                'seo',
                'faviconUrl',
                'headerLogoUrl',
                'assetBaseUrl',
                'previewMainOnly'
            ));
            $html = (string)ob_get_clean();

            if ($assetBaseUrl !== '') {
                $baseTag = '<base href="' . htmlspecialchars($assetBaseUrl . '/', ENT_QUOTES, 'UTF-8') . '">';
                $patched = preg_replace('/<head(\s*)>/i', '<head$1>' . $baseTag, $html, 1);
                if (is_string($patched) && $patched !== '') {
                    $html = $patched;
                }
            }

            echo $html;
        } catch (\Throwable) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            http_response_code(500);
            echo 'Frontend-Layout konnte nicht gerendert werden.';
        }
    }

    private function parseMediaIdFromUrl(string $url): ?int
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return null;
        }
        $path = (string)($parts['path'] ?? '');
        if (!in_array($path, ['/media/file', '/media/thumb'], true)) {
            return null;
        }
        $query = (string)($parts['query'] ?? '');
        if ($query === '') {
            return null;
        }
        parse_str($query, $q);
        $id = (int)($q['id'] ?? 0);
        return $id > 0 ? $id : null;
    }

    private function cmsBaseUrlFromRequest(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
        if ($host === '') {
            return '';
        }
        return $scheme . '://' . $host;
    }

    private function absolutizeCmsMediaUrl(string $url, string $cmsBaseUrl): string
    {
        $url = trim($url);
        if ($url === '' || $cmsBaseUrl === '') {
            return $url;
        }
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return $url;
        }
        if (!empty($parts['scheme']) && !empty($parts['host'])) {
            return $url;
        }
        $path = (string)($parts['path'] ?? '');
        if (!in_array($path, ['/media/file', '/media/thumb'], true)) {
            return $url;
        }
        $query = (string)($parts['query'] ?? '');
        return rtrim($cmsBaseUrl, '/') . $path . ($query !== '' ? ('?' . $query) : '');
    }

    private function enrichBlocksWithFocusFromDb(\PDO $pdo, array $blocks): array
    {
        $cmsBaseUrl = $this->cmsBaseUrlFromRequest();
        $mediaIds = [];
        $collect = function ($value) use (&$collect, &$mediaIds): void {
            if (!is_array($value)) {
                return;
            }
            foreach ($value as $k => $v) {
                if (is_array($v)) {
                    $collect($v);
                    continue;
                }
                $key = (string)$k;
                $isImageUrlLike = in_array($key, ['url', 'image_url', 'poster_url'], true)
                    || (bool)preg_match('/_image_url$/', $key);
                $isMediaIdLike = in_array($key, ['media_id', 'image_media_id', 'poster_media_id'], true)
                    || (bool)preg_match('/_media_id$/', $key);

                if ($isMediaIdLike) {
                    $mid = (int)$v;
                    if ($mid > 0) {
                        $mediaIds[$mid] = true;
                    }
                    continue;
                }
                if (!is_string($v)) {
                    continue;
                }
                if (!$isImageUrlLike) {
                    continue;
                }
                $parts = parse_url(trim($v));
                if (!is_array($parts)) {
                    continue;
                }
                $path = (string)($parts['path'] ?? '');
                if (!in_array($path, ['/media/file', '/media/thumb'], true)) {
                    continue;
                }
                parse_str((string)($parts['query'] ?? ''), $q);
                $id = (int)($q['id'] ?? 0);
                if ($id > 0) {
                    $mediaIds[$id] = true;
                }
            }
        };
        $collect($blocks);

        if ($mediaIds === []) {
            return $blocks;
        }

        $ids = array_keys($mediaIds);
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $st = $pdo->prepare("SELECT id, focus_x, focus_y FROM media_items WHERE is_deleted = 0 AND id IN ($ph)");
        foreach ($ids as $i => $id) {
            $st->bindValue($i + 1, (int)$id, \PDO::PARAM_INT);
        }
        $st->execute();
        $rows = $st->fetchAll(\PDO::FETCH_ASSOC);
        $focusMap = [];
        foreach ($rows as $r) {
            if (!is_array($r)) continue;
            $id = (int)($r['id'] ?? 0);
            if ($id <= 0) continue;
            $focusMap[$id] = [
                'x' => ($r['focus_x'] !== null && $r['focus_x'] !== '') ? (float)$r['focus_x'] : null,
                'y' => ($r['focus_y'] !== null && $r['focus_y'] !== '') ? (float)$r['focus_y'] : null,
            ];
        }

        $apply = function ($value) use (&$apply, $focusMap) {
            if (!is_array($value)) {
                return $value;
            }

            if (array_is_list($value)) {
                foreach ($value as $i => $item) {
                    $value[$i] = $apply($item);
                }
                return $value;
            }

            foreach ($value as $k => $v) {
                if (is_array($v)) {
                    $value[$k] = $apply($v);
                    continue;
                }
                $key = (string)$k;

                $isImageUrlLike = in_array($key, ['url', 'image_url', 'poster_url'], true)
                    || (bool)preg_match('/_image_url$/', $key);
                $isMediaIdLike = in_array($key, ['media_id', 'image_media_id', 'poster_media_id'], true)
                    || (bool)preg_match('/_media_id$/', $key);

                if ($isImageUrlLike && is_string($v)) {
                    $value[$key] = $this->absolutizeCmsMediaUrl($v, $cmsBaseUrl);
                }

                if ($isMediaIdLike) {
                    $mid = (int)$v;
                    if ($mid > 0 && isset($focusMap[$mid])) {
                        if ($key === 'poster_media_id') {
                            $targetField = 'poster_url';
                        } elseif ($key === 'media_id' || $key === 'image_media_id') {
                            $targetField = 'image_url';
                        } elseif (preg_match('/_media_id$/', $key) === 1) {
                            $targetField = (string)preg_replace('/_media_id$/', '_image_url', $key);
                        } else {
                            $targetField = 'image_url';
                        }
                        if (!isset($value[$targetField]) || trim((string)$value[$targetField]) === '') {
                            $value[$targetField] = $this->absolutizeCmsMediaUrl('/media/file?id=' . $mid, $cmsBaseUrl);
                        }
                        if ($key === 'media_id' && (!isset($value['url']) || trim((string)$value['url']) === '')) {
                            $value['url'] = $this->absolutizeCmsMediaUrl('/media/file?id=' . $mid, $cmsBaseUrl);
                        }

                        if ($focusMap[$mid]['x'] !== null) {
                            $value[$targetField . '_focus_x'] = $focusMap[$mid]['x'];
                            if ($key === 'media_id') {
                                $value['url_focus_x'] = $focusMap[$mid]['x'];
                            }
                        }
                        if ($focusMap[$mid]['y'] !== null) {
                            $value[$targetField . '_focus_y'] = $focusMap[$mid]['y'];
                            if ($key === 'media_id') {
                                $value['url_focus_y'] = $focusMap[$mid]['y'];
                            }
                        }
                    }
                    continue;
                }

                if (!is_string($v)) {
                    continue;
                }
                if (!$isImageUrlLike) {
                    continue;
                }
                $mediaId = $this->parseMediaIdFromUrl($v);
                if ($mediaId === null || !isset($focusMap[$mediaId])) {
                    continue;
                }
                if ($focusMap[$mediaId]['x'] !== null) {
                    $value[$key . '_focus_x'] = $focusMap[$mediaId]['x'];
                }
                if ($focusMap[$mediaId]['y'] !== null) {
                    $value[$key . '_focus_y'] = $focusMap[$mediaId]['y'];
                }
            }
            return $value;
        };

        return $apply($blocks);
    }

    private function buildPreviewNavigationItems(\PDO $pdo): array
    {
        $stmt = $pdo->query("
            SELECT id, nav_label, slug, nav_area, nav_order
            FROM pages
            WHERE is_deleted = 0 AND status = 'live' AND nav_visible = 1
            ORDER BY nav_order ASC, id ASC
        ");
        $rows = $stmt ? $stmt->fetchAll() : [];
        if (!is_array($rows)) {
            return [];
        }

        $items = [];
        foreach ($rows as $r) {
            if (!is_array($r)) {
                continue;
            }
            $slug = trim((string)($r['slug'] ?? ''), '/');
            $url = ($slug === '' || $slug === 'home' || $slug === 'start') ? '/' : ('/' . $slug);
            $title = trim((string)($r['nav_label'] ?? ''));
            if ($title === '') {
                $title = $slug !== '' ? $slug : 'Start';
            }

            $items[] = [
                'id' => (int)($r['id'] ?? 0),
                'title' => $title,
                'url' => $url,
                'slug' => $slug,
                'area' => (string)($r['nav_area'] ?? 'header'),
                'sort_order' => (int)($r['nav_order'] ?? 0),
                'nav_order' => (int)($r['nav_order'] ?? 0),
            ];
        }

        return $items;
    }

    private function normalizeHex(string $color, string $fallback): string
    {
        $color = trim($color);
        if (preg_match('/^#([0-9a-fA-F]{3})$/', $color, $m) === 1) {
            $r = $m[1][0]; $g = $m[1][1]; $b = $m[1][2];
            $color = '#' . $r . $r . $g . $g . $b . $b;
        }
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $color) === 1) {
            return strtolower($color);
        }
        return $fallback;
    }

    private function enrichBlocksWithEventsFromDb(\PDO $pdo, array $blocks): array
    {
        $cache = [];
        $walk = function ($value) use (&$walk, &$cache, $pdo) {
            if (!is_array($value)) {
                return $value;
            }
            if (array_is_list($value)) {
                foreach ($value as $i => $item) {
                    $value[$i] = $walk($item);
                }
                return $value;
            }

            foreach ($value as $k => $v) {
                if (is_array($v)) {
                    $value[$k] = $walk($v);
                }
            }

            if ((string)($value['type'] ?? '') !== 'events') {
                return $value;
            }

            $rawCategories = trim((string)($value['category_slugs'] ?? ($value['category_slug'] ?? '')));
            $categoryList = $rawCategories !== ''
                ? array_values(array_filter(array_map(static fn(string $v): string => trim($v), explode(',', $rawCategories)), static fn(string $v): bool => $v !== ''))
                : [];
            $rawLimit = strtolower(trim((string)($value['limit'] ?? 'all')));
            if ($rawLimit === 'all') {
                $limit = 500;
            } else {
                $limit = (int)$rawLimit;
                if ($limit <= 0) $limit = 50;
                if ($limit > 500) $limit = 500;
            }
            $includePast = true;
            $key = strtolower(implode(',', $categoryList)) . '|' . $limit . '|' . ($includePast ? '1' : '0');
            $hasCategoryColor = function_exists('admin_db_column_exists')
                ? admin_db_column_exists($pdo, 'event_categories', 'color_hex')
                : false;
            $hasCategoryLogo = function_exists('admin_db_column_exists')
                ? admin_db_column_exists($pdo, 'event_categories', 'logo_media_id')
                : false;
            $hasEventSubtitle = function_exists('admin_db_column_exists')
                ? admin_db_column_exists($pdo, 'events', 'subtitle')
                : false;

            if (!isset($cache[$key])) {
                $sql = "
                    SELECT
                      e.id,
                      e.title,
                      " . ($hasEventSubtitle ? 'e.subtitle' : "''") . " AS subtitle,
                      e.description,
                      e.event_date,
                      e.event_date_from,
                      e.event_date_to,
                      e.image_media_id,
                      e.youtube_url,
                      m.focus_x AS image_focus_x,
                      m.focus_y AS image_focus_y,
                      GROUP_CONCAT(DISTINCT c.name ORDER BY c.sort_order ASC, c.slug ASC SEPARATOR ', ') AS category_names,
                      GROUP_CONCAT(DISTINCT c.slug ORDER BY c.sort_order ASC, c.slug ASC SEPARATOR ',') AS category_slugs,
                      " . ($hasCategoryColor
                        ? "GROUP_CONCAT(DISTINCT c.color_hex ORDER BY c.sort_order ASC, c.slug ASC SEPARATOR ',')"
                        : "''") . " AS category_colors
                    FROM events e
                    LEFT JOIN event_category_map ecm ON ecm.event_id = e.id
                    LEFT JOIN event_categories c ON c.id = ecm.category_id AND c.is_deleted = 0
                    LEFT JOIN media_items m ON m.id = e.image_media_id AND m.is_deleted = 0
                    WHERE e.is_deleted = 0
                      AND e.is_published = 1
                ";
                $params = [];
                if ($categoryList !== []) {
                    $inParts = [];
                    foreach ($categoryList as $idx => $slugVal) {
                        $ph = ':slug' . $idx;
                        $inParts[] = $ph;
                        $params[$ph] = $slugVal;
                    }
                    $sql .= " AND EXISTS (
                        SELECT 1
                        FROM event_category_map x
                        JOIN event_categories xc ON xc.id = x.category_id
                        WHERE x.event_id = e.id
                          AND xc.is_deleted = 0
                          AND xc.slug IN (" . implode(',', $inParts) . ")
                    )";
                }
                if (!$includePast) {
                    $sql .= " AND (COALESCE(e.event_date_to, e.event_date_from, DATE(e.event_date)) IS NULL OR COALESCE(e.event_date_to, e.event_date_from, DATE(e.event_date)) >= CURDATE())";
                }
                $sql .= " GROUP BY e.id ORDER BY CASE WHEN COALESCE(e.event_date_from, DATE(e.event_date)) IS NULL THEN 1 ELSE 0 END ASC, COALESCE(e.event_date_from, DATE(e.event_date)) ASC, e.id DESC LIMIT :lim";

                $stmt = $pdo->prepare($sql);
                foreach ($params as $pk => $pv) {
                    $stmt->bindValue($pk, $pv);
                }
                $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
                $stmt->execute();
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                $items = [];
                $eventIds = [];
                foreach (is_array($rows) ? $rows : [] as $r) {
                    if (!is_array($r)) continue;
                    $eventId = (int)($r['id'] ?? 0);
                    if ($eventId <= 0) continue;
                    $eventIds[] = $eventId;
                    $mid = (int)($r['image_media_id'] ?? 0);
                    $dateFrom = trim((string)($r['event_date_from'] ?? ''));
                    $dateTo = trim((string)($r['event_date_to'] ?? ''));
                    if ($dateFrom === '' && trim((string)($r['event_date'] ?? '')) !== '') {
                        $dateFrom = (string)date('Y-m-d', (int)strtotime((string)$r['event_date']));
                    }
                    $catNames = trim((string)($r['category_names'] ?? ''));
                    $catSlugs = trim((string)($r['category_slugs'] ?? ''));
                    $catColors = trim((string)($r['category_colors'] ?? ''));
                    $catNamesArr = $catNames !== '' ? array_values(array_filter(array_map('trim', explode(',', $catNames)), static fn(string $v): bool => $v !== '')) : [];
                    $catSlugsArr = $catSlugs !== '' ? array_values(array_filter(array_map('trim', explode(',', $catSlugs)), static fn(string $v): bool => $v !== '')) : [];
                    $catColorsArr = $catColors !== '' ? array_values(array_filter(array_map(static fn(string $v): string => strtoupper(trim($v)), explode(',', $catColors)), static fn(string $v): bool => preg_match('/^#[0-9A-F]{6}$/', $v) === 1)) : [];
                    $catColorMap = [];
                    foreach ($catSlugsArr as $idx => $slugVal) {
                        $slugKey = strtolower(trim((string)$slugVal));
                        if ($slugKey === '') continue;
                        $colorVal = $catColorsArr[$idx] ?? '';
                        if ($colorVal !== '') {
                            $catColorMap[$slugKey] = $colorVal;
                        }
                    }
                    $items[] = [
                        'id' => $eventId,
                        'title' => (string)($r['title'] ?? ''),
                        'subtitle' => trim((string)($r['subtitle'] ?? '')),
                        'text' => (string)($r['description'] ?? ''),
                        'date' => (string)($r['event_date'] ?? ''),
                        'date_from' => $dateFrom,
                        'date_to' => $dateTo,
                        'image_url' => $mid > 0 ? ('/media/file?id=' . $mid) : '',
                        'image_focus_x' => ($r['image_focus_x'] !== null && $r['image_focus_x'] !== '') ? (float)$r['image_focus_x'] : null,
                        'image_focus_y' => ($r['image_focus_y'] !== null && $r['image_focus_y'] !== '') ? (float)$r['image_focus_y'] : null,
                        'youtube_url' => (string)($r['youtube_url'] ?? ''),
                        'category_name' => $catNames,
                        'category_slug' => $catSlugs,
                        'category_names' => $catNamesArr,
                        'category_slugs' => $catSlugsArr,
                        'category_colors' => $catColorsArr,
                        'category_color_map' => $catColorMap,
                        'category_logo_map' => [],
                        'image_variants' => [],
                        'category_links' => [],
                    ];
                }

                if ($items !== [] && $hasCategoryLogo) {
                    $allCategorySlugs = [];
                    foreach ($items as $itLogo) {
                        if (!is_array($itLogo)) continue;
                        $slugs = is_array($itLogo['category_slugs'] ?? null) ? $itLogo['category_slugs'] : [];
                        foreach ($slugs as $slugVal) {
                            $slugKey = strtolower(trim((string)$slugVal));
                            if ($slugKey !== '') {
                                $allCategorySlugs[$slugKey] = true;
                            }
                        }
                    }
                    $allCategorySlugs = array_keys($allCategorySlugs);
                    if ($allCategorySlugs !== []) {
                        $logoIn = [];
                        $logoParams = [];
                        foreach ($allCategorySlugs as $lidx => $slugVal) {
                            $phLogo = ':logo_slug_' . $lidx;
                            $logoIn[] = $phLogo;
                            $logoParams[$phLogo] = $slugVal;
                        }
                        $logoSql = "
                            SELECT slug, logo_media_id
                            FROM event_categories
                            WHERE is_deleted = 0
                              AND logo_media_id IS NOT NULL
                              AND logo_media_id > 0
                              AND slug IN (" . implode(',', $logoIn) . ")
                        ";
                        $logoStmt = $pdo->prepare($logoSql);
                        foreach ($logoParams as $phLogo => $slugVal) {
                            $logoStmt->bindValue($phLogo, $slugVal);
                        }
                        $logoStmt->execute();
                        $logoRows = $logoStmt->fetchAll(\PDO::FETCH_ASSOC);
                        $logoMapBySlug = [];
                        foreach (is_array($logoRows) ? $logoRows : [] as $logoRow) {
                            if (!is_array($logoRow)) continue;
                            $slugKey = strtolower(trim((string)($logoRow['slug'] ?? '')));
                            $logoMediaId = (int)($logoRow['logo_media_id'] ?? 0);
                            if ($slugKey === '' || $logoMediaId <= 0) continue;
                            $logoMapBySlug[$slugKey] = '/media/file?id=' . $logoMediaId;
                        }
                        if ($logoMapBySlug !== []) {
                            foreach ($items as $ixLogo => $itLogo) {
                                if (!is_array($itLogo)) continue;
                                $slugs = is_array($itLogo['category_slugs'] ?? null) ? $itLogo['category_slugs'] : [];
                                $itemLogoMap = [];
                                foreach ($slugs as $slugVal) {
                                    $slugKey = strtolower(trim((string)$slugVal));
                                    if ($slugKey !== '' && isset($logoMapBySlug[$slugKey])) {
                                        $itemLogoMap[$slugKey] = $logoMapBySlug[$slugKey];
                                    }
                                }
                                $items[$ixLogo]['category_logo_map'] = $itemLogoMap;
                            }
                        }
                    }
                }

                if ($eventIds !== []) {
                    try {
                        $ph2 = implode(',', array_fill(0, count($eventIds), '?'));
                        $vsql = "
                            SELECT
                              ecm.event_id,
                              ec.slug AS category_slug,
                              ec.name AS category_name,
                              " . ($hasCategoryColor ? 'ec.color_hex' : "''") . " AS category_color,
                              ecm.media_id,
                              mi.focus_x AS focus_x,
                              mi.focus_y AS focus_y
                            FROM event_category_media ecm
                            JOIN event_categories ec ON ec.id = ecm.category_id AND ec.is_deleted = 0
                            JOIN media_items mi ON mi.id = ecm.media_id AND mi.is_deleted = 0
                            WHERE ecm.event_id IN ($ph2)
                            ORDER BY ecm.event_id ASC, ec.sort_order ASC, ec.name ASC
                        ";
                        $vst = $pdo->prepare($vsql);
                        foreach ($eventIds as $i2 => $eid2) {
                            $vst->bindValue($i2 + 1, (int)$eid2, \PDO::PARAM_INT);
                        }
                        $vst->execute();
                        $vrows = $vst->fetchAll(\PDO::FETCH_ASSOC);
                        $variantsByEvent = [];
                        foreach (is_array($vrows) ? $vrows : [] as $vr) {
                            if (!is_array($vr)) continue;
                            $eid3 = (int)($vr['event_id'] ?? 0);
                            $mid3 = (int)($vr['media_id'] ?? 0);
                            if ($eid3 <= 0 || $mid3 <= 0) continue;
                            $variantsByEvent[$eid3][] = [
                                'category_slug' => trim((string)($vr['category_slug'] ?? '')),
                                'category_name' => trim((string)($vr['category_name'] ?? '')),
                                'category_color' => strtoupper(trim((string)($vr['category_color'] ?? ''))),
                                'image_url' => '/media/file?id=' . $mid3,
                                'image_focus_x' => ($vr['focus_x'] !== null && $vr['focus_x'] !== '') ? (float)$vr['focus_x'] : null,
                                'image_focus_y' => ($vr['focus_y'] !== null && $vr['focus_y'] !== '') ? (float)$vr['focus_y'] : null,
                            ];
                        }
                        foreach ($items as $ix => $itx) {
                            $eid4 = (int)($itx['id'] ?? 0);
                            if ($eid4 > 0 && isset($variantsByEvent[$eid4])) {
                                $items[$ix]['image_variants'] = $variantsByEvent[$eid4];
                            }
                        }
                    } catch (\Throwable) {
                        // Optional feature in preview only; ignore if table missing.
                    }

                    try {
                        $hasLinksType = function_exists('admin_db_column_exists')
                            ? admin_db_column_exists($pdo, 'event_category_links', 'link_type')
                            : false;
                        $hasLinksPdfMedia = function_exists('admin_db_column_exists')
                            ? admin_db_column_exists($pdo, 'event_category_links', 'pdf_media_id')
                            : false;
                        $hasLinksYoutubeStartAt = function_exists('admin_db_column_exists')
                            ? admin_db_column_exists($pdo, 'event_category_links', 'youtube_start_at')
                            : false;
                        $hasLinksYoutubeEndAt = function_exists('admin_db_column_exists')
                            ? admin_db_column_exists($pdo, 'event_category_links', 'youtube_end_at')
                            : false;
                        $ph3 = implode(',', array_fill(0, count($eventIds), '?'));
                        $lsql = "
                            SELECT
                              ecl.event_id,
                              ec.slug AS category_slug,
                              ec.name AS category_name,
                              " . ($hasLinksType ? 'ecl.link_type' : "'link'") . " AS link_type,
                              ecl.label,
                              ecl.url,
                              " . ($hasLinksPdfMedia ? 'ecl.pdf_media_id' : "NULL") . " AS pdf_media_id,
                              " . ($hasLinksYoutubeStartAt ? 'ecl.youtube_start_at' : "NULL") . " AS youtube_start_at,
                              " . ($hasLinksYoutubeEndAt ? 'ecl.youtube_end_at' : "NULL") . " AS youtube_end_at,
                              ecl.sort_order
                            FROM event_category_links ecl
                            JOIN event_categories ec ON ec.id = ecl.category_id AND ec.is_deleted = 0
                            WHERE ecl.event_id IN ($ph3)
                            ORDER BY ecl.event_id ASC, ec.sort_order ASC, ec.name ASC, ecl.sort_order ASC, ecl.id ASC
                        ";
                        $lst = $pdo->prepare($lsql);
                        foreach ($eventIds as $i3 => $eid3) {
                            $lst->bindValue($i3 + 1, (int)$eid3, \PDO::PARAM_INT);
                        }
                        $lst->execute();
                        $lrows = $lst->fetchAll(\PDO::FETCH_ASSOC);
                        $linksByEvent = [];
                        foreach (is_array($lrows) ? $lrows : [] as $lr) {
                            if (!is_array($lr)) continue;
                            $eid4 = (int)($lr['event_id'] ?? 0);
                            $type = strtolower(trim((string)($lr['link_type'] ?? 'link')));
                            if (!in_array($type, ['link', 'youtube', 'pdf'], true)) {
                                $type = 'link';
                            }
                            $label = trim((string)($lr['label'] ?? ''));
                            $url = trim((string)($lr['url'] ?? ''));
                            $pdfMediaId = (int)($lr['pdf_media_id'] ?? 0);
                            if ($url === '' && $type === 'pdf' && $pdfMediaId > 0) {
                                $url = '/media/file?id=' . $pdfMediaId;
                            }
                            if ($eid4 <= 0 || $label === '' || $url === '') {
                                continue;
                            }
                            $linksByEvent[$eid4][] = [
                                'category_slug' => strtolower(trim((string)($lr['category_slug'] ?? ''))),
                                'category_name' => trim((string)($lr['category_name'] ?? '')),
                                'link_type' => $type,
                                'label' => $label,
                                'url' => $url,
                                'pdf_media_id' => $pdfMediaId > 0 ? $pdfMediaId : 0,
                                'youtube_start_at' => trim((string)($lr['youtube_start_at'] ?? '')),
                                'youtube_end_at' => trim((string)($lr['youtube_end_at'] ?? '')),
                                'sort_order' => (int)($lr['sort_order'] ?? 0),
                            ];
                        }
                        foreach ($items as $ix2 => $it2) {
                            $eid5 = (int)($it2['id'] ?? 0);
                            if ($eid5 > 0 && isset($linksByEvent[$eid5])) {
                                $items[$ix2]['category_links'] = $linksByEvent[$eid5];
                            }
                        }
                    } catch (\Throwable) {
                        // Optional feature in preview only; ignore if table missing.
                    }
                }
                $cache[$key] = $items;
            }

            $value['items'] = $cache[$key];
            return $value;
        };

        return $walk($blocks);
    }

    /** @return array{0:array,1:string,2:\PDO,3:PageRepositoryDb,4:PageService} */
    private function deps(array $user): array
    {
        $theme = \admin_theme_for_user((int)$user['id']);
        $pdo = \admin_pdo();

        EnsureDefaultPages::run($pdo);

        $repo = new PageRepositoryDb($pdo);
        $svc  = new PageService($repo);

        return [$user, $theme, $pdo, $repo, $svc];
    }

    public function index(): void
    {
        $user = \admin_require_perm('pages.view');
        [$user, $theme, $_pdo, $repo] = $this->deps($user);

        $rows = $repo->listActive();
        $deletedCount = $repo->countDeleted();
        $flash = null;

        \admin_layout_begin([
            'title'    => 'Seiten',
            'theme'    => $theme,
            'active'   => 'pages',
            'user'     => $user,
            'next'     => '/pages',
            'pageCss'  => 'pages-list',
            'headline' => 'Seiten',
            'subtitle' => 'Seiten anlegen, bearbeiten oder löschen (Soft-Delete).',
        ]);

        require __DIR__ . '/../Views/pages_list.php';
        \admin_layout_end();
    }

    public function deleted(): void
    {
        $user = \admin_require_perm('pages.view');
        [$user, $theme, $_pdo, $repo] = $this->deps($user);

        $rows = $repo->listDeleted();
        $deletedCount = $repo->countDeleted();
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);

        \admin_layout_begin([
            'title'    => 'GelÃ¶schte Seiten',
            'theme'    => $theme,
            'active'   => 'pages',
            'user'     => $user,
            'next'     => '/pages',
            'pageCss'  => 'pages-list',
            'headline' => 'GelÃ¶schte Seiten',
            'subtitle' => 'Hier kannst du gelÃ¶schte Seiten wiederherstellen.',
        ]);

        require __DIR__ . '/../Views/pages_deleted.php';
        \admin_layout_end();
    }

    public function edit(): void
    {
        $user = \admin_require_perm('pages.view');
        [$user, $theme, $_pdo, $repo, $svc] = $this->deps($user);

        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

        // Neue Seite? => zusÃ¤tzlich pages.create benÃ¶tigen
        if ($id <= 0) {
            \admin_require_perm('pages.create');
        } else {
            // Bearbeiten-Seite Ã¶ffnen ist "edit"
            \admin_require_perm('pages.edit');
        }

        $page = null;
        if ($id > 0) {
            $page = $repo->findById($id);
        }

        if (!is_array($page)) {
            $page = [
                'id'             => 0,
                'slug'           => '/',
                'title'          => '',
                'frontend_title' => '',
                'subtitle'       => '',
                'status'         => 'live',
                'content_json'   => json_encode(['blocks' => []], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{"blocks":[]}',
                'is_deleted'     => 0,

                'is_home'        => 0,
                'nav_visible'    => 0,
                'nav_label'      => '',
                'nav_area'       => 'header',
                'nav_order'      => 0,
            ];
        } else {
            $page['frontend_title'] = (string)($page['frontend_title'] ?? '');
            $page['subtitle']       = (string)($page['subtitle'] ?? '');
            $page['status']         = (string)($page['status'] ?? 'live');

            $page['is_home']     = (int)($page['is_home'] ?? 0);
            $page['nav_visible'] = (int)($page['nav_visible'] ?? 0);
            $page['nav_label']   = (string)($page['nav_label'] ?? '');
            $page['nav_area']    = (string)($page['nav_area'] ?? 'header');
            $page['nav_order']   = (int)($page['nav_order'] ?? 0);
        }

        $flash = null;
        $revisions = [];
        $selectedRevision = null;
        $navCandidates = $repo->listActive();
        $eventCategoryOptions = [];
        $newsCategoryOptions = [];
        try {
            $eventCategoryOptions = (new EventCategoryRepositoryDb($_pdo))->listActive();
        } catch (\Throwable) {
            $eventCategoryOptions = [];
        }
        try {
            $newsCategoryOptions = (new NewsCategoryRepositoryDb($_pdo))->listActive();
        } catch (\Throwable) {
            $newsCategoryOptions = [];
        }

        // SEO-Override (nur seitenspezifische Werte) fÃ¼r das Formular laden
        $seoSvc      = new SeoService(new SeoRepositoryDb($_pdo), new SiteSettingsRepositoryDb($_pdo));
        $seoOverride = $id > 0 ? ($seoSvc->getForPage($id, $page)['_override'] ?? []) : [];

        if ($id > 0) {
            $revisions = $repo->listRevisions($id, 20);
            $previewRevisionId = (int)($_GET['revision'] ?? 0);
            if ($previewRevisionId > 0) {
                $selectedRevision = $repo->findRevision($id, $previewRevisionId);
            }
        }

        \admin_layout_begin([
            'title'    => 'Seite bearbeiten',
            'theme'    => $theme,
            'active'   => 'pages',
            'user'     => $user,
            'next'     => '/pages',
            'pageCss'  => 'pages-edit',
            'headline' => 'Seite',
            'subtitle' => 'Slug muss eindeutig sein. Löschen ist Soft-Delete.',
        ]);

        require __DIR__ . '/../Views/pages_edit.php';
        \admin_layout_end();
    }

    public function save(): void
    {
        $user = \admin_require_perm('pages.view'); // Basis: darf Ã¼berhaupt Pages nutzen
        [$user, $theme, $_pdo, $repo, $svc] = $this->deps($user);

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo 'Method Not Allowed';
            return;
        }

        // âœ… CSRF prÃ¼fen (POST + Sideeffect)
        \admin_verify_csrf();

        $id = (int)($_POST['id'] ?? 0);

        // Permission: create vs edit
        if ($id > 0) {
            \admin_require_perm('pages.edit');
        } else {
            \admin_require_perm('pages.create');
        }

        // Status-Ã„nderung ist separat
        $postedStatus = (string)($_POST['status'] ?? 'live');
        if (!in_array($postedStatus, ['live', 'draft'], true)) $postedStatus = 'live';

        if ($id > 0) {
            $existing = $repo->findById($id);
            $oldStatus = is_array($existing) ? (string)($existing['status'] ?? 'live') : 'live';
            if ($oldStatus !== $postedStatus) {
                \admin_require_perm('pages.status.edit');
            }
        } else {
            if ($postedStatus === 'live') {
                \admin_require_perm('pages.status.edit');
            }
        }

        $slug    = (string)($_POST['slug'] ?? '/');
        $title   = (string)($_POST['title'] ?? '');

        $frontendTitle = (string)($_POST['frontend_title'] ?? '');
        $subtitle      = (string)($_POST['subtitle'] ?? '');
        $status        = $postedStatus;

        $content = (string)($_POST['content_json'] ?? '{"blocks":[]}');
        $restoreRevisionId = (int)($_POST['restore_revision_id'] ?? 0);

        if ($id > 0 && $restoreRevisionId > 0) {
            $rev = $repo->findRevision($id, $restoreRevisionId);
            if (is_array($rev)) {
                $title = (string)($rev['title'] ?? $title);
                $content = (string)($rev['content_json'] ?? $content);
            }
        }

        $isHome     = !empty($_POST['is_home']);
        $navVisible = !empty($_POST['nav_visible']);
        $navLabel   = (string)($_POST['nav_label'] ?? '');
        $navArea    = (string)($_POST['nav_area'] ?? 'header');
        $navOrder   = (int)($_POST['nav_order'] ?? 0);
        $navPlaceMode = (string)($_POST['nav_place_mode'] ?? 'after');
        if (!in_array($navPlaceMode, ['before', 'after'], true)) {
            $navPlaceMode = 'after';
        }
        $navPlaceRef = (int)($_POST['nav_place_ref'] ?? 0);

        $res = $svc->save(
            $id > 0 ? $id : null,
            $slug,
            $title,
            $frontendTitle,
            $subtitle,
            $status,
            $content,
            $isHome,
            $navVisible,
            $navLabel,
            $navArea,
            $navOrder,
            (int)($user['id'] ?? 0)
        );

        $id2   = (int)($res['id'] ?? 0);
        $flash = $res['flash'] ?? null;

        if (!empty($_POST['save_return']) && $id2 > 0) {
            header('Location: /pages');
            exit;
        }
        
        $seoSvc = new SeoService(new SeoRepositoryDb($_pdo), new SiteSettingsRepositoryDb($_pdo));

        if (($res['ok'] ?? false) && $id2 > 0) {
            $mus = new MediaUsageService($_pdo, new MediaUsageRepositoryDb($_pdo), new MediaRepositoryDb($_pdo));
            $mus->syncPageUsages($id2, $content);

            // SEO-Override speichern
            $seoSvc->saveForPage($id2, [
                'meta_title'       => $_POST['seo_meta_title']       ?? '',
                'meta_description' => $_POST['seo_meta_description'] ?? '',
                'robots'           => $_POST['seo_robots']           ?? '',
                'canonical_url'    => $_POST['seo_canonical_url']    ?? '',
                'og_title'         => $_POST['seo_og_title']         ?? '',
                'og_description'   => $_POST['seo_og_description']   ?? '',
                'og_image_url'     => $_POST['seo_og_image_url']     ?? '',
            ]);

            if ($navVisible && $navPlaceRef > 0) {
                $this->reorderNavigation($_pdo, $repo, $id2, $navArea, $navPlaceRef, $navPlaceMode);
            }
        }

        $page = $id2 > 0 ? $repo->findById($id2) : null;
        $revisions = $id2 > 0 ? $repo->listRevisions($id2, 20) : [];
        $selectedRevision = null;
        $navCandidates = $repo->listActive();
        $eventCategoryOptions = [];
        $newsCategoryOptions = [];
        try {
            $eventCategoryOptions = (new EventCategoryRepositoryDb($_pdo))->listActive();
        } catch (\Throwable) {
            $eventCategoryOptions = [];
        }
        try {
            $newsCategoryOptions = (new NewsCategoryRepositoryDb($_pdo))->listActive();
        } catch (\Throwable) {
            $newsCategoryOptions = [];
        }
        if (!is_array($page)) {
            $page = [
                'id'             => 0,
                'slug'           => $svc->normalizeSlug($slug),
                'title'          => $title,
                'frontend_title' => $frontendTitle,
                'subtitle'       => $subtitle,
                'status'         => $status,
                'content_json'   => $content,
                'is_deleted'     => 0,

                'is_home'        => $isHome ? 1 : 0,
                'nav_visible'    => $navVisible ? 1 : 0,
                'nav_label'      => $navLabel,
                'nav_area'       => $navArea,
                'nav_order'      => $navOrder,
                '_nav_place_mode'=> $navPlaceMode,
                '_nav_place_ref' => $navPlaceRef,
            ];
        }

        // SEO-Override fÃ¼r die Formular-Wiederanzeige
        $seoOverride = $id2 > 0 ? ($seoSvc->getForPage($id2, is_array($page) ? $page : [])['_override'] ?? []) : [];

        \admin_layout_begin([
            'title'    => 'Seite bearbeiten',
            'theme'    => $theme,
            'active'   => 'pages',
            'user'     => $user,
            'next'     => '/pages',
            'pageCss'  => 'pages-edit',
            'headline' => 'Seite',
            'subtitle' => 'Slug muss eindeutig sein. Löschen ist Soft-Delete.',
        ]);

        require __DIR__ . '/../Views/pages_edit.php';
        \admin_layout_end();
    }

    private function navAppearsInArea(array $row, string $area): bool
    {
        if ((int)($row['is_deleted'] ?? 0) !== 0) return false;
        if ((int)($row['nav_visible'] ?? 0) !== 1) return false;
        $a = (string)($row['nav_area'] ?? 'header');
        return $a === $area || $a === 'both';
    }

    private function reorderNavigation(
        \PDO $pdo,
        PageRepositoryDb $repo,
        int $pageId,
        string $navArea,
        int $refId,
        string $mode
    ): void {
        $baseArea = in_array($navArea, ['header', 'footer'], true) ? $navArea : 'header';
        $rows = $repo->listActive();

        $ordered = [];
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            if (!$this->navAppearsInArea($row, $baseArea)) continue;
            $rid = (int)($row['id'] ?? 0);
            if ($rid > 0) $ordered[] = $rid;
        }

        $ordered = array_values(array_filter($ordered, static fn(int $id): bool => $id !== $pageId));
        if (!in_array($pageId, $ordered, true)) {
            $ordered[] = $pageId;
        }

        $refPos = array_search($refId, $ordered, true);
        if ($refPos !== false) {
            $ordered = array_values(array_filter($ordered, static fn(int $id): bool => $id !== $pageId));
            $insertPos = ($mode === 'before') ? (int)$refPos : ((int)$refPos + 1);
            array_splice($ordered, $insertPos, 0, [$pageId]);
        }

        $upd = $pdo->prepare('UPDATE pages SET nav_order = :nav_order WHERE id = :id LIMIT 1');
        $n = 10;
        foreach ($ordered as $rid) {
            $upd->execute([
                ':nav_order' => $n,
                ':id' => $rid,
            ]);
            $n += 10;
        }
    }

    public function delete(): void
    {
        $user = \admin_require_perm('pages.delete');
        [$user, $_theme, $_pdo, $repo] = $this->deps($user);

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo 'Method Not Allowed';
            return;
        }

        // âœ… CSRF prÃ¼fen
        \admin_verify_csrf();

        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) $repo->softDelete($id);

        header('Location: /pages');
        exit;
    }

    public function restore(): void
    {
        // Restore hÃ¤ngt an delete
        $user = \admin_require_perm('pages.delete');
        [$user, $_theme, $_pdo, $repo] = $this->deps($user);

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo 'Method Not Allowed';
            return;
        }

        // âœ… CSRF prÃ¼fen
        \admin_verify_csrf();

        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) $repo->restore($id);

        header('Location: /pages/deleted');
        exit;
    }
    public function purge(): void
    {
        $user = \admin_require_perm('pages.delete');
        [$user, $_theme, $_pdo, $repo] = $this->deps($user);

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo 'Method Not Allowed';
            return;
        }

        \admin_verify_csrf();

        $n = $repo->purgeDeleted();
        $_SESSION['flash'] = ($n > 0)
            ? ['type' => 'success', 'msg' => 'Papierkorb geleert (' . $n . ').']
            : ['type' => 'success', 'msg' => 'Papierkorb ist bereits leer.'];

        header('Location: /pages/deleted');
        exit;
    }

}
