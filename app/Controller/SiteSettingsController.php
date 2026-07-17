<?php
declare(strict_types=1);

namespace App\Controller;

use App\Repositories\SiteSettingsRepositoryDb;
use App\Repositories\MediaRepositoryDb;
use App\Repositories\MediaUsageRepositoryDb;
use App\Services\MediaUsageService;

final class SiteSettingsController
{
    private function deps(array $user): array
    {
        $theme = \admin_theme_for_user((int)($user['id'] ?? 0));
        $pdo = \db();
        return [$user, $theme, $pdo];
    }

    public function show(): void
    {
        $user = \admin_require_perm('settings.view');
        [$user, $theme, $pdo] = $this->deps($user);

        $repo  = new SiteSettingsRepositoryDb($pdo);
        $data  = $repo->getAll();
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);

        \admin_layout_begin([
            'title'  => 'Einstellungen',
            'theme'  => $theme,
            'active' => 'settings',
            'user'   => $user,
            'pageCss' => 'site-settings',
        ]);


        require __DIR__ . '/../Views/settings_site.php';

        \admin_layout_end();
    }

    public function save(): void
    {
        $user = \admin_require_perm('settings.view');
        [$user, $theme, $pdo] = $this->deps($user);

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo 'Method Not Allowed';
            return;
        }

        \admin_verify_csrf();

        $repo = new SiteSettingsRepositoryDb($pdo);

        $repo->set('site_title', trim((string)($_POST['site_title'] ?? '')));
        $repo->set('site_tagline', trim((string)($_POST['site_tagline'] ?? '')));
        $repo->set('domain', trim((string)($_POST['domain'] ?? '')));

        $repo->set('cms_logo_light_media_id', trim((string)($_POST['cms_logo_light_media_id'] ?? '')) ?: null);
        $repo->set('cms_logo_dark_media_id', trim((string)($_POST['cms_logo_dark_media_id'] ?? '')) ?: null);
        $repo->set('favicon_media_id', trim((string)($_POST['favicon_media_id'] ?? '')) ?: null);
        $repo->set('brand_color_primary', trim((string)($_POST['brand_color_primary'] ?? '')));
        $repo->set('brand_color_secondary', trim((string)($_POST['brand_color_secondary'] ?? '')));
        $repo->set('brand_color_tertiary', trim((string)($_POST['brand_color_tertiary'] ?? '')));

        $repo->set('contact_name', trim((string)($_POST['contact_name'] ?? '')));
        $repo->set('contact_email', trim((string)($_POST['contact_email'] ?? '')));
        $repo->set('contact_phone', trim((string)($_POST['contact_phone'] ?? '')));
        $repo->set('contact_address', trim((string)($_POST['contact_address'] ?? '')));
        $repo->set('contact_postal_city', trim((string)($_POST['contact_postal_city'] ?? '')));

        $repo->set('social_facebook', trim((string)($_POST['social_facebook'] ?? '')));
        $repo->set('social_instagram', trim((string)($_POST['social_instagram'] ?? '')));
        $repo->set('social_youtube', trim((string)($_POST['social_youtube'] ?? '')));
        $repo->set('social_tiktok', trim((string)($_POST['social_tiktok'] ?? '')));
        $repo->set('social_x', trim((string)($_POST['social_x'] ?? '')));

        $logo = trim((string)($_POST['logo_media_id'] ?? ''));
        $repo->set('logo_media_id', $logo === '' ? null : $logo);

        // SEO Defaults
        $repo->set('seo_meta_title_default',       trim((string)($_POST['seo_meta_title_default']       ?? '')));
        $repo->set('seo_meta_description_default',  trim((string)($_POST['seo_meta_description_default'] ?? '')));
        $repo->set('seo_canonical_base',            rtrim(trim((string)($_POST['seo_canonical_base'] ?? '')), '/'));
        $repo->set('seo_og_image_url',              trim((string)($_POST['seo_og_image_url']             ?? '')));

        $robotsAllowed = ['index,follow', 'noindex,follow', 'index,nofollow', 'noindex,nofollow'];
        $robots = trim((string)($_POST['seo_robots_default'] ?? 'index,follow'));
        $repo->set('seo_robots_default', in_array($robots, $robotsAllowed, true) ? $robots : 'index,follow');

        // Media-Usage für Site-Settings synchronisieren
        $mus = new MediaUsageService($pdo, new MediaUsageRepositoryDb($pdo), new MediaRepositoryDb($pdo));
        $mus->syncSiteSettingsUsages($repo->getAll());

        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Einstellungen gespeichert.'];
        header('Location: /settings');
        exit;
    }
}
