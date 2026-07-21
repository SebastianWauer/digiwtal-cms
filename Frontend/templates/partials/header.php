<?php
/**
 * Build navigation tree from flat array
 */
if (!function_exists('buildNavTree')) {
    function buildNavTree(array $items, $parentId = null, array $visitedIds = []): array {
        $branch = [];
        
        foreach ($items as $item) {
            // Normalize parent_id key and convert to int|null
            $rawParentId = $item['parent_id'] ?? $item['parentId'] ?? null;
            $itemParentId = null;
            if ($rawParentId !== null && $rawParentId !== '') {
                $itemParentId = (int)$rawParentId;
            }
            
            // Match logic based on parentId type
            $matches = false;
            if ($parentId === null) {
                // Looking for null parents
                $matches = ($itemParentId === null);
            } elseif ($parentId === 0) {
                // Looking for 0 parents
                $matches = ($itemParentId === 0);
            } else {
                // Looking for specific non-root parent
                $matches = ($itemParentId === (int)$parentId);
            }
            
            if (!$matches) {
                continue;
            }
            
            // Normalize URL
            $url = $item['url'] ?? null;
            if ($url === null) {
                $itemSlug = (string)($item['slug'] ?? '');
                $url = in_array($itemSlug, ['start', 'home'], true) ? '/' : '/' . $itemSlug;
            }
            
            // Skip absolute URLs (security)
            if (preg_match('#^https?://#i', $url)) {
                continue;
            }
            
            // Skip protocol-relative URLs (security)
            if (substr($url, 0, 2) === '//') {
                continue;
            }
            
            // Ensure URL starts with /
            if ($url === '' || $url[0] !== '/') {
                $url = '/' . $url;
            }
            
            $nodeId = (int)($item['id'] ?? 0);
            
            // Recursion protection: check if this node was already visited
            if (in_array($nodeId, $visitedIds, true)) {
                continue;
            }
            
            $node = [
                'id' => $nodeId,
                'title' => (string)($item['title'] ?? 'Untitled'),
                'url' => $url,
                'slug' => (string)($item['slug'] ?? ''),
                'is_home' => !empty($item['is_home']),
                'nav_order' => (int)($item['nav_order'] ?? $item['sort_order'] ?? 9999),
                'children' => buildNavTree($items, $nodeId, array_merge($visitedIds, [$nodeId]))
            ];
            
            $branch[] = $node;
        }
        
        // Sort by nav_order ASC, then by title
        usort($branch, function($a, $b) {
            if ($a['nav_order'] !== $b['nav_order']) {
                return $a['nav_order'] <=> $b['nav_order'];
            }
            return $a['title'] <=> $b['title'];
        });
        
        return $branch;
    }
}

/**
 * Mark active states in navigation tree
 * Returns updated tree with active_self and active_any flags
 */
if (!function_exists('markNavActive')) {
    function markNavActive(array $tree, string $currentSlug): array {
        foreach ($tree as &$node) {
            // Check if this node is the current page
            $selfActive = false;
            if (in_array($currentSlug, ['start', 'home'], true) && $node['url'] === '/') {
                $selfActive = true;
            } elseif (!in_array($currentSlug, ['start', 'home'], true) && $node['slug'] === $currentSlug) {
                $selfActive = true;
            }
            
            // Mark children recursively
            $childActive = false;
            if (!empty($node['children'])) {
                $node['children'] = markNavActive($node['children'], $currentSlug);
                // Check if any child is active
                foreach ($node['children'] as $child) {
                    if ($child['active_any'] ?? false) {
                        $childActive = true;
                        break;
                    }
                }
            }
            
            $node['active_self'] = $selfActive;
            $node['active_any'] = $selfActive || $childActive;
        }
        unset($node);
        
        return $tree;
    }
}

/**
 * Render navigation tree recursively
 */
if (!function_exists('renderNavTree')) {
    function renderNavTree(array $tree, string $activeFaviconUrl = '', string $currentPath = '/', string $currentSlug = ''): void {
        if (empty($tree)) {
            return;
        }
        $normalize = static function (string $path): string {
            $path = trim($path);
            if ($path === '') return '/';
            if ($path[0] !== '/') $path = '/' . $path;
            $path = preg_replace('#/+#', '/', $path) ?: $path;
            if ($path !== '/') $path = rtrim($path, '/');
            return $path === '' ? '/' : $path;
        };
        
        echo '<ul>';
        foreach ($tree as $node) {
            $nodePath = $normalize((string)($node['url'] ?? '/'));
            $slugNode = trim((string)($node['slug'] ?? ''), '/');
            $slugCurrent = trim($currentSlug, '/');
            $selfByPath = ($nodePath === $currentPath)
                || ($currentPath === '/' && !empty($node['is_home']))
                || ($currentPath === '/' && $slugCurrent !== '' && $nodePath === $normalize('/' . $slugCurrent))
                || ($slugNode !== '' && $slugCurrent !== '' && $slugNode === $slugCurrent);
            $selfActive = ($node['active_self'] ?? false) || $selfByPath;
            $anyActive = ($node['active_any'] ?? false) || $selfByPath;

            echo '<li>';
            echo '<a href="' . e($node['url']) . '"';
            if ($anyActive) {
                echo ' class="active"';
            }
            if ($selfActive) {
                echo ' aria-current="page"';
            }
            echo '>';
            if ($activeFaviconUrl !== '') {
                echo '<span class="site-nav__active-icon-wrap" aria-hidden="true">';
                echo '<img class="site-nav__active-icon" src="' . e($activeFaviconUrl) . '" alt="" loading="lazy">';
                echo '</span>';
            }
            echo '<span>' . e($node['title']) . '</span></a>';
            
            if (!empty($node['children'])) {
                renderNavTree($node['children'], $activeFaviconUrl, $currentPath, $currentSlug);
            }
            
            echo '</li>';
        }
        echo '</ul>';
    }
}

// Auto-detect root parent value
$navItems = $headerNavItems ?? $navItems ?? [];
$rootParent = null;
foreach ($navItems as $item) {
    $rawParentId = $item['parent_id'] ?? $item['parentId'] ?? null;
    if ($rawParentId !== null && trim((string)$rawParentId) === '0') {
        $rootParent = 0;
        break;
    }
}

// Build tree, mark active states, and render navigation
$tree = buildNavTree($navItems, $rootParent);
$tree = markNavActive($tree, $slug ?? 'home');
$activeFaviconUrl = isset($faviconUrl) && is_string($faviconUrl) ? trim($faviconUrl) : '';
$reqPathRaw = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$currentPath = is_string($reqPathRaw) ? trim($reqPathRaw) : '/';
if ($currentPath === '') $currentPath = '/';
if ($currentPath[0] !== '/') $currentPath = '/' . $currentPath;
$currentPath = preg_replace('#/+#', '/', $currentPath) ?: $currentPath;
if (substr($currentPath, 0, 10) === '/index.php') {
    $currentPath = (string)substr($currentPath, strlen('/index.php'));
    if ($currentPath === '') $currentPath = '/';
}
if ($currentPath !== '/') $currentPath = rtrim($currentPath, '/');
if ($activeFaviconUrl === '') {
    $assetBaseUrl = isset($assetBaseUrl) && is_string($assetBaseUrl) ? rtrim($assetBaseUrl, '/') : '';
    $activeFaviconUrl = ($assetBaseUrl !== '' ? $assetBaseUrl : '') . '/favicon.ico';
}
?>
<header class="site-header">
    <a class="site-brand" href="/" aria-label="<?php echo e($siteName ?? 'Website'); ?>">
        <?php if (!empty($headerLogoUrl)): ?>
            <img src="<?php echo e((string)$headerLogoUrl); ?>" alt="<?php echo e($siteName ?? 'Website'); ?>">
        <?php else: ?>
            <span><?php echo e($siteName ?? 'Website'); ?></span>
        <?php endif; ?>
    </a>
    <?php if (!empty($tree)): ?>
    <nav class="site-nav" aria-label="Hauptnavigation">
        <?php renderNavTree($tree, $activeFaviconUrl, $currentPath, (string)($slug ?? '')); ?>
    </nav>
    <?php endif; ?>
</header>
