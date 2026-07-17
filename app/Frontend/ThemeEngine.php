<?php
declare(strict_types=1);

namespace App\Frontend;

use App\Repositories\PageRepositoryDb;
use App\Repositories\SeoRepositoryDb;
use App\Repositories\SiteSettingsRepositoryDb;
use App\Services\SeoService;

final class ThemeEngine
{
    public function render(string $slug): void
    {
        $pdo  = db();
        $repo = new PageRepositoryDb($pdo);

        // Startseite: erst slug='/' versuchen, dann is_home=1 Fallback
        if ($slug === '/') {
            $page = $repo->findPublicBySlug('/') ?? $repo->findPublicHome();
        } else {
            $page = $repo->findPublicBySlug($slug);

            // Home-Slug-Redirect: /<slug> -> / (Duplicate Content vermeiden)
            if (is_array($page) && !empty($page['is_home'])) {
                $qs     = (string)($_SERVER['QUERY_STRING'] ?? '');
                $target = '/' . ($qs !== '' ? '?' . $qs : '');
                http_response_code(301);
                header('Location: ' . $target);
                exit;
            }
        }

        if (!is_array($page)) {
            $this->notFound();
            return;
        }

        // Blocks aus content_json dekodieren
        $blocks = [];
        $raw    = (string)($page['content_json'] ?? '');
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded) && isset($decoded['blocks']) && is_array($decoded['blocks'])) {
                $blocks = $decoded['blocks'];
            }
        }

        // SEO-Daten für diese Seite
        $seoSvc = new SeoService(
            new SeoRepositoryDb($pdo),
            new SiteSettingsRepositoryDb($pdo)
        );
        $seo = $seoSvc->getForPage((int)($page['id'] ?? 0), $page);

        // Navigation
        $navHeader = $repo->listPublicNav('header');
        $navFooter = $repo->listPublicNav('footer');
        $nav = ['header' => $navHeader, 'footer' => $navFooter];
        $settings = (new SiteSettingsRepositoryDb($pdo))->getAll();
        $faviconMediaId = (int)($settings['favicon_media_id'] ?? 0);
        $faviconUrl = $faviconMediaId > 0 ? ('/media/file?id=' . $faviconMediaId) : '';
        $brandPrimary = $this->normalizeHex((string)($settings['brand_color_primary'] ?? ''), '#2563eb');
        $brandSecondary = $this->normalizeHex((string)($settings['brand_color_secondary'] ?? ''), $brandPrimary);
        $brandTertiary = $this->normalizeHex((string)($settings['brand_color_tertiary'] ?? ''), '#f59e0b');

        $renderer  = new BlockRenderer();
        $themeRoot = dirname(__DIR__, 3) . '/Frontend/themes/default';
        $layout    = $themeRoot . '/layout.php';

        if (!is_file($layout)) {
            http_response_code(500);
            echo 'Theme-Layout nicht gefunden.';
            return;
        }

        // Verfügbar in layout.php: $page, $blocks, $seo, $nav, $renderer, $themeRoot
        require $layout;
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

    private function notFound(): void
    {
        http_response_code(404);
        echo '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8">'
           . '<title>404 – Nicht gefunden</title></head>'
           . '<body><h1>404 – Seite nicht gefunden</h1></body></html>';
    }
}
