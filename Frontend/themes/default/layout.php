<?php
declare(strict_types=1);

if (!function_exists('render')) {
    $viewHelper = __DIR__ . '/../../app/view.php';
    if (is_file($viewHelper)) {
        require_once $viewHelper;
    }
}

$siteName = (string)($siteName ?? ($settings['site_title'] ?? $settings['site_name'] ?? 'Website'));

$pageTitle = trim((string)($pageTitle ?? ''));
if ($pageTitle === '') {
    $pageTitle = trim((string)($page['frontend_title'] ?? ''));
}
if ($pageTitle === '') {
    $pageTitle = (string)($page['title'] ?? 'Seite');
}

$pageSubtitle = trim((string)($pageSubtitle ?? ($page['subtitle'] ?? '')));

$internalTitle = trim((string)($page['title'] ?? ''));
if ($internalTitle === '') {
    $internalTitle = $pageTitle;
}
$title = (string)($title ?? ($internalTitle . ' - ' . $siteName));

$slug = trim((string)($slug ?? ($page['slug'] ?? 'home')), '/');
if ($slug === '') {
    $slug = 'home';
}

$blocks = is_array($blocks ?? null) ? $blocks : (is_array($page['blocks'] ?? null) ? $page['blocks'] : []);
$contactFormStates = is_array($contactFormStates ?? null) ? $contactFormStates : [];
$seo = is_array($seo ?? null) ? $seo : [];

if (!isset($navItems) || !is_array($navItems)) {
    $navItems = [];
    if (is_array($nav['header'] ?? null)) {
        foreach ($nav['header'] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $item['area'] = 'header';
            $navItems[] = $item;
        }
    }
    if (is_array($nav['footer'] ?? null)) {
        foreach ($nav['footer'] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $item['area'] = 'footer';
            $navItems[] = $item;
        }
    }
}

$faviconUrl = trim((string)($faviconUrl ?? ''));
if ($faviconUrl === '' && isset($settings['favicon_url']) && is_string($settings['favicon_url'])) {
    $faviconUrl = trim($settings['favicon_url']);
}
if ($faviconUrl === '' && isset($settings['favicon_media_id']) && (int)$settings['favicon_media_id'] > 0) {
    $faviconUrl = '/media/file?id=' . (int)$settings['favicon_media_id'];
}

$headerLogoUrl = trim((string)($headerLogoUrl ?? ''));
if ($headerLogoUrl === '' && isset($settings['cms_logo_light_url']) && is_string($settings['cms_logo_light_url'])) {
    $headerLogoUrl = trim($settings['cms_logo_light_url']);
}
if ($headerLogoUrl === '' && isset($settings['logo_url']) && is_string($settings['logo_url'])) {
    $headerLogoUrl = trim($settings['logo_url']);
}
if ($headerLogoUrl === '' && isset($settings['cms_logo_light_media_id']) && (int)$settings['cms_logo_light_media_id'] > 0) {
    $headerLogoUrl = '/media/file?id=' . (int)$settings['cms_logo_light_media_id'];
}
if ($headerLogoUrl === '' && isset($settings['logo_media_id']) && (int)$settings['logo_media_id'] > 0) {
    $headerLogoUrl = '/media/file?id=' . (int)$settings['logo_media_id'];
}

if (!function_exists('render')) {
    http_response_code(500);
    echo 'Frontend-Renderer nicht verfügbar.';
    return;
}

render('templates/layout.php', compact(
    'siteName',
    'title',
    'pageTitle',
    'pageSubtitle',
    'blocks',
    'contactFormStates',
    'navItems',
    'slug',
    'seo',
    'faviconUrl',
    'headerLogoUrl',
    'assetBaseUrl',
    'previewMainOnly'
));

