<?php
declare(strict_types=1);

namespace App\Controller;

use App\Http\Redirect;

final class SystemHealthController
{
    private function timingFilePath(): string
    {
        $root = function_exists('admin_project_root') ? admin_project_root() : '';
        $root = is_string($root) ? rtrim($root, '/') : '';
        return $root !== '' ? ($root . '/storage/system/health_requests.jsonl') : '';
    }

    /** @return array{records:array<int,array<string,mixed>>, stats:array<string,mixed>, per_path:array<int,array<string,mixed>>} */
    private function requestTimings(): array
    {
        $file = $this->timingFilePath();
        if ($file === '' || !is_file($file) || !is_readable($file)) {
            return ['records' => [], 'stats' => [], 'per_path' => []];
        }

        $maxLines = 200;
        $lines = [];
        $fp = @fopen($file, 'rb');
        if (!$fp) return ['records' => [], 'stats' => [], 'per_path' => []];

        try {
            $buf = '';
            fseek($fp, 0, SEEK_END);
            $size = ftell($fp);
            if (!is_int($size)) $size = 0;

            while ($size > 0 && count($lines) < $maxLines) {
                $step = min(4096, $size);
                $size -= $step;
                fseek($fp, $size, SEEK_SET);
                $chunk = fread($fp, $step);
                if ($chunk === false) break;

                $buf = $chunk . $buf;
                while (($nl = strrpos($buf, "\n")) !== false) {
                    $line = substr($buf, $nl + 1);
                    $buf  = substr($buf, 0, $nl);
                    $line = trim($line);
                    if ($line !== '') $lines[] = $line;
                    if (count($lines) >= $maxLines) break;
                }
            }

            if (count($lines) < $maxLines && trim($buf) !== '') {
                $lines[] = trim($buf);
            }
        } finally {
            fclose($fp);
        }

        $records = [];
        foreach ($lines as $line) {
            $row = json_decode($line, true);
            if (!is_array($row)) continue;

            $path = (string)($row['path'] ?? '');
            $total = (float)($row['total_ms'] ?? 0.0);
            if ($path === '' || $total <= 0) continue;

            $records[] = [
                'at' => (string)($row['at'] ?? ''),
                'method' => (string)($row['method'] ?? ''),
                'path' => $path,
                'status' => (int)($row['status'] ?? 0),
                'total_ms' => (float)($row['total_ms'] ?? 0.0),
                'db_ms' => (float)($row['db_ms'] ?? 0.0),
                'db_q' => (int)($row['db_q'] ?? 0),
                'peak_mem' => (int)($row['peak_mem'] ?? 0),
            ];
        }

        usort($records, fn($a, $b) => strcmp((string)$b['at'], (string)$a['at']));

        $totals = array_map(fn($r) => (float)$r['total_ms'], $records);
        sort($totals);

        $stats = [];
        if (count($totals) > 0) {
            $n = count($totals);
            $avg = array_sum($totals) / $n;
            $p50 = $totals[(int)floor(0.50 * ($n - 1))];
            $p95 = $totals[(int)floor(0.95 * ($n - 1))];
            $max = $totals[$n - 1];

            $stats = [
                'n' => $n,
                'avg_ms' => round($avg, 2),
                'p50_ms' => round($p50, 2),
                'p95_ms' => round($p95, 2),
                'max_ms' => round($max, 2),
            ];
        }

        $bucket = [];
        foreach ($records as $r) {
            $k = (string)$r['path'];
            if (!isset($bucket[$k])) {
                $bucket[$k] = ['path' => $k, 'n' => 0, 'sum' => 0.0, 'max' => 0.0];
            }
            $bucket[$k]['n']++;
            $bucket[$k]['sum'] += (float)$r['total_ms'];
            $bucket[$k]['max'] = max((float)$bucket[$k]['max'], (float)$r['total_ms']);
        }

        $perPath = [];
        foreach ($bucket as $b) {
            $avg = $b['n'] > 0 ? $b['sum'] / $b['n'] : 0.0;
            $perPath[] = [
                'path' => (string)$b['path'],
                'n' => (int)$b['n'],
                'avg_ms' => round($avg, 2),
                'max_ms' => round((float)$b['max'], 2),
            ];
        }

        usort($perPath, fn($a, $b) => ($b['avg_ms'] <=> $a['avg_ms']));
        $perPath = array_slice($perPath, 0, 10);

        return ['records' => $records, 'stats' => $stats, 'per_path' => $perPath];
    }

    /** @return array<string,mixed> */
    private function phpInfo(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'sapi' => PHP_SAPI,
            'memory_limit' => (string)(ini_get('memory_limit') ?: ''),
            'max_execution_time' => (string)(ini_get('max_execution_time') ?: ''),
            'upload_max_filesize' => (string)(ini_get('upload_max_filesize') ?: ''),
            'post_max_size' => (string)(ini_get('post_max_size') ?: ''),
        ];
    }

    /** @return array<string,mixed> */
    private function opcacheInfo(): array
    {
        $loaded = extension_loaded('Zend OPcache');
        $fn = function_exists('opcache_get_status');

        $enabled = false;
        $memoryUsage = null;

        if ($fn) {
            $st = @opcache_get_status(false);
            if (is_array($st)) {
                $enabled = !empty($st['opcache_enabled']);
                $memoryUsage = is_array($st['memory_usage'] ?? null) ? $st['memory_usage'] : null;
            }
        }

        return [
            'available' => $fn,
            'loaded' => $loaded,
            'enabled' => $enabled,
            'ini_enable' => (string)(ini_get('opcache.enable') ?: ''),
            'ini_validate_ts' => (string)(ini_get('opcache.validate_timestamps') ?: ''),
            'ini_revalidate_freq' => (string)(ini_get('opcache.revalidate_freq') ?: ''),
            'ini_memory' => (string)(ini_get('opcache.memory_consumption') ?: ''),
            'memory_usage' => $memoryUsage,
        ];
    }

    /** @return array<string,mixed> */
    private function apcuInfo(): array
    {
        $loaded = extension_loaded('apcu');
        $fn = function_exists('apcu_enabled');

        $enabled = false;
        if ($fn) {
            $enabled = (bool)apcu_enabled();
        }

        $ini = (string)(ini_get('apc.enabled') ?: '');

        return [
            'available' => $fn,
            'loaded' => $loaded,
            'enabled' => $enabled,
            'ini_enabled' => $ini,
        ];
    }

    /** @param array<int,array<string,mixed>> $records */
    private function avgDbFromRecords(array $records): array
    {
        $sumDb = 0.0;
        $sumQ  = 0;
        $count = 0;

        foreach ($records as $r) {
            if (!is_array($r)) continue;
            $sumDb += (float)($r['db_ms'] ?? 0.0);
            $sumQ  += (int)($r['db_q'] ?? 0);
            $count++;
            if ($count >= 200) break;
        }

        if ($count <= 0) {
            return ['avg_db_ms' => 0.0, 'avg_db_q' => 0];
        }

        return [
            'avg_db_ms' => round($sumDb / $count, 2),
            'avg_db_q'  => (int)round($sumQ / $count),
        ];
    }

    public function reset(): void
    {
        // Systembereich: nur SystemUser
        admin_require_system_user();

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo 'Method Not Allowed';
            return;
        }

        // ✅ ZENTRAL: Admin-CSRF prüfen
        \admin_verify_csrf();

        $file = $this->timingFilePath();
        if ($file !== '') {
            $dir = dirname($file);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            @file_put_contents($file, '', LOCK_EX);
        }

        // Optional: Token rotieren nach destruktiver Aktion
        if (function_exists('admin_csrf_reset')) {
            admin_csrf_reset();
        }

        Redirect::to('/system/health', 302);
    }

    /**
     * JSON Export der wichtigsten System-Health-Daten.
     * Systembereich: nur SystemUser.
     */
    public function api(): void
    {
        admin_require_system_user();

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
            http_response_code(405);
            echo 'Method Not Allowed';
            return;
        }

        $t0 = (float)($GLOBALS['CMS_REQUEST_T0'] ?? microtime(true));
        $responseMs = round((microtime(true) - $t0) * 1000.0, 2);

        $timing = $this->requestTimings();
        $records = is_array($timing['records'] ?? null) ? $timing['records'] : [];
        $stats   = is_array($timing['stats'] ?? null) ? $timing['stats'] : [];
        $perPath = is_array($timing['per_path'] ?? null) ? $timing['per_path'] : [];

        $avgDb = $this->avgDbFromRecords($records);

        $top = $perPath ? array_slice($perPath, 0, 5) : [];

        $payload = [
            'generated_at' => gmdate('c'),
            'response_ms'  => $responseMs,

            'performance' => [
                'samples'   => (int)($stats['n'] ?? 0),
                'avg_ms'    => (float)($stats['avg_ms'] ?? 0.0),
                'p50_ms'    => (float)($stats['p50_ms'] ?? 0.0),
                'p95_ms'    => (float)($stats['p95_ms'] ?? 0.0),
                'max_ms'    => (float)($stats['max_ms'] ?? 0.0),
                'avg_db_ms' => (float)($avgDb['avg_db_ms'] ?? 0.0),
                'avg_db_q'  => (int)($avgDb['avg_db_q'] ?? 0),
                'top_routes' => array_values(array_map(function ($r) {
                    if (!is_array($r)) return [];
                    return [
                        'path'   => (string)($r['path'] ?? ''),
                        'n'      => (int)($r['n'] ?? 0),
                        'avg_ms' => (float)($r['avg_ms'] ?? 0.0),
                        'max_ms' => (float)($r['max_ms'] ?? 0.0),
                    ];
                }, $top)),
            ],

            'runtime' => $this->phpInfo(),
            'opcache' => $this->opcacheInfo(),
            'apcu'    => $this->apcuInfo(),
        ];

        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if (!is_string($json)) {
            http_response_code(500);
            echo '{"error":"json_encode_failed"}';
            return;
        }

        echo $json;
    }

    public function show(): void
    {
        // Systembereich: nur SystemUser
        $user = admin_require_system_user();

        $theme = function_exists('admin_theme_for_user')
            ? admin_theme_for_user((int)($user['id'] ?? 0))
            : 'dark';

        $t0 = (float)($GLOBALS['CMS_REQUEST_T0'] ?? microtime(true));
        $responseMs = round((microtime(true) - $t0) * 1000.0, 2);

        $health = [
            'response_ms' => $responseMs,
            'php' => $this->phpInfo(),
            'opcache' => $this->opcacheInfo(),
            'apcu' => $this->apcuInfo(),
            'timing' => $this->requestTimings(),
        ];

        admin_layout_begin([
            'title' => 'System Health',
            'theme' => $theme,
            'active' => 'system',
            'user' => $user,
            'next' => '/system/health',
            'pageCss' => 'system-health',
            'headline' => 'System Health',
            'subtitle' => 'Systemstatus, Performance und Runtime.',
        ]);

        require __DIR__ . '/../Views/system_health.php';

        admin_layout_end();
    }
}
