<?php
declare(strict_types=1);

// public/api.php
$__appRoot = getenv('CMS_APP_ROOT');
$__appRoot = ($__appRoot !== false && $__appRoot !== '') ? rtrim($__appRoot, '/') : (__DIR__ . '/..');
require_once $__appRoot . '/app/bootstrap.php';

if (!function_exists('cms_api_log_path')) {
    function cms_api_log_path(): string
    {
        return (getenv('CMS_APP_ROOT') ?: dirname(__DIR__)) . '/storage/api_error.log';
    }
}

if (!function_exists('cms_api_debug_log')) {
    function cms_api_debug_log(string $message): void
    {
        $dir = (getenv('CMS_APP_ROOT') ?: dirname(__DIR__)) . '/storage';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $line = '[' . gmdate('c') . '] ' . $message . "\n";
        @file_put_contents(cms_api_log_path(), $line, FILE_APPEND);
        FileLogger::channel('cms-api')->error($message);
    }
}

set_exception_handler(function (Throwable $e): void {
    cms_api_debug_log('[CMS_API] uncaught exception'
        . ' type=' . get_class($e)
        . ' message=' . $e->getMessage()
        . ' file=' . $e->getFile()
        . ' line=' . $e->getLine()
        . ' uri=' . (string)($_SERVER['REQUEST_URI'] ?? ''));

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['ok' => false, 'error' => 'server_error'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
});

register_shutdown_function(function (): void {
    $error = error_get_last();
    if (!is_array($error)) {
        return;
    }

    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array((int)($error['type'] ?? 0), $fatalTypes, true)) {
        return;
    }

    cms_api_debug_log('[CMS_API] fatal shutdown'
        . ' type=' . (string)($error['type'] ?? '')
        . ' message=' . (string)($error['message'] ?? '')
        . ' file=' . (string)($error['file'] ?? '')
        . ' line=' . (string)($error['line'] ?? '')
        . ' uri=' . (string)($_SERVER['REQUEST_URI'] ?? ''));
});

if (!function_exists('request_path')) {
    function request_path(): string
    {
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        if (!is_string($path) || $path === '') return '/';
        return $path;
    }
}

/**
 * Public REST API:
 *  - GET /api/v1/pages
 *  - GET /api/v1/pages/{slug}
 *  - GET /api/v1/settings/public
 *  - GET /api/v1/media/{id}
 *
 * Internal / secured:
 *  - GET  /api/health (X-Health-Token header, query token deprecated fallback)
 *  - POST /api/internal/create-backup   (X-Deploy-Token)
 *  - POST /api/internal/run-migrations  (X-Deploy-Token)
 */

// --------------------------------------------------
// Pfad auflösen ("/api.php" abschneiden)
// --------------------------------------------------
$fullPath = request_path();

$prefix = (function_exists('cms_base_path') ? cms_base_path() : '') . '/api.php';
$path = $fullPath;

if (str_starts_with($path, $prefix)) {
    $path = substr($path, strlen($prefix));
    if ($path === '') $path = '/';
}

// --------------------------------------------------
// Hilfsfunktionen: Settings
// --------------------------------------------------

/**
 * Validiert und normalisiert einen Hex-Farbwert.
 * Akzeptiert #RGB und #RRGGBB, normalisiert auf #rrggbb.
 * Bei ungültigem Wert → $default zurückgeben.
 */
function validate_hex_color(string $color, string $default): string
{
    $color = trim($color);
    if (preg_match('/^#([0-9a-fA-F]{3})$/', $color, $m)) {
        $r = $m[1][0]; $g = $m[1][1]; $b = $m[1][2];
        $color = '#' . $r.$r . $g.$g . $b.$b;
    }
    if (preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
        return strtolower($color);
    }
    return $default;
}

/** Erzeugt einen oeffentlichen CMS-Pfad inklusive optionalem Basis-Pfad. */
function api_cms_path(string $path): string
{
    $base = function_exists('cms_base_path') ? cms_base_path() : '';
    return $base . '/' . ltrim($path, '/');
}

/**
 * Liest die öffentlichen Brand-Settings aus site_settings.
 * Liefert Defaults, wenn ein Key fehlt oder eine Farbe ungültig ist.
 * Mappt site_title → site_name.
 */
function get_public_settings(PDO $pdo): array
{
    $keys = [
        'site_title',
        'domain',
        'brand_color_primary',
        'brand_color_secondary',
        'brand_color_tertiary',
        'logo_url',
        'favicon_media_id',
        'cms_logo_light_media_id',
        'cms_logo_dark_media_id',
        'contact_name',
        'contact_email',
        'contact_phone',
        'contact_address',
        'contact_postal_city',
        // Profil-URLs der Social-Media-Kanaele. Werden im CMS unter
        // Einstellungen gepflegt und vom social_account-Block gebraucht.
        'social_facebook',
        'social_instagram',
        'social_youtube',
        'social_tiktok',
        'social_x'
    ];
    $in   = implode(', ', array_fill(0, count($keys), '?'));
    $stmt = $pdo->prepare("SELECT `key`, `value` FROM site_settings WHERE `key` IN ({$in})");
    $stmt->execute($keys);
    $rows = $stmt->fetchAll();

    $raw = [];
    foreach (is_array($rows) ? $rows : [] as $r) {
        $raw[(string)($r['key'] ?? '')] = (string)($r['value'] ?? '');
    }

    $faviconId = (int)($raw['favicon_media_id'] ?? 0);
    $faviconUrl = $faviconId > 0 ? api_cms_path('/media/file?id=' . $faviconId) : null;
    $cmsLogoLightId = (int)($raw['cms_logo_light_media_id'] ?? 0);
    $cmsLogoLightUrl = $cmsLogoLightId > 0 ? api_cms_path('/media/file?id=' . $cmsLogoLightId) : null;
    $cmsLogoDarkId = (int)($raw['cms_logo_dark_media_id'] ?? 0);
    $cmsLogoDarkUrl = $cmsLogoDarkId > 0 ? api_cms_path('/media/file?id=' . $cmsLogoDarkId) : null;

    return [
        'site_name'             => (string)($raw['site_title'] ?? ''),
        'brand_color_primary'   => validate_hex_color($raw['brand_color_primary']   ?? '', '#2563eb'),
        'brand_color_secondary' => validate_hex_color($raw['brand_color_secondary'] ?? '', '#64748b'),
        'brand_color_tertiary'  => validate_hex_color($raw['brand_color_tertiary']  ?? '', '#f59e0b'),
        'logo_url'              => (isset($raw['logo_url']) && $raw['logo_url'] !== '') ? $raw['logo_url'] : null,
        'cms_logo_light_url'    => $cmsLogoLightUrl,
        'cms_logo_dark_url'     => $cmsLogoDarkUrl,
        'favicon_url'           => $faviconUrl,
        'domain'                => (string)($raw['domain'] ?? ''),
        'contact_name'          => (string)($raw['contact_name'] ?? ''),
        'contact_email'         => (string)($raw['contact_email'] ?? ''),
        'contact_phone'         => (string)($raw['contact_phone'] ?? ''),
        'contact_address'       => (string)($raw['contact_address'] ?? ''),
        'contact_postal_city'   => (string)($raw['contact_postal_city'] ?? ''),
        'social_facebook'       => (string)($raw['social_facebook'] ?? ''),
        'social_instagram'      => (string)($raw['social_instagram'] ?? ''),
        'social_youtube'        => (string)($raw['social_youtube'] ?? ''),
        'social_tiktok'         => (string)($raw['social_tiktok'] ?? ''),
        'social_x'              => (string)($raw['social_x'] ?? ''),
    ];
}

// --------------------------------------------------
// Hilfsfunktionen: Pages
// --------------------------------------------------
function normalize_slug(string $slug): string
{
    $slug = trim($slug);
    if ($slug === '') return '/';
    if ($slug[0] !== '/') $slug = '/' . $slug;
    if (strlen($slug) > 1) $slug = rtrim($slug, '/');
    return $slug;
}

function normalize_event_category_slug(string $slug): string
{
    $slug = mb_strtolower(trim($slug), 'UTF-8');
    $slug = preg_replace('/[^a-z0-9]+/u', '-', $slug) ?? '';
    return trim($slug, '-');
}

function api_db_table_exists(PDO $pdo, string $table): bool
{
    $tableEsc = str_replace('`', '``', $table);
    try {
        $pdo->query("SELECT 1 FROM `$tableEsc` LIMIT 0");
        return true;
    } catch (Throwable) {
        return false;
    }
}

function api_db_column_exists(PDO $pdo, string $table, string $column): bool
{
    $tableEsc = str_replace('`', '``', $table);
    $colEsc = str_replace('`', '``', $column);
    try {
        $pdo->query("SELECT `$colEsc` FROM `$tableEsc` LIMIT 0");
        return true;
    } catch (Throwable) {
        return false;
    }
}

// --------------------------------------------------
// Response Helper
// --------------------------------------------------
function json_response($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function api_client_ip(): string
{
    $xff = (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
    if ($xff !== '') {
        $first = trim(explode(',', $xff)[0] ?? '');
        if ($first !== '') {
            return $first;
        }
    }

    $realIp = trim((string)($_SERVER['HTTP_X_REAL_IP'] ?? ''));
    if ($realIp !== '') {
        return $realIp;
    }

    return (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

function api_auth_bearer_token(): string
{
    $auth = trim((string)($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
    if ($auth === '' && function_exists('getallheaders')) {
        $headers = getallheaders();
        if (is_array($headers)) {
            $auth = trim((string)($headers['Authorization'] ?? $headers['authorization'] ?? ''));
        }
    }
    if (preg_match('/^Bearer\\s+(.+)$/i', $auth, $m) !== 1) {
        return '';
    }
    return trim((string)($m[1] ?? ''));
}

/**
 * Simple fixed-window limiter: 60 req/min per IP.
 * Uses APCu if available, otherwise DB table `rate_limits`.
 */
function enforce_api_rate_limit(int $maxRequests = 60, int $windowSeconds = 60): void
{
    $ip = api_client_ip();
    $now = time();
    $windowStart = (int)(floor($now / $windowSeconds) * $windowSeconds);
    $retryAfter = max(1, $windowSeconds - ($now - $windowStart));
    $count = 0;

    $apcuEnabled = function_exists('apcu_enabled') && apcu_enabled();
    if ($apcuEnabled) {
        $key = 'cms_api_rl:' . sha1($ip . ':' . (string)$windowStart);
        $ok = false;
        $count = (int)apcu_inc($key, 1, $ok, $windowSeconds + 5);
        if (!$ok) {
            apcu_store($key, 1, $windowSeconds + 5);
            $count = 1;
        }
    } else {
        try {
            $pdo = db();
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS rate_limits (
                    ip_hash      CHAR(64)      NOT NULL,
                    window_start INT UNSIGNED  NOT NULL,
                    requests     INT UNSIGNED  NOT NULL DEFAULT 0,
                    updated_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (ip_hash, window_start)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            $ipHash = hash('sha256', $ip);
            $stmt = $pdo->prepare("
                INSERT INTO rate_limits (ip_hash, window_start, requests, updated_at)
                VALUES (:ip_hash, :window_start, 1, NOW())
                ON DUPLICATE KEY UPDATE requests = requests + 1, updated_at = NOW()
            ");
            $stmt->execute([
                ':ip_hash' => $ipHash,
                ':window_start' => $windowStart,
            ]);

            $sel = $pdo->prepare("
                SELECT requests
                FROM rate_limits
                WHERE ip_hash = :ip_hash
                  AND window_start = :window_start
                LIMIT 1
            ");
            $sel->execute([
                ':ip_hash' => $ipHash,
                ':window_start' => $windowStart,
            ]);
            $count = (int)($sel->fetchColumn() ?: 0);
        } catch (Throwable $e) {
            cms_api_debug_log('[CMS_API] rate_limit_fallback_error ip=' . $ip . ' msg=' . $e->getMessage());
            return; // fail-open
        }
    }

    if ($count > $maxRequests) {
        header('Retry-After: ' . (string)$retryAfter);
        json_response(['ok' => false, 'error' => 'rate_limited'], 429);
    }
}

// Per-request API rate limit (global, before routing)
enforce_api_rate_limit(60, 60);

// --------------------------------------------------
// Manifest Helpers (cms-manifest.json, außerhalb public/)
// --------------------------------------------------
function manifest_path(): string
{
    return (getenv('CMS_APP_ROOT') ?: dirname(__DIR__)) . '/cms-manifest.json';
}

function read_manifest(): ?array
{
    $path = manifest_path();
    if (!is_file($path)) return null;
    $raw = @file_get_contents($path);
    if ($raw === false || trim($raw) === '') return null;
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

function write_manifest(array $data): void
{
    $path = manifest_path();
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) return;
    $tmp = $path . '.tmp.' . getmypid();
    if (@file_put_contents($tmp, $json) === false) return;
    @rename($tmp, $path);
}

// --------------------------------------------------
// Deploy Auth + Backup + Migration Helpers
// --------------------------------------------------

function check_deploy_token(): void
{
    $expected = \App\Core\Env::get('DEPLOY_TOKEN', '');
    $given    = (string)($_SERVER['HTTP_X_DEPLOY_TOKEN'] ?? '');
    if ($expected === '' || !hash_equals($expected, $given)) {
        json_response(['ok' => false, 'error' => 'forbidden'], 403);
    }
}

/**
 * Schreibt einen vollständigen MySQL-Dump (alle Tabellen) in $outFile.
 * Arbeitet in Batches von 500 Zeilen – kein OOM bei großen Tabellen.
 */
function cms_dump_sql(PDO $pdo, string $outFile): void
{
    $fh = fopen($outFile, 'w');
    if ($fh === false) {
        throw new RuntimeException("Cannot open dump file for writing.");
    }

    fwrite($fh, "-- DigiWTAL CMS Database Dump\n");
    fwrite($fh, "-- Generated: " . gmdate('c') . "\n\n");
    fwrite($fh, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");

    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    if (!is_array($tables)) $tables = [];

    foreach ($tables as $table) {
        $table = (string)$table;
        $tq    = '`' . str_replace('`', '``', $table) . '`';

        $createRow = $pdo->query("SHOW CREATE TABLE {$tq}")->fetch(PDO::FETCH_NUM);
        $createSql = (string)($createRow[1] ?? '');

        fwrite($fh, "-- Table: {$table}\n");
        fwrite($fh, "DROP TABLE IF EXISTS {$tq};\n");
        fwrite($fh, $createSql . ";\n\n");

        $offset = 0;
        $batch  = 500;
        do {
            $stmt = $pdo->query("SELECT * FROM {$tq} LIMIT {$batch} OFFSET {$offset}");
            $rows = $stmt ? $stmt->fetchAll() : [];
            if (!is_array($rows) || empty($rows)) break;

            $colNames = array_map(
                fn($c) => '`' . str_replace('`', '``', $c) . '`',
                array_keys($rows[0])
            );
            $colList = implode(', ', $colNames);

            foreach ($rows as $r) {
                $vals = array_map(
                    fn($v) => $v === null ? 'NULL' : $pdo->quote((string)$v),
                    array_values($r)
                );
                fwrite($fh, "INSERT INTO {$tq} ({$colList}) VALUES (" . implode(', ', $vals) . ");\n");
            }

            $offset += $batch;
        } while (count($rows) === $batch);

        fwrite($fh, "\n");
    }

    fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
    fclose($fh);
}

/**
 * Fügt ein Verzeichnis rekursiv in ein ZipArchive ein.
 */
function zip_add_dir(ZipArchive $zip, string $dir, string $zipBase): void
{
    if (!is_dir($dir)) return;
    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($iter as $file) {
        if (!$file->isFile()) continue;
        $zip->addFile((string)$file->getPathname(), $zipBase . '/' . $iter->getSubPathname());
    }
}

// --------------------------------------------------
// API v1 Router (minimal)
// --------------------------------------------------
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// --------------------------------------------------
// Health-Check: GET /api/health (header token; query fallback deprecated)
// --------------------------------------------------
if ($method === 'POST' && $path === '/api/setup/init') {
    $expectedToken = trim((string)\App\Core\Env::get('SETUP_TOKEN', ''));
    $givenToken = trim((string)($_SERVER['HTTP_X_SETUP_TOKEN'] ?? ''));

    if ($expectedToken === '') {
        json_response(['ok' => false, 'error' => 'setup_token_not_configured'], 503);
    }
    if ($givenToken === '' || !hash_equals($expectedToken, $givenToken)) {
        json_response(['ok' => false, 'error' => 'forbidden'], 403);
    }

    try {
        if (!\App\Core\Setup::allowSetupRequest(db())) {
            json_response(['ok' => false, 'error' => 'setup_not_allowed'], 409);
        }
    } catch (Throwable $e) {
        json_response(['ok' => false, 'error' => 'db_not_available'], 503);
    }

    json_response([
        'ok' => true,
        'setup_allowed' => true,
        'csrf_token' => admin_csrf_token(),
    ]);
}

if ($method === 'GET' && $path === '/api/health') {
    $expectedToken = \App\Core\Env::get('HEALTH_TOKEN', '');
    $headerToken   = trim((string)($_SERVER['HTTP_X_HEALTH_TOKEN'] ?? ''));
    $queryToken    = trim((string)($_GET['token'] ?? ''));
    $givenToken    = $headerToken !== '' ? $headerToken : $queryToken;

    if ($headerToken === '' && $queryToken !== '') {
        cms_api_debug_log('[CMS_API] deprecated health token via query parameter used');
    }

    if ($expectedToken === '' || !hash_equals($expectedToken, $givenToken)) {
        json_response(['error' => 'Forbidden'], 403);
    }

    // Manifest laden (optional – fehlt beim ersten Deploy noch)
    $manifest = read_manifest();

    // CMS-Version: Manifest > config/version.php > Fallback
    $versionCfg = (static function () {
        $f = (getenv('CMS_APP_ROOT') ?: dirname(__DIR__)) . '/config/version.php';
        if (!is_file($f)) return [];
        $v = include $f;
        return is_array($v) ? $v : [];
    })();
    $cmsVersion = (string)(
        $manifest['cms_version'] ??
        $versionCfg['cms_version'] ??
        '2.1.2'
    );

    // Frontend-Version: Manifest > null
    $frontendVersion = null;
    if ($manifest !== null && ($manifest['frontend_version'] ?? null) !== null) {
        $frontendVersion = (string)$manifest['frontend_version'];
    }

    // Modules: installed_modules aus Manifest > leeres Objekt
    $modules = (object)[];
    if ($manifest !== null && isset($manifest['installed_modules']) && is_array($manifest['installed_modules'])) {
        $modules = (object)$manifest['installed_modules'];
    }

    // DB-Verbindung prüfen (SELECT 1)
    $dbOk      = false;
    $healthPdo = null;
    try {
        $healthPdo = db();
        $healthPdo->query('SELECT 1');
        $dbOk = true;
    } catch (Throwable $e) {
        // $dbOk bleibt false
    }

    // Storage beschreibbar?
    $storageDir      = (getenv('CMS_APP_ROOT') ?: dirname(__DIR__)) . '/storage';
    $storageWritable = false;
    if (!is_dir($storageDir)) {
        $storageWritable = (bool)@mkdir($storageDir, 0755, true);
    } else {
        $storageWritable = is_writable($storageDir);
    }

    // Letzten Migrations-Eintrag ermitteln
    $lastMigration = null;
    if ($dbOk && $healthPdo !== null) {
        try {
            $mStmt = $healthPdo->query(
                "SELECT id FROM schema_migrations ORDER BY applied_at DESC, id DESC LIMIT 1"
            );
            $mRow = $mStmt ? $mStmt->fetch() : false;
            if (is_array($mRow) && isset($mRow['id'])) {
                $lastMigration = (string)$mRow['id'];
            }
        } catch (Throwable $e) {
            // Tabelle existiert nicht → null
        }
    }

    // Gesamtstatus
    $status = ($dbOk && $storageWritable) ? 'healthy' : 'degraded';

    // Manifest aktualisieren (nur wenn es bereits existiert)
    if ($manifest !== null) {
        $manifest['status']         = $status;
        $manifest['last_checked_at'] = gmdate('c');
        write_manifest($manifest);
    }

    json_response([
        'status'           => $status,
        'cms_version'      => $cmsVersion,
        'frontend_version' => $frontendVersion,
        'php_version'      => PHP_VERSION,
        'db_connection'    => $dbOk,
        'storage_writable' => $storageWritable,
        'last_migration'   => $lastMigration,
        'modules'          => $modules,
        'checked_at'       => gmdate('c'),
    ]);
}

// --------------------------------------------------
// Internal Deploy API: /api/internal/...
// Auth: X-Deploy-Token header
// --------------------------------------------------
if (str_starts_with($path, '/api/internal/')) {
    check_deploy_token();

    if ($method !== 'POST') {
        json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
    }

    // --- POST /api/internal/create-backup ---
    if ($path === '/api/internal/create-backup') {
        if (!class_exists('ZipArchive')) {
            json_response(['ok' => false, 'error' => 'backup_failed', 'message' => 'ZipArchive extension not available.'], 500);
        }

        $root      = (getenv('CMS_APP_ROOT') ?: dirname(__DIR__));
        $backupDir = rtrim((string)(\App\Core\Env::get('BACKUP_DIR', '') ?: $root . '/storage/backups'), '/');
        $filename  = 'cms-backup-' . gmdate('Ymd-His') . '.zip';
        $filepath  = $backupDir . '/' . $filename;
        $tmpSql    = null;

        try {
            if (!is_dir($backupDir) && !@mkdir($backupDir, 0755, true)) {
                throw new RuntimeException('Cannot create backup dir: ' . $backupDir);
            }

            $pdo = db();

            $tmpSql = tempnam(sys_get_temp_dir(), 'cms_dump_');
            if ($tmpSql === false) throw new RuntimeException('Cannot create temp file.');
            cms_dump_sql($pdo, $tmpSql);

            $zip = new ZipArchive();
            $res = $zip->open($filepath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            if ($res !== true) {
                throw new RuntimeException('Cannot create ZIP (ZipArchive error ' . $res . ').');
            }

            $zip->addFile($tmpSql, 'database.sql');

            foreach (['uploads', 'data', 'media'] as $d) {
                zip_add_dir($zip, $root . '/storage/' . $d, 'storage/' . $d);
            }

            $zip->close();
            @unlink($tmpSql);
            $tmpSql = null;

            // Manifest aktualisieren (nur wenn bereits vorhanden)
            $manifest = read_manifest();
            if ($manifest !== null) {
                $manifest['last_backup_at'] = gmdate('c');
                write_manifest($manifest);
            }

            json_response([
                'ok'          => true,
                'backup_file' => $filename,
                'size_bytes'  => (int)@filesize($filepath),
                'created_at'  => gmdate('c'),
            ]);
        } catch (Throwable $e) {
            if ($tmpSql !== null) @unlink($tmpSql);
            if (is_file($filepath)) @unlink($filepath);
            json_response(['ok' => false, 'error' => 'backup_failed', 'message' => $e->getMessage()], 500);
        }
    }

    // --- POST /api/internal/run-migrations ---
    if ($path === '/api/internal/run-migrations') {
        try {
            $pdo = db();

            // schema_migrations sicherstellen (konsistent mit db.php)
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS schema_migrations (
                    id VARCHAR(190) NOT NULL,
                    applied_at DATETIME NOT NULL,
                    PRIMARY KEY (id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // Bereits angewendete Migrationen laden
            $applied = [];
            $rows    = $pdo->query("SELECT id FROM schema_migrations")->fetchAll();
            foreach (is_array($rows) ? $rows : [] as $r) {
                $applied[(string)($r['id'] ?? '')] = true;
            }

            // Ausstehende Migrationen anwenden (nutzt db_apply_migration aus db.php)
            $ran = 0;
            foreach (db_migration_files() as $file) {
                $id = basename($file);
                if (isset($applied[$id])) continue;

                $sql = file_get_contents($file);
                if (!is_string($sql) || trim($sql) === '') continue;

                db_apply_migration($pdo, $id, $sql);
                $ran++;
            }

            // Letzten Migrations-Eintrag auslesen
            $lastRow  = $pdo->query("SELECT id FROM schema_migrations ORDER BY applied_at DESC, id DESC LIMIT 1")->fetch();
            $lastId   = is_array($lastRow) ? (string)($lastRow['id'] ?? '') : '';
            $lastName = $lastId !== '' ? basename($lastId, '.sql') : null;

            // Manifest aktualisieren (nur wenn bereits vorhanden)
            $manifest = read_manifest();
            if ($manifest !== null && $lastName !== null) {
                $manifest['last_migration']    = $lastName;
                $manifest['last_migration_at'] = gmdate('c');
                write_manifest($manifest);
            }

            json_response([
                'ok'             => true,
                'applied'        => $ran,
                'last_migration' => $lastName,
                'ran_at'         => gmdate('c'),
            ]);
        } catch (Throwable $e) {
            json_response(['ok' => false, 'error' => 'migration_failed', 'message' => $e->getMessage()], 500);
        }
    }

    json_response(['ok' => false, 'error' => 'not_found'], 404);
}

if (!str_starts_with($path, '/api/v1/')) {
    json_response(['error' => 'Not Found'], 404);
}

$sub = substr($path, strlen('/api/v1'));

try {
    $pdo = db();
} catch (Throwable $e) {
    json_response(['error' => 'DB not available'], 500);
}

if ($method === 'POST' && $sub === '/form/submit') {
    $expectedToken = trim((string)(getenv('CMS_API_TOKEN') ?: ''));
    $providedToken = api_auth_bearer_token();
    if ($expectedToken !== '') {
        if ($providedToken === '' || !hash_equals($expectedToken, $providedToken)) {
            json_response(['ok' => false, 'error' => 'unauthorized'], 401);
        }
    }

    $rawBody = file_get_contents('php://input');
    $payload = is_string($rawBody) ? json_decode($rawBody, true) : null;
    if (!is_array($payload)) {
        json_response(['ok' => false, 'error' => 'bad_request'], 400);
    }

    $formId = strtolower(trim((string)($payload['form_id'] ?? '')));
    if ($formId === '' || preg_match('/^[a-z0-9_-]{1,64}$/', $formId) !== 1) {
        $formId = '__global';
    }
    $slug = trim((string)($payload['slug'] ?? ''));
    $name = trim((string)($payload['name'] ?? ''));
    $email = trim((string)($payload['email'] ?? ''));
    $phone = trim((string)($payload['phone'] ?? ''));
    $message = trim((string)($payload['message'] ?? ''));
    $ip = trim((string)($payload['ip'] ?? ''));
    $ua = trim((string)($payload['user_agent'] ?? ''));
    $fields = is_array($payload['fields'] ?? null) ? array_values(array_filter(array_map(static fn($v): string => strtolower(trim((string)$v)), $payload['fields']), static fn(string $v): bool => in_array($v, ['name', 'email', 'phone', 'message'], true))) : [];

    $data = [
        'slug' => $slug,
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'message' => $message,
        'user_agent' => $ua,
        'fields' => $fields,
    ];

    $stmt = $pdo->prepare("
        INSERT INTO form_submissions (form_id, data_json, ip, created_at)
        VALUES (:form_id, :data_json, :ip, NOW())
    ");
    $stmt->execute([
        ':form_id' => $formId,
        ':data_json' => (string)json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ':ip' => mb_substr($ip, 0, 64),
    ]);

    json_response(['ok' => true, 'id' => (int)$pdo->lastInsertId()], 201);
}

// --- /settings/public ---
if ($sub === '/settings/public') {
    if ($method !== 'GET') {
        json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
    }
    header('Cache-Control: no-store');
    json_response(get_public_settings($pdo));
}

/**
 * Liefert die zuletzt geladenen Instagram-Beitraege des verbundenen
 * Business-/Creator-Kontos (native Meta Graph API, siehe README
 * "Grundsatz: keine Drittanbieter-Embed-Dienste"). Zwischenspeichert das
 * Ergebnis fuer INSTAGRAM_MEDIA_CACHE_TTL Sekunden in site_settings, um
 * nicht bei jedem Seitenaufruf gegen Instagram zu fragen, und erneuert den
 * Access-Token automatisch, bevor er ablaeuft.
 */
function get_instagram_media(PDO $pdo): array
{
    $cacheTtl = 1200; // 20 Minuten
    $tokenRefreshMargin = 7 * 86400; // 7 Tage vor Ablauf erneuern

    $repo = new \App\Repositories\SiteSettingsRepositoryDb($pdo);
    $s = $repo->getAll();

    $tokenEnc = trim((string)($s['instagram_access_token_enc'] ?? ''));
    $username = trim((string)($s['instagram_username'] ?? ''));
    if ($tokenEnc === '') {
        return ['connected' => false, 'username' => '', 'items' => []];
    }

    $cacheJson = (string)($s['instagram_media_cache_json'] ?? '');
    $cacheAt = (int)($s['instagram_media_cache_at'] ?? 0);
    if ($cacheJson !== '' && (time() - $cacheAt) < $cacheTtl) {
        $items = json_decode($cacheJson, true);
        return ['connected' => true, 'username' => $username, 'items' => is_array($items) ? $items : []];
    }

    $appId = trim((string)(getenv('INSTAGRAM_APP_ID') ?: ''));
    $appSecret = trim((string)(getenv('INSTAGRAM_APP_SECRET') ?: ''));
    if ($appId === '' || $appSecret === '') {
        $items = json_decode($cacheJson, true);
        return ['connected' => true, 'username' => $username, 'items' => is_array($items) ? $items : []];
    }

    try {
        $accessToken = \App\Services\InstagramTokenCrypto::decrypt($tokenEnc);
        $client = new \App\Services\InstagramClient($appId, $appSecret);

        $expiresAt = (int)($s['instagram_token_expires_at'] ?? 0);
        if ($expiresAt > 0 && ($expiresAt - time()) < $tokenRefreshMargin) {
            $refreshed = $client->refreshLongLivedToken($accessToken);
            $accessToken = $refreshed['access_token'];
            $repo->set('instagram_access_token_enc', \App\Services\InstagramTokenCrypto::encrypt($accessToken));
            $repo->set('instagram_token_expires_at', (string)(time() + $refreshed['expires_in']));
        }

        $items = $client->fetchMedia($accessToken, 12);
        $repo->set('instagram_media_cache_json', (string)json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $repo->set('instagram_media_cache_at', (string)time());

        return ['connected' => true, 'username' => $username, 'items' => $items];
    } catch (\Throwable $e) {
        cms_api_debug_log('[INSTAGRAM] media fetch failed: ' . $e->getMessage());
        // Bei Fehler (z.B. Instagram kurzzeitig nicht erreichbar, Token widerrufen):
        // alten Cache weiterverwenden, statt die Seite kaputtzumachen.
        $items = json_decode($cacheJson, true);
        return ['connected' => true, 'username' => $username, 'items' => is_array($items) ? $items : []];
    }
}

// --- /instagram/media ---
if ($sub === '/instagram/media') {
    if ($method !== 'GET') {
        json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
    }
    header('Cache-Control: no-store');
    json_response(['ok' => true] + get_instagram_media($pdo));
}

// --- GET /api/v1/pages (list of all public pages) ---
if ($sub === '/pages' && !isset($_GET['slug'])) {
    if ($method !== 'GET') {
        json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
    }
    $stmt = $pdo->query("
        SELECT id, slug, title, nav_label, nav_visible, nav_order, is_home, updated_at
        FROM pages
        WHERE is_deleted = 0 AND status = 'live'
        ORDER BY nav_order ASC, title ASC
    ");
    $rows = $stmt ? $stmt->fetchAll() : [];
    if (!is_array($rows)) $rows = [];

    $out = [];
    foreach ($rows as $r) {
        if (!is_array($r)) continue;
        $isHome    = (bool)($r['is_home'] ?? false);
        $slugRaw   = ltrim((string)($r['slug'] ?? ''), '/');
        $titleVal  = (($r['nav_label'] ?? '') !== '') ? (string)$r['nav_label'] : (string)($r['title'] ?? '');
        $updAt     = (string)($r['updated_at'] ?? '');
        $out[] = [
            'id'         => (int)($r['id'] ?? 0),
            'slug'       => $slugRaw,
            'title'      => $titleVal,
            'url'        => $isHome ? '/' : ('/' . $slugRaw),
            'in_nav'     => (bool)($r['nav_visible'] ?? false),
            'nav_order'  => (int)($r['nav_order'] ?? 0),
            'updated_at' => $updAt !== '' ? gmdate('c', (int)strtotime($updAt)) : null,
        ];
    }
    json_response($out);
}

// --- /pages?slug=/ ---
if ($method === 'GET' && $sub === '/pages') {
    $slug = normalize_slug((string)($_GET['slug'] ?? '/'));

    $stmt = $pdo->prepare("
        SELECT
            p.id,
            p.slug,
            p.title,
            p.frontend_title,
            p.subtitle,
            p.status,
            sm.meta_title,
            sm.meta_description,
            p.content_json
        FROM pages p
        LEFT JOIN seo_meta sm
            ON sm.entity_type = 'page'
           AND sm.entity_id = p.id
        WHERE p.slug = :s
          AND p.is_deleted = 0
          AND p.status = 'live'
        LIMIT 1
    ");
    $stmt->execute([':s' => $slug]);
    $row = $stmt->fetch();
    if (!is_array($row)) {
        json_response(['error' => 'Not Found'], 404);
    }

    $content = null;
    if (isset($row['content_json']) && is_string($row['content_json']) && trim($row['content_json']) !== '') {
        $decoded = json_decode($row['content_json'], true);
        $content = is_array($decoded) ? $decoded : null;
    }

    json_response([
        'page' => [
            'id' => (int)$row['id'],
            'slug' => (string)$row['slug'],
            'title' => (string)($row['title'] ?? ''),
            'frontend_title' => (string)($row['frontend_title'] ?? ''),
            'subtitle' => (string)($row['subtitle'] ?? ''),
            'status' => (string)($row['status'] ?? ''),
            'meta_title' => (string)($row['meta_title'] ?? ''),
            'meta_description' => (string)($row['meta_description'] ?? ''),
            'content' => $content,
        ]
    ], 200);
}

// --- /navigation ---
if ($method === 'GET' && $sub === '/navigation') {
    $stmt = $pdo->query("
        SELECT id, nav_label, slug, nav_area, nav_order
        FROM pages
        WHERE is_deleted = 0 AND status = 'live' AND nav_visible = 1
        ORDER BY nav_order ASC, id ASC
    ");
    $rows = $stmt ? $stmt->fetchAll() : [];
    if (!is_array($rows)) $rows = [];

    $out = [];
    foreach ($rows as $r) {
        if (!is_array($r)) continue;
        $out[] = [
            'id'         => (int)($r['id'] ?? 0),
            'title'      => (string)($r['nav_label'] ?? ''),
            'url'        => (string)($r['slug'] ?? ''),
            'area'       => (string)($r['nav_area'] ?? ''),
            'sort_order' => (int)($r['nav_order'] ?? 0),
        ];
    }

    json_response(['items' => $out], 200);
}

// --- /events ---
if ($method === 'GET' && $sub === '/events') {
    $categorySlug = normalize_event_category_slug((string)($_GET['category'] ?? '')); // legacy single filter
    $categoriesRaw = trim((string)($_GET['categories'] ?? ''));
    $categorySlugs = [];
    if ($categoriesRaw !== '') {
        $parts = explode(',', $categoriesRaw);
        foreach ($parts as $p) {
            $slug = normalize_event_category_slug((string)$p);
            if ($slug !== '') {
                $categorySlugs[] = $slug;
            }
        }
        $categorySlugs = array_values(array_unique($categorySlugs));
    } elseif ($categorySlug !== '') {
        $categorySlugs = [$categorySlug];
    }
    $rawLimit = strtolower(trim((string)($_GET['limit'] ?? '6')));
    if ($rawLimit === 'all') {
        $limit = 500;
    } else {
        $limit = (int)$rawLimit;
        if ($limit <= 0) $limit = 6;
        if ($limit > 500) $limit = 500;
    }
    $includePast = !empty($_GET['include_past']);
    $hasCategoryColor = api_db_column_exists($pdo, 'event_categories', 'color_hex');
    $hasCategoryLogo = api_db_column_exists($pdo, 'event_categories', 'logo_media_id');
    $hasEventSubtitle = api_db_column_exists($pdo, 'events', 'subtitle');
    $supportsMulti = api_db_table_exists($pdo, 'event_category_map')
        && api_db_column_exists($pdo, 'events', 'event_date_from')
        && api_db_column_exists($pdo, 'events', 'event_date_to');

    if ($supportsMulti) {
        $sql = "
            SELECT
              e.id,
              e.title,
              " . ($hasEventSubtitle ? 'e.subtitle' : "''") . " AS subtitle,
              e.description,
              e.event_date,
              e.event_date_from,
              e.event_date_to,
              e.image_media_id,
              e.youtube_url,
              e.sort_order,
              m.focus_x AS image_focus_x,
              m.focus_y AS image_focus_y,
              GROUP_CONCAT(DISTINCT c.name ORDER BY c.sort_order ASC, c.slug ASC SEPARATOR ', ') AS category_names,
              GROUP_CONCAT(DISTINCT c.slug ORDER BY c.sort_order ASC, c.slug ASC SEPARATOR ',') AS category_slugs,
              " . ($hasCategoryColor
                ? "GROUP_CONCAT(DISTINCT c.color_hex ORDER BY c.sort_order ASC, c.slug ASC SEPARATOR ',')"
                : "''") . " AS category_colors
            FROM events e
            LEFT JOIN event_category_map ecm ON ecm.event_id = e.id
            LEFT JOIN event_categories c ON c.id = ecm.category_id AND c.is_deleted = 0
            LEFT JOIN media_items m ON m.id = e.image_media_id AND m.is_deleted = 0
            WHERE e.is_deleted = 0
              AND e.is_published = 1
        ";
        $params = [];
        if ($categorySlugs !== []) {
            $inParts = [];
            foreach ($categorySlugs as $idx => $slugVal) {
                $ph = ':category_slug_' . $idx;
                $inParts[] = $ph;
                $params[$ph] = $slugVal;
            }
            $sql .= " AND EXISTS (
                SELECT 1
                FROM event_category_map x
                JOIN event_categories xc ON xc.id = x.category_id
                WHERE x.event_id = e.id
                  AND xc.is_deleted = 0
                  AND xc.slug IN (" . implode(',', $inParts) . ")
            )";
        }
        if (!$includePast) {
            $sql .= " AND (COALESCE(e.event_date_to, e.event_date_from, DATE(e.event_date)) IS NULL OR COALESCE(e.event_date_to, e.event_date_from, DATE(e.event_date)) >= CURDATE())";
        }
        $sql .= "
            GROUP BY e.id
            ORDER BY
              CASE WHEN COALESCE(e.event_date_from, DATE(e.event_date)) IS NULL THEN 1 ELSE 0 END ASC,
              COALESCE(e.event_date_from, DATE(e.event_date)) ASC,
              e.id DESC
            LIMIT :lim
        ";
    } else {
        $sql = "
            SELECT
              e.id,
              e.title,
              " . ($hasEventSubtitle ? 'e.subtitle' : "''") . " AS subtitle,
              e.description,
              e.event_date,
              e.image_media_id,
              e.youtube_url,
              e.sort_order,
              m.focus_x AS image_focus_x,
              m.focus_y AS image_focus_y,
              c.name AS category_name,
              c.slug AS category_slug,
              " . ($hasCategoryColor ? 'c.color_hex' : "''") . " AS category_color
            FROM events e
            LEFT JOIN event_categories c ON c.id = e.category_id
            LEFT JOIN media_items m ON m.id = e.image_media_id AND m.is_deleted = 0
            WHERE e.is_deleted = 0
              AND e.is_published = 1
        ";
        $params = [];
        if ($categorySlugs !== []) {
            $inParts = [];
            foreach ($categorySlugs as $idx => $slugVal) {
                $ph = ':category_slug_' . $idx;
                $inParts[] = $ph;
                $params[$ph] = $slugVal;
            }
            $sql .= " AND c.slug IN (" . implode(',', $inParts) . ")";
        }
        if (!$includePast) {
            $sql .= " AND (e.event_date IS NULL OR DATE(e.event_date) >= CURDATE())";
        }
        $sql .= "
            ORDER BY
              CASE WHEN e.event_date IS NULL THEN 1 ELSE 0 END ASC,
              e.event_date ASC,
              e.id DESC
            LIMIT :lim
        ";
    }

    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();
    if (!is_array($rows)) $rows = [];

    $eventIds = [];
    $items = [];
    foreach ($rows as $r) {
        if (!is_array($r)) continue;
        $eventId = (int)($r['id'] ?? 0);
        if ($eventId <= 0) continue;
        $eventIds[] = $eventId;
        $mid = (int)($r['image_media_id'] ?? 0);
        $dateFrom = trim((string)($r['event_date_from'] ?? ''));
        $dateTo = trim((string)($r['event_date_to'] ?? ''));
        if ($dateFrom === '' && trim((string)($r['event_date'] ?? '')) !== '') {
            $dateFrom = (string)date('Y-m-d', (int)strtotime((string)$r['event_date']));
        }
        $catNames = trim((string)($r['category_names'] ?? ''));
        $catSlugs = trim((string)($r['category_slugs'] ?? ''));
        $catColors = trim((string)($r['category_colors'] ?? ''));
        if ($catNames === '') {
            $catNames = trim((string)($r['category_name'] ?? ''));
        }
        if ($catSlugs === '') {
            $catSlugs = trim((string)($r['category_slug'] ?? ''));
        }
        if ($catColors === '') {
            $catColors = trim((string)($r['category_color'] ?? ''));
        }
        $catNamesArr = $catNames !== '' ? array_values(array_filter(array_map('trim', explode(',', $catNames)), static fn(string $v): bool => $v !== '')) : [];
        $catSlugsArr = $catSlugs !== '' ? array_values(array_filter(array_map('trim', explode(',', $catSlugs)), static fn(string $v): bool => $v !== '')) : [];
        $catColorsArr = $catColors !== '' ? array_values(array_filter(array_map(static fn(string $v): string => strtoupper(trim($v)), explode(',', $catColors)), static fn(string $v): bool => preg_match('/^#[0-9A-F]{6}$/', $v) === 1)) : [];
        $catColorMap = [];
        foreach ($catSlugsArr as $idx => $slugVal) {
            $slugKey = strtolower(trim((string)$slugVal));
            if ($slugKey === '') continue;
            $colorVal = $catColorsArr[$idx] ?? '';
            if ($colorVal !== '') {
                $catColorMap[$slugKey] = $colorVal;
            }
        }
        $items[] = [
            'id' => $eventId,
            'title' => (string)($r['title'] ?? ''),
            'subtitle' => trim((string)($r['subtitle'] ?? '')),
            'text' => (string)($r['description'] ?? ''),
            'date' => (string)($r['event_date'] ?? ''), // legacy compatibility
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'image_url' => $mid > 0 ? api_cms_path('/media/file?id=' . $mid) : '',
            'image_focus_x' => ($r['image_focus_x'] !== null && $r['image_focus_x'] !== '') ? (float)$r['image_focus_x'] : null,
            'image_focus_y' => ($r['image_focus_y'] !== null && $r['image_focus_y'] !== '') ? (float)$r['image_focus_y'] : null,
            'youtube_url' => (string)($r['youtube_url'] ?? ''),
            'category_name' => $catNames,
            'category_slug' => $catSlugs,
            'category_names' => $catNamesArr,
            'category_slugs' => $catSlugsArr,
            'category_colors' => $catColorsArr,
            'category_color_map' => $catColorMap,
            'category_logo_map' => [],
            'image_variants' => [],
            'category_links' => [],
        ];
    }

    if ($items !== [] && $hasCategoryLogo) {
        $allCategorySlugs = [];
        foreach ($items as $it) {
            if (!is_array($it)) continue;
            $slugs = is_array($it['category_slugs'] ?? null) ? $it['category_slugs'] : [];
            foreach ($slugs as $slugVal) {
                $slugKey = strtolower(trim((string)$slugVal));
                if ($slugKey !== '') {
                    $allCategorySlugs[$slugKey] = true;
                }
            }
        }
        $allCategorySlugs = array_keys($allCategorySlugs);
        if ($allCategorySlugs !== []) {
            $slugParts = [];
            $slugParams = [];
            foreach ($allCategorySlugs as $idx => $slugVal) {
                $ph = ':logo_slug_' . $idx;
                $slugParts[] = $ph;
                $slugParams[$ph] = $slugVal;
            }
            $logosSql = "
                SELECT slug, logo_media_id
                FROM event_categories
                WHERE is_deleted = 0
                  AND logo_media_id IS NOT NULL
                  AND logo_media_id > 0
                  AND slug IN (" . implode(',', $slugParts) . ")
            ";
            $logosStmt = $pdo->prepare($logosSql);
            foreach ($slugParams as $ph => $slugVal) {
                $logosStmt->bindValue($ph, $slugVal);
            }
            $logosStmt->execute();
            $logoRows = $logosStmt->fetchAll();
            $logoMapBySlug = [];
            foreach (is_array($logoRows) ? $logoRows : [] as $row) {
                if (!is_array($row)) continue;
                $slugKey = strtolower(trim((string)($row['slug'] ?? '')));
                $logoMediaId = (int)($row['logo_media_id'] ?? 0);
                if ($slugKey === '' || $logoMediaId <= 0) continue;
                $logoMapBySlug[$slugKey] = api_cms_path('/media/file?id=' . $logoMediaId);
            }
            if ($logoMapBySlug !== []) {
                foreach ($items as $idx => $it) {
                    if (!is_array($it)) continue;
                    $slugs = is_array($it['category_slugs'] ?? null) ? $it['category_slugs'] : [];
                    $itemLogoMap = [];
                    foreach ($slugs as $slugVal) {
                        $slugKey = strtolower(trim((string)$slugVal));
                        if ($slugKey !== '' && isset($logoMapBySlug[$slugKey])) {
                            $itemLogoMap[$slugKey] = $logoMapBySlug[$slugKey];
                        }
                    }
                    $items[$idx]['category_logo_map'] = $itemLogoMap;
                }
            }
        }
    }

    if ($eventIds !== [] && api_db_table_exists($pdo, 'event_category_media')) {
        $ph = implode(',', array_fill(0, count($eventIds), '?'));
        $vsql = "
            SELECT
              ecm.event_id,
              ec.slug AS category_slug,
              ec.name AS category_name,
              " . ($hasCategoryColor ? 'ec.color_hex' : "''") . " AS category_color,
              ecm.media_id,
              mi.focus_x AS focus_x,
              mi.focus_y AS focus_y
            FROM event_category_media ecm
            JOIN event_categories ec ON ec.id = ecm.category_id AND ec.is_deleted = 0
            JOIN media_items mi ON mi.id = ecm.media_id AND mi.is_deleted = 0
            WHERE ecm.event_id IN ($ph)
            ORDER BY ecm.event_id ASC, ec.sort_order ASC, ec.name ASC
        ";
        $vst = $pdo->prepare($vsql);
        foreach ($eventIds as $i => $eid) {
            $vst->bindValue($i + 1, (int)$eid, PDO::PARAM_INT);
        }
        $vst->execute();
        $vrows = $vst->fetchAll();
        $variantsByEvent = [];
        foreach (is_array($vrows) ? $vrows : [] as $vr) {
            if (!is_array($vr)) continue;
            $eid = (int)($vr['event_id'] ?? 0);
            if ($eid <= 0) continue;
            $mid = (int)($vr['media_id'] ?? 0);
            if ($mid <= 0) continue;
            $variantsByEvent[$eid][] = [
                'category_slug' => trim((string)($vr['category_slug'] ?? '')),
                'category_name' => trim((string)($vr['category_name'] ?? '')),
                'category_color' => strtoupper(trim((string)($vr['category_color'] ?? ''))),
                'image_url' => api_cms_path('/media/file?id=' . $mid),
                'image_focus_x' => ($vr['focus_x'] !== null && $vr['focus_x'] !== '') ? (float)$vr['focus_x'] : null,
                'image_focus_y' => ($vr['focus_y'] !== null && $vr['focus_y'] !== '') ? (float)$vr['focus_y'] : null,
            ];
        }
        foreach ($items as $idx => $it) {
            $eid = (int)($it['id'] ?? 0);
            if ($eid > 0 && isset($variantsByEvent[$eid])) {
                $items[$idx]['image_variants'] = $variantsByEvent[$eid];
            }
        }
    }

    if ($eventIds !== [] && api_db_table_exists($pdo, 'event_category_links')) {
        $hasLinksType = api_db_column_exists($pdo, 'event_category_links', 'link_type');
        $hasLinksPdfMedia = api_db_column_exists($pdo, 'event_category_links', 'pdf_media_id');
        $hasLinksYoutubeStartAt = api_db_column_exists($pdo, 'event_category_links', 'youtube_start_at');
        $hasLinksYoutubeEndAt = api_db_column_exists($pdo, 'event_category_links', 'youtube_end_at');
        $ph = implode(',', array_fill(0, count($eventIds), '?'));
        $lsql = "
            SELECT
              ecl.event_id,
              ec.slug AS category_slug,
              ec.name AS category_name,
              " . ($hasLinksType ? 'ecl.link_type' : "'link'") . " AS link_type,
              ecl.label,
              ecl.url,
              " . ($hasLinksPdfMedia ? 'ecl.pdf_media_id' : "NULL") . " AS pdf_media_id,
              " . ($hasLinksYoutubeStartAt ? 'ecl.youtube_start_at' : "NULL") . " AS youtube_start_at,
              " . ($hasLinksYoutubeEndAt ? 'ecl.youtube_end_at' : "NULL") . " AS youtube_end_at,
              ecl.sort_order
            FROM event_category_links ecl
            JOIN event_categories ec ON ec.id = ecl.category_id AND ec.is_deleted = 0
            WHERE ecl.event_id IN ($ph)
            ORDER BY ecl.event_id ASC, ec.sort_order ASC, ec.name ASC, ecl.sort_order ASC, ecl.id ASC
        ";
        $lst = $pdo->prepare($lsql);
        foreach ($eventIds as $i => $eid) {
            $lst->bindValue($i + 1, (int)$eid, PDO::PARAM_INT);
        }
        $lst->execute();
        $lrows = $lst->fetchAll();
        $linksByEvent = [];
        foreach (is_array($lrows) ? $lrows : [] as $lr) {
            if (!is_array($lr)) continue;
            $eid = (int)($lr['event_id'] ?? 0);
            $type = strtolower(trim((string)($lr['link_type'] ?? 'link')));
            if (!in_array($type, ['link', 'youtube', 'pdf'], true)) {
                $type = 'link';
            }
            $label = trim((string)($lr['label'] ?? ''));
            $url = trim((string)($lr['url'] ?? ''));
            $pdfMediaId = (int)($lr['pdf_media_id'] ?? 0);
            if ($url === '' && $type === 'pdf' && $pdfMediaId > 0) {
                $url = api_cms_path('/media/file?id=' . $pdfMediaId);
            }
            if ($eid <= 0 || $label === '' || $url === '') continue;
            $linksByEvent[$eid][] = [
                'category_slug' => strtolower(trim((string)($lr['category_slug'] ?? ''))),
                'category_name' => trim((string)($lr['category_name'] ?? '')),
                'link_type' => $type,
                'label' => $label,
                'url' => $url,
                'pdf_media_id' => $pdfMediaId > 0 ? $pdfMediaId : 0,
                'youtube_start_at' => trim((string)($lr['youtube_start_at'] ?? '')),
                'youtube_end_at' => trim((string)($lr['youtube_end_at'] ?? '')),
                'sort_order' => (int)($lr['sort_order'] ?? 0),
            ];
        }
        foreach ($items as $idx => $it) {
            $eid = (int)($it['id'] ?? 0);
            if ($eid > 0 && isset($linksByEvent[$eid])) {
                $items[$idx]['category_links'] = $linksByEvent[$eid];
            }
        }
    }

    json_response(['items' => $items], 200);
}

// --- /news ---
if ($method === 'GET' && $sub === '/news') {
    $rawLimit = strtolower(trim((string)($_GET['limit'] ?? '6')));
    $limit = $rawLimit === 'all' ? 200 : (int)$rawLimit;
    if ($limit <= 0) $limit = 6;
    if ($limit > 200) $limit = 200;

    $categorySlugs = [];
    $categoriesRaw = trim((string)($_GET['categories'] ?? ''));
    if ($categoriesRaw !== '') {
        foreach (explode(',', $categoriesRaw) as $p) {
            $slug = normalize_event_category_slug((string)$p);
            if ($slug !== '') $categorySlugs[] = $slug;
        }
        $categorySlugs = array_values(array_unique($categorySlugs));
    }

    $sql = "
        SELECT
          n.id, n.title, n.slug, n.teaser, n.content_json, n.image_media_id, n.is_published, n.published_at,
          c.name AS category_name, c.slug AS category_slug
        FROM news n
        LEFT JOIN news_categories c ON c.id = n.category_id AND c.is_deleted = 0
        WHERE n.is_deleted = 0
          AND n.is_published = 1
    ";
    $params = [];
    if ($categorySlugs !== []) {
        $ph = [];
        foreach ($categorySlugs as $i => $s) {
            $k = ':cat_' . $i;
            $ph[] = $k;
            $params[$k] = $s;
        }
        $sql .= " AND c.slug IN (" . implode(',', $ph) . ")";
    }
    $sql .= " ORDER BY COALESCE(n.published_at, n.created_at) DESC, n.id DESC LIMIT :lim";

    $st = $pdo->prepare($sql);
    foreach ($params as $k => $v) $st->bindValue($k, $v);
    $st->bindValue(':lim', $limit, PDO::PARAM_INT);
    $st->execute();
    $rows = $st->fetchAll();
    if (!is_array($rows)) $rows = [];

    $items = [];
    foreach ($rows as $r) {
        if (!is_array($r)) continue;
        $mid = (int)($r['image_media_id'] ?? 0);
        $items[] = [
            'id' => (int)($r['id'] ?? 0),
            'title' => (string)($r['title'] ?? ''),
            'slug' => (string)($r['slug'] ?? ''),
            'teaser' => (string)($r['teaser'] ?? ''),
            'content_json' => (string)($r['content_json'] ?? ''),
            'image_url' => $mid > 0 ? api_cms_path('/media/file?id=' . $mid) : '',
            'category_name' => (string)($r['category_name'] ?? ''),
            'category_slug' => (string)($r['category_slug'] ?? ''),
            'published_at' => (string)($r['published_at'] ?? ''),
        ];
    }

    json_response(['items' => $items], 200);
}

if ($method === 'GET' && preg_match('/^\/news\/([a-z0-9-]+)$/', $sub, $m) === 1) {
    $slug = (string)$m[1];
    $st = $pdo->prepare("
        SELECT
          n.id, n.title, n.slug, n.teaser, n.content_json, n.image_media_id, n.is_published, n.published_at,
          c.name AS category_name, c.slug AS category_slug
        FROM news n
        LEFT JOIN news_categories c ON c.id = n.category_id AND c.is_deleted = 0
        WHERE n.slug = :slug
          AND n.is_deleted = 0
          AND n.is_published = 1
        LIMIT 1
    ");
    $st->execute([':slug' => $slug]);
    $r = $st->fetch();
    if (!is_array($r)) {
        json_response(['ok' => false, 'error' => 'not_found'], 404);
    }
    $mid = (int)($r['image_media_id'] ?? 0);
    json_response([
        'id' => (int)($r['id'] ?? 0),
        'title' => (string)($r['title'] ?? ''),
        'slug' => (string)($r['slug'] ?? ''),
        'teaser' => (string)($r['teaser'] ?? ''),
        'content_json' => (string)($r['content_json'] ?? ''),
        'image_url' => $mid > 0 ? api_cms_path('/media/file?id=' . $mid) : '',
        'category_name' => (string)($r['category_name'] ?? ''),
        'category_slug' => (string)($r['category_slug'] ?? ''),
        'published_at' => (string)($r['published_at'] ?? ''),
    ], 200);
}

// --- GET /api/v1/pages/{slug} (single page with blocks + SEO, path param) ---
if (preg_match('/^\/pages\/(.+)$/', $sub, $m)) {
    if ($method !== 'GET') {
        json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
    }
    $slugRaw = $m[1];
    if (!preg_match('/^[a-z0-9][a-z0-9\/-]*$/', $slugRaw)) {
        json_response(['ok' => false, 'error' => 'bad_request'], 400);
    }
    $slugDb = normalize_slug($slugRaw);

    $stmt = $pdo->prepare("
        SELECT
            p.id,
            p.slug,
            p.title,
            p.frontend_title,
            p.subtitle,
            sm.meta_title,
            sm.meta_description,
            p.content_json,
            p.updated_at
        FROM pages p
        LEFT JOIN seo_meta sm
            ON sm.entity_type = 'page'
           AND sm.entity_id = p.id
        WHERE p.slug = :s
          AND p.is_deleted = 0
          AND p.status = 'live'
        LIMIT 1
    ");
    $stmt->execute([':s' => $slugDb]);
    $row = $stmt->fetch();

    // Fallback: Frontend fragt fuer die Startseite immer "home" ab; die Startseite
    // selbst kann aber mit Slug "/" gepflegt sein (is_home=1). Dann darueber matchen.
    if (!is_array($row) && strtolower($slugRaw) === 'home') {
        $stmtHome = $pdo->prepare("
            SELECT
                p.id, p.slug, p.title, p.frontend_title, p.subtitle,
                sm.meta_title, sm.meta_description, p.content_json, p.updated_at
            FROM pages p
            LEFT JOIN seo_meta sm
                ON sm.entity_type = 'page' AND sm.entity_id = p.id
            WHERE (p.is_home = 1 OR p.slug = '/') AND p.is_deleted = 0 AND p.status = 'live'
            ORDER BY p.is_home DESC
            LIMIT 1
        ");
        $stmtHome->execute();
        $row = $stmtHome->fetch();
    }

    if (!is_array($row)) {
        json_response(['ok' => false, 'error' => 'not_found'], 404);
    }

    $blocks = [];
    $cj = trim((string)($row['content_json'] ?? ''));
    if ($cj !== '') {
        $decoded = json_decode($cj, true);
        if (is_array($decoded)) {
            $b      = $decoded['blocks'] ?? $decoded;
            $blocks = is_array($b) ? $b : [];
        }
    }

    $updAt = (string)($row['updated_at'] ?? '');
    json_response([
        'id'         => (int)$row['id'],
        'slug'       => ltrim((string)$row['slug'], '/'),
        'title'      => (string)($row['title'] ?? ''),
        'frontend_title' => (string)($row['frontend_title'] ?? ''),
        'subtitle'   => (string)($row['subtitle'] ?? ''),
        'seo'        => [
            'title'       => (string)($row['meta_title'] ?? ''),
            'description' => (string)($row['meta_description'] ?? ''),
        ],
        'blocks'     => $blocks,
        'updated_at' => $updAt !== '' ? gmdate('c', (int)strtotime($updAt)) : null,
    ]);
}

// --- GET /api/v1/media/{id} (media metadata) ---
if (preg_match('/^\/media\/(.+)$/', $sub, $m)) {
    if ($method !== 'GET') {
        json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
    }
    if (!preg_match('/^\d+$/', $m[1]) || (int)$m[1] <= 0) {
        json_response(['ok' => false, 'error' => 'bad_request'], 400);
    }
    $mediaId = (int)$m[1];

    $stmt = $pdo->prepare(
        "SELECT id, display_filename, mime, size_bytes, width, height, alt_text, focus_x, focus_y
         FROM media_items
         WHERE id = :id AND is_deleted = 0"
    );
    $stmt->execute([':id' => $mediaId]);
    $row = $stmt->fetch();
    if (!is_array($row)) {
        json_response(['ok' => false, 'error' => 'not_found'], 404);
    }

    $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host    = (string)($_SERVER['HTTP_HOST'] ?? '');
    $baseUrl = $host !== '' ? "{$scheme}://{$host}" : '';

    json_response([
        'id'         => (int)$row['id'],
        'url'        => $baseUrl . api_cms_path('/media/file?id=' . $mediaId),
        'mime'       => (string)($row['mime'] ?? ''),
        'size_bytes' => (int)($row['size_bytes'] ?? 0),
        'width'      => ($row['width'] !== null && $row['width'] !== '') ? (int)$row['width'] : null,
        'height'     => ($row['height'] !== null && $row['height'] !== '') ? (int)$row['height'] : null,
        'alt'        => (string)($row['alt_text'] ?? ''),
        'focus_x'    => ($row['focus_x'] !== null && $row['focus_x'] !== '') ? (float)$row['focus_x'] : null,
        'focus_y'    => ($row['focus_y'] !== null && $row['focus_y'] !== '') ? (float)$row['focus_y'] : null,
    ]);
}

json_response(['ok' => false, 'error' => 'not_found'], 404);
