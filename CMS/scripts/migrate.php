<?php
declare(strict_types=1);

/**
 * scripts/migrate.php
 * CLI-only Migrations Runner:
 * - runs /migrations/*.sql in natural order
 * - tracks applied migrations in schema_migrations
 * - applies each migration exactly once
 *
 * Run:
 *   php scripts/migrate.php
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

$root = realpath(__DIR__ . '/..');
if ($root === false) {
    fwrite(STDERR, "Cannot resolve project root.\n");
    exit(1);
}

$cfgPath = $root . '/config/db.php';
if (!is_file($cfgPath)) {
    fwrite(STDERR, "Missing config/db.php\n");
    exit(1);
}

$cfg = require $cfgPath;
if (!is_array($cfg)) {
    fwrite(STDERR, "config/db.php must return an array.\n");
    exit(1);
}

$host    = (string)($cfg['host'] ?? '127.0.0.1');
$port    = (int)($cfg['port'] ?? 3306);
$db      = (string)($cfg['database'] ?? '');
$user    = (string)($cfg['username'] ?? '');
$pass    = (string)($cfg['password'] ?? '');
$charset = (string)($cfg['charset'] ?? 'utf8mb4');

if ($db === '' || $user === '') {
    fwrite(STDERR, "config/db.php missing database/username.\n");
    exit(1);
}

$dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, "DB connect failed: " . $e->getMessage() . "\n");
    fwrite(STDERR, "Hint: Run this on the SERVER where the DB host is reachable.\n");
    exit(1);
}

// 1) schema_migrations (stable collation)
$pdo->exec("
  CREATE TABLE IF NOT EXISTS schema_migrations (
    version VARCHAR(190) NOT NULL,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (version)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// 2) read applied
$applied = [];
$stmt = $pdo->query("SELECT version FROM schema_migrations");
foreach ($stmt->fetchAll() as $row) {
    $applied[(string)$row['version']] = true;
}

// 3) collect migration files
$migrationsDir = $root . '/migrations';
if (!is_dir($migrationsDir)) {
    fwrite(STDERR, "Missing migrations/ directory.\n");
    exit(1);
}

$files = glob($migrationsDir . '/*.sql');
if ($files === false) $files = [];
sort($files, SORT_NATURAL);

if (!$files) {
    echo "No migrations found.\n";
    exit(0);
}

$ran = 0;

foreach ($files as $file) {
    $version = basename($file);

    if (isset($applied[$version])) {
        continue;
    }

    $sql = file_get_contents($file);
    if ($sql === false) {
        fwrite(STDERR, "Cannot read migration: {$version}\n");
        exit(1);
    }

    $sql = trim($sql);

    echo "Applying: {$version}\n";

    if ($sql !== '') {
        // ALTER TABLE etc. should be executed directly (no transaction)
        $pdo->exec($sql);
    }

    $ins = $pdo->prepare("INSERT INTO schema_migrations (version) VALUES (:v)");
    $ins->execute([':v' => $version]);

    echo "Applied:  {$version}\n\n";
    $ran++;
}

echo "Done. Ran {$ran} migration(s).\n";
