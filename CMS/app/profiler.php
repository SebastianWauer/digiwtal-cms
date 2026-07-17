<?php
declare(strict_types=1);

/**
 * Request Profiler (Admin/System Health)
 *
 * Ziele:
 * - Leichtgewichtig, ohne externe Dependencies
 * - Backend-messbar (TTFB/Total, DB-Zeit, Query-Count, Peak-Memory)
 * - Persistiert als JSONL in /storage/system/health_requests.jsonl
 *
 * Security:
 * - Keine Query-Strings, keine Bodies, keine IPs
 * - Nur Pfad, Method, Status, Zeiten, Memory und (optional) user_id
 */

if (!function_exists('cms_prof_init')) {
    function cms_prof_init(): void
    {
        if (PHP_SAPI === 'cli') return;

        // Init nur einmal
        if (isset($GLOBALS['CMS_PROF']) && is_array($GLOBALS['CMS_PROF'])) {
            return;
        }

        $uri  = (string)($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        if (!is_string($path) || $path === '') $path = '/';

        // Normieren: trailing slash (außer "/")
        $path = '/' . ltrim($path, '/');
        if ($path !== '/') $path = rtrim($path, '/');

        $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($method === '') $method = 'GET';

        $t0 = isset($GLOBALS['CMS_REQUEST_T0']) ? (float)$GLOBALS['CMS_REQUEST_T0'] : microtime(true);

        $GLOBALS['CMS_PROF'] = [
            't0' => $t0,
            'path' => $path,
            'method' => $method,
            'db_ms' => 0.0,
            'db_q' => 0,
        ];

        // Persistieren am Ende des Requests
        register_shutdown_function('cms_prof_finalize');
    }
}

if (!function_exists('cms_prof_add_db')) {
    function cms_prof_add_db(float $ms): void
    {
        if (!isset($GLOBALS['CMS_PROF']) || !is_array($GLOBALS['CMS_PROF'])) return;

        $GLOBALS['CMS_PROF']['db_ms'] = (float)($GLOBALS['CMS_PROF']['db_ms'] ?? 0.0) + $ms;
        $GLOBALS['CMS_PROF']['db_q']  = (int)($GLOBALS['CMS_PROF']['db_q'] ?? 0) + 1;
    }
}

if (!function_exists('cms_prof_data')) {
    /** @return array{total_ms:float, db_ms:float, db_q:int, peak_mem:int, path:string, method:string, status:int, at:string, user_id:int|null} */
    function cms_prof_data(): array
    {
        $p = (isset($GLOBALS['CMS_PROF']) && is_array($GLOBALS['CMS_PROF'])) ? $GLOBALS['CMS_PROF'] : [];

        $t0 = (float)($p['t0'] ?? microtime(true));
        $totalMs = (microtime(true) - $t0) * 1000.0;

        $dbMs = (float)($p['db_ms'] ?? 0.0);
        $dbQ  = (int)($p['db_q'] ?? 0);

        $path = (string)($p['path'] ?? '/');
        $method = (string)($p['method'] ?? 'GET');

        $status = http_response_code();
        if (!is_int($status) || $status <= 0) $status = 200;

        $peak = (int)memory_get_peak_usage(true);

        // optional: admin user id (falls verfügbar)
        $uid = null;
        if (function_exists('admin_current_user')) {
            $u = admin_current_user();
            if (is_array($u) && isset($u['id'])) {
                $uid = (int)$u['id'];
                if ($uid <= 0) $uid = null;
            }
        }

        return [
            'at' => gmdate('c'),
            'path' => $path,
            'method' => $method,
            'status' => $status,
            'total_ms' => round($totalMs, 2),
            'db_ms' => round($dbMs, 2),
            'db_q' => $dbQ,
            'peak_mem' => $peak,
            'user_id' => $uid,
        ];
    }
}

if (!function_exists('cms_prof_finalize')) {
    function cms_prof_finalize(): void
    {
        if (PHP_SAPI === 'cli') return;
        if (!isset($GLOBALS['CMS_PROF']) || !is_array($GLOBALS['CMS_PROF'])) return;

        // Assets & triviales rausfiltern
        $path = (string)($GLOBALS['CMS_PROF']['path'] ?? '/');
        if (str_starts_with($path, '/assets/') || $path === '/favicon.ico' || $path === '/robots.txt') {
            return;
        }

        $data = cms_prof_data();

        // storage/system sicherstellen
        $root = function_exists('admin_project_root') ? admin_project_root() : realpath(__DIR__ . '/..');
        if (!is_string($root) || $root === '') return;

        $dir = rtrim($root, '/') . '/storage/system';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $file = $dir . '/health_requests.jsonl';

        // Simple rotation (damit es nie unendlich wächst)
        $maxBytes = 2_000_000; // ~2MB
        if (is_file($file)) {
            $size = @filesize($file);
            if (is_int($size) && $size > $maxBytes) {
                @rename($file, $file . '.1');
            }
        }

        $line = json_encode($data, JSON_UNESCAPED_SLASHES) . "\n";
        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }
}
