<?php
declare(strict_types=1);

/**
 * DB + Auto-Migrations (MySQL/MariaDB, PDO)
 * - config/db.php pro Installation
 * - Migrationen: /migrations/*.sql
 * - Status: schema_migrations
 *
 * Security-Härtung:
 * - db_migrate_if_needed() läuft NUR per CLI oder wenn CMS_ALLOW_MIGRATIONS === true gesetzt wurde
 */

/**
 * PDOStatement-Subclass für DB-Timing (System Health / Request-Timing).
 * Misst nur execute()-Zeit (DB Roundtrip + DB Work).
 */
if (!class_exists('CmsProfiledStatement')) {
    class CmsProfiledStatement extends PDOStatement
    {
        public function execute(?array $params = null): bool
        {
            $t0 = microtime(true);
            try {
                return parent::execute($params);
            } finally {
                $ms = (microtime(true) - $t0) * 1000.0;
                if (function_exists('cms_prof_add_db')) {
                    cms_prof_add_db($ms);
                }
            }
        }
    }
}

function db_config(): array
{
    $path = __DIR__ . '/../config/db.php';
    $cfg = is_file($path) ? require $path : [];
    return is_array($cfg) ? $cfg : [];
}

/**
 * Kleiner Cache-Wrapper (APCu optional)
 */
function cms_cache_get(string $key): mixed
{
    if (function_exists('apcu_fetch')) {
        $success = false;
        $val = apcu_fetch($key, $success);
        return $success ? $val : null;
    }
    return null;
}

function cms_cache_set(string $key, mixed $value, int $ttlSeconds): bool
{
    if (function_exists('apcu_store')) {
        return (bool)apcu_store($key, $value, $ttlSeconds);
    }
    return false;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $cfg = db_config();

    $host = (string)($cfg['host'] ?? '127.0.0.1');
    $port = (int)($cfg['port'] ?? 3306);

    // Kompatibilität: alte Keys (database/username/password) + neue Keys (name/user/pass)
    $name = (string)($cfg['name'] ?? $cfg['database'] ?? '');
    $user = (string)($cfg['user'] ?? $cfg['username'] ?? '');
    $pass = (string)($cfg['pass'] ?? $cfg['password'] ?? '');

    $name = trim($name);
    $user = trim($user);

    if ($name === '' || $user === '') {
        throw new RuntimeException(
            'DB config incomplete. Please set config/db.php (expected keys: name/user/pass OR database/username/password).'
        );
    }

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

        // Wichtig: damit execute()-Timing über CmsProfiledStatement läuft
        PDO::ATTR_STATEMENT_CLASS    => [CmsProfiledStatement::class],
    ]);

    return $pdo;
}

function db_migrations_dir(): string
{
    $dir = realpath(__DIR__ . '/../migrations');
    if ($dir === false) {
        throw new RuntimeException('Migrations dir not found.');
    }
    return $dir;
}

function db_migration_files(): array
{
    $dir = db_migrations_dir();
    $files = glob($dir . '/*.sql');
    if (!is_array($files)) $files = [];
    sort($files, SORT_NATURAL);
    return $files;
}

function db_apply_migration(PDO $pdo, string $id, string $sql): void
{
    $parts = preg_split('/;\s*(\r\n|\r|\n)/', $sql);
    if (!is_array($parts)) $parts = [$sql];

    $pdo->beginTransaction();
    try {
        foreach ($parts as $part) {
            $part = trim((string)$part);
            if ($part === '') continue;
            $pdo->exec($part);
        }

        $stmt = $pdo->prepare("INSERT INTO schema_migrations (id, applied_at) VALUES (:id, NOW())");
        $stmt->execute([':id' => $id]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function db_migrate_if_needed(): void
{
    // Migrationen dürfen nur explizit (Admin-Migrationsseite) oder per CLI laufen.
    if (PHP_SAPI !== 'cli' && !(defined('CMS_ALLOW_MIGRATIONS') && CMS_ALLOW_MIGRATIONS === true)) {
        return;
    }

    static $ranThisRequest = false;
    if ($ranThisRequest) return;
    $ranThisRequest = true;

    // Throttle: max. alle 30 Sekunden prüfen (wenn Cache verfügbar ist)
    $ttlSeconds = 30;
    $cacheKey = 'cms_db_migrate_next_check';

    $next = cms_cache_get($cacheKey);
    if (is_int($next) && $next > time()) {
        return;
    }
    cms_cache_set($cacheKey, time() + $ttlSeconds, $ttlSeconds);

    $pdo = db();

    // Ensure schema_migrations exists (stable collation)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS schema_migrations (
            id VARCHAR(190) NOT NULL,
            applied_at DATETIME NOT NULL,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $applied = [];
    $rows = $pdo->query("SELECT id FROM schema_migrations")->fetchAll();
    if (is_array($rows)) {
        foreach ($rows as $r) {
            if (!is_array($r)) continue;
            $applied[(string)($r['id'] ?? '')] = true;
        }
    }

    foreach (db_migration_files() as $file) {
        $id = basename($file);
        if (isset($applied[$id])) continue;

        $sql = file_get_contents($file);
        if (!is_string($sql) || trim($sql) === '') {
            throw new RuntimeException("Migration empty/unreadable: {$id}");
        }

        db_apply_migration($pdo, $id, $sql);
    }
}

/**
 * Backward compatible:
 * - db_table_exists($pdo, 'table')  (neu)
 * - db_table_exists('table')        (alt)
 */
function db_table_exists(mixed $pdoOrTable, ?string $table = null): bool
{
    $pdo = null;
    $t = '';

    if ($pdoOrTable instanceof PDO) {
        $pdo = $pdoOrTable;
        $t = (string)($table ?? '');
    } else {
        // alter Call: db_table_exists('my_table')
        $pdo = db();
        $t = (string)$pdoOrTable;
    }

    $t = trim($t);
    if ($t === '') return false;

    $stmt = $pdo->prepare("SHOW TABLES LIKE :t");
    $stmt->execute([':t' => $t]);
    return (bool)$stmt->fetchColumn();
}
