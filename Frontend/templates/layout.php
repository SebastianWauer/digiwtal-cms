<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
    $brandVersion = @filemtime(__DIR__ . '/../assets/css/brand.php') ?: time();
    $themeVersion = @filemtime(__DIR__ . '/../assets/css/theme.css') ?: time();
    $assetBaseUrl = (isset($assetBaseUrl) && is_string($assetBaseUrl)) ? rtrim($assetBaseUrl, '/') : '';
    $faviconUrl = (isset($faviconUrl) && is_string($faviconUrl)) ? trim($faviconUrl) : '';
    $previewMainOnly = !empty($previewMainOnly);
    $contactFormStates = is_array($contactFormStates ?? null) ? $contactFormStates : [];
    $contactTurnstileSiteKey = (isset($contactTurnstileSiteKey) && is_string($contactTurnstileSiteKey)) ? trim($contactTurnstileSiteKey) : '';
    $publicSettings = is_array($publicSettings ?? null) ? $publicSettings : [];
    $themeCssHref = ($assetBaseUrl !== '' ? $assetBaseUrl : '') . '/assets/css/theme.css?v=' . (int)$themeVersion;
    $brandCssHref = ($assetBaseUrl !== '' ? $assetBaseUrl : '') . '/assets/css/brand.php?v=' . (int)$brandVersion;
    $faviconFallbackUrl = ($assetBaseUrl !== '' ? $assetBaseUrl : '') . '/favicon.ico';
    ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($themeCssHref, ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars($brandCssHref, ENT_QUOTES, 'UTF-8') ?>">
    <?php if ($faviconUrl !== ''): ?>
    <link rel="icon" href="<?= htmlspecialchars($faviconUrl, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <style>
      :root {
        --site-favicon-url: url('<?= htmlspecialchars($faviconUrl !== '' ? $faviconUrl : $faviconFallbackUrl, ENT_QUOTES, 'UTF-8') ?>');
      }
      /* Safety override: honor per-block hero overlay even with cached/legacy theme.css */
      .block-hero[style*="background-image"]::before {
        background: rgba(0, 0, 0, var(--hero-overlay-opacity, 0)) !important;
      }
    </style>
    <title><?php echo e($title ?? 'Seite'); ?></title>
    <?php
    $seo = is_array($seo ?? null) ? $seo : [];
    $metaDesc   = (string)($seo['description'] ?? $seo['meta_description'] ?? '');
    $robots     = (string)($seo['robots'] ?? '');
    $canonical  = (string)($seo['canonical_url'] ?? '');
    $ogTitle    = (string)($seo['og_title'] ?? $seo['title'] ?? $seo['meta_title'] ?? '');
    $ogDesc     = (string)($seo['og_description'] ?? $metaDesc);
    $ogImage    = (string)($seo['og_image_url'] ?? '');
    ?>
    <?php if ($metaDesc !== ''): ?>
    <meta name="description" content="<?= htmlspecialchars($metaDesc, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <?php if ($robots !== ''): ?>
    <meta name="robots" content="<?= htmlspecialchars($robots, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <?php if ($canonical !== ''): ?>
    <link rel="canonical" href="<?= htmlspecialchars($canonical, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <?php if ($ogTitle !== ''): ?>
    <meta property="og:title" content="<?= htmlspecialchars($ogTitle, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <?php if ($ogDesc !== ''): ?>
    <meta property="og:description" content="<?= htmlspecialchars($ogDesc, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <?php if ($ogImage !== ''): ?>
    <meta property="og:image" content="<?= htmlspecialchars($ogImage, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
</head>
<body>
    <?php
    $headerNavItems = array_values(array_filter($navItems ?? [], static function (array $item): bool {
        return (string)($item['area'] ?? 'header') !== 'footer';
    }));
    $footerNavItems = array_values(array_filter($navItems ?? [], static function (array $item): bool {
        return (string)($item['area'] ?? '') === 'footer';
    }));
    if (!$previewMainOnly) {
        render('templates/partials/header.php', compact('siteName', 'headerNavItems', 'slug', 'headerLogoUrl', 'faviconUrl', 'assetBaseUrl'));
    }
    ?>
    
    <main>
        <?php render('templates/page.php', compact('pageTitle', 'pageSubtitle', 'blocks', 'contactFormStates', 'slug', 'contactTurnstileSiteKey', 'publicSettings')); ?>
    </main>
    
    <?php if (!$previewMainOnly) { render('templates/partials/footer.php', compact('siteName', 'footerNavItems')); } ?>
</body>
</html>
