<?php
declare(strict_types=1);

/**
 * Frontend-Einstiegspunkt.
 * Admin läuft weiterhin über public/index.php (separate Domain/Subdomain).
 *
 * Apache-Konfiguration (Beispiel für Frontend-Domain):
 *   RewriteCond %{HTTP_HOST} !^cms\.
 *   RewriteRule ^(.*)$ web.php [L]
 *
 * (Admin bleibt auf index.php, z.B. cms.meine-domain.de)
 */

// Statische Dateien direkt ausliefern (Fallback wenn .htaccess nicht greift,
// z.B. beim PHP Built-in-Server: php -S localhost:8000 public/web.php)
(static function (): void {
    $uri  = (string)($_SERVER['REQUEST_URI'] ?? '/');
    $path = (string)(parse_url($uri, PHP_URL_PATH) ?: '/');
    if ($path === '' || $path === '/') return;

    $file = __DIR__ . $path;
    if (!is_file($file)) return;

    // Nur bekannte Asset-Typen; nie PHP-Dateien ausliefern
    $ext = strtolower((string)pathinfo($file, PATHINFO_EXTENSION));
    $mime = match ($ext) {
        'css'  => 'text/css; charset=utf-8',
        'js'   => 'application/javascript; charset=utf-8',
        'svg'  => 'image/svg+xml',
        'png'  => 'image/png',
        'jpg', 'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
        'ico'  => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2'=> 'font/woff2',
        'ttf'  => 'font/ttf',
        default => '',
    };

    if ($mime === '') return; // unbekannter Typ → weiter zum Router

    header('Content-Type: ' . $mime);
    header('Cache-Control: public, max-age=86400');
    readfile($file);
    exit;
})();

require __DIR__ . '/../app/bootstrap.php';

// Nur GET erlaubt
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}

// Slug aus Request-URI ermitteln
$uri  = (string)($_SERVER['REQUEST_URI'] ?? '/');
$path = (string)(parse_url($uri, PHP_URL_PATH) ?: '/');
if ($path === '') $path = '/';
if ($path[0] !== '/') $path = '/' . $path;

// Trailing-Slash 301 (außer Root "/")
if ($path !== '/' && str_ends_with($path, '/')) {
    $clean  = rtrim($path, '/');
    $qs     = (string)($_SERVER['QUERY_STRING'] ?? '');
    $target = $clean . ($qs !== '' ? '?' . $qs : '');
    http_response_code(301);
    header('Location: ' . $target);
    exit;
}

$slug = ($path === '/') ? '/' : $path;

// Sitemap
if ($slug === '/sitemap.xml') {
    (new \App\Frontend\SitemapController())->handle();
    exit;
}

(new \App\Frontend\ThemeEngine())->render($slug);
