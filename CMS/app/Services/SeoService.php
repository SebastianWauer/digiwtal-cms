<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\SeoRepositoryDb;
use App\Repositories\SiteSettingsRepositoryDb;

final class SeoService
{
    private const ALLOWED_ROBOTS = [
        'index,follow',
        'noindex,follow',
        'index,nofollow',
        'noindex,nofollow',
    ];

    public function __construct(
        private SeoRepositoryDb           $repo,
        private SiteSettingsRepositoryDb  $settings
    ) {}

    /** Globale SEO-Defaults aus site_settings. */
    public function defaults(): array
    {
        $s = $this->settings->getAll();
        return [
            'meta_title'       => (string)($s['seo_meta_title_default']       ?? ''),
            'meta_description' => (string)($s['seo_meta_description_default'] ?? ''),
            'robots'           => (string)($s['seo_robots_default']           ?? 'index,follow'),
            'canonical_base'   => rtrim((string)($s['seo_canonical_base']     ?? ''), '/'),
            'og_image_url'     => (string)($s['seo_og_image_url']             ?? ''),
        ];
    }

    /**
     * Liefert die fertige SEO-Daten für eine Seite:
     * globale Defaults + seitenspezifische Overrides zusammengeführt.
     */
    public function getForPage(int $pageId, array $page): array
    {
        $def      = $this->defaults();
        $override = ($pageId > 0) ? ($this->repo->findForEntity('page', $pageId) ?? []) : [];

        // Canonical aufbauen: Override-URL oder Base+Slug
        $canonical = (string)($override['canonical_url'] ?? '');
        if ($canonical === '' && $def['canonical_base'] !== '') {
            $slug      = (string)($page['slug'] ?? '/');
            $canonical = $def['canonical_base'] . ($slug === '/' ? '/' : $slug);
        }

        $pageTitle = (string)($page['frontend_title'] ?? '') ?: (string)($page['title'] ?? '');

        return [
            'meta_title'       => (string)($override['meta_title']       ?? '') ?: $pageTitle ?: $def['meta_title'],
            'meta_description' => (string)($override['meta_description'] ?? '') ?: $def['meta_description'],
            'robots'           => (string)($override['robots']           ?? '') ?: $def['robots'],
            'canonical_url'    => $canonical,
            'og_title'         => (string)($override['og_title']         ?? ''),
            'og_description'   => (string)($override['og_description']   ?? ''),
            'og_image_url'     => (string)($override['og_image_url']     ?? '') ?: $def['og_image_url'],
            // Rohe Override-Werte für das Admin-Formular
            '_override'        => $override,
        ];
    }

    /**
     * Validiert und speichert SEO-Override für eine Seite.
     */
    public function saveForPage(int $pageId, array $raw): void
    {
        $robots = (string)($raw['robots'] ?? '');
        if (!in_array($robots, self::ALLOWED_ROBOTS, true)) {
            $robots = '';
        }

        $canonicalUrl = $this->sanitizeUrl((string)($raw['canonical_url'] ?? ''));
        $ogImageUrl   = $this->sanitizeUrl((string)($raw['og_image_url']  ?? ''));

        $this->repo->upsertForEntity('page', $pageId, [
            'meta_title'       => mb_substr(trim((string)($raw['meta_title']       ?? '')), 0, 255),
            'meta_description' => mb_substr(trim((string)($raw['meta_description'] ?? '')), 0, 500),
            'robots'           => $robots,
            'canonical_url'    => mb_substr($canonicalUrl, 0, 2000),
            'og_title'         => mb_substr(trim((string)($raw['og_title']         ?? '')), 0, 255),
            'og_description'   => mb_substr(trim((string)($raw['og_description']   ?? '')), 0, 500),
            'og_image_url'     => mb_substr($ogImageUrl,  0, 2000),
        ]);
    }

    private function sanitizeUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') return '';
        return preg_match('/^https?:\/\//i', $url) ? $url : '';
    }
}
