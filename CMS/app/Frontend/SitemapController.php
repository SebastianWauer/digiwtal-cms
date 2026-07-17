<?php
declare(strict_types=1);

namespace App\Frontend;

use App\Repositories\SiteSettingsRepositoryDb;

final class SitemapController
{
    public function handle(): void
    {
        $pdo  = db();
        $repo = new SiteSettingsRepositoryDb($pdo);
        $s    = $repo->getAll();

        $base = rtrim((string)($s['seo_canonical_base'] ?? ''), '/');

        // Fallback: aus dem aktuellen Request-Host ableiten
        if ($base === '') {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host   = (string)($_SERVER['HTTP_HOST'] ?? '');
            $base   = $scheme . '://' . $host;
        }

        $stmt  = $pdo->query(
            "SELECT slug, is_home, updated_at FROM pages
             WHERE is_deleted = 0 AND status = 'live'
             ORDER BY nav_order ASC, id ASC"
        );
        $pages = $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];

        header('Content-Type: application/xml; charset=utf-8');
        header('X-Robots-Tag: noindex');

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        $seenHome = false;

        foreach ($pages as $page) {
            $isHome = !empty($page['is_home']);

            if ($isHome) {
                // Home-Seite immer als '/' ausgeben, nur einmal
                if ($seenHome) continue;
                $seenHome = true;
                $slug = '/';
            } else {
                $slug = (string)($page['slug'] ?? '/');
            }

            $url  = $base . $slug;

            $lastmod = '';
            if (!empty($page['updated_at'])) {
                try {
                    $dt      = new \DateTime($page['updated_at']);
                    $lastmod = $dt->format('Y-m-d');
                } catch (\Exception) {}
            }

            echo '  <url>' . "\n";
            echo '    <loc>' . htmlspecialchars($url, ENT_XML1, 'UTF-8') . '</loc>' . "\n";
            if ($lastmod !== '') {
                echo '    <lastmod>' . $lastmod . '</lastmod>' . "\n";
            }
            echo '  </url>' . "\n";
        }

        echo '</urlset>';
    }
}
