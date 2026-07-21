<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Run this script via CLI only.\n");
    exit(1);
}

if ($argc < 3) {
    fwrite(STDERR, "Usage: php Verwaltung/scripts/check_admin_login.php <email> <password>\n");
    exit(1);
}

$email = trim((string)$argv[1]);
$password = (string)$argv[2];

$envFile = dirname(__DIR__) . '/.env';
if (!is_file($envFile)) {
    fwrite(STDERR, ".env file not found: {$envFile}\n");
    exit(1);
}

$lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
foreach ($lines as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#') {
        continue;
    }
    $eq = strpos($line, '=');
    if ($eq === false) {
        continue;
    }
    $key = trim(substr($line, 0, $eq));
    $value = trim(substr($line, $eq + 1));
    if (
        strlen($value) >= 2
        && (($value[0] === '"' && $value[strlen($value) - 1] === '"') || ($value[0] === "'" && $value[strlen($value) - 1] === "'"))
    ) {
        $value = substr($value, 1, -1);
    }
    $_ENV[$key] = $value;
}

$dbHost = (string)($_ENV['DB_HOST'] ?? 'localhost');
$dbName = (string)($_ENV['DB_NAME'] ?? '');
$dbUser = (string)($_ENV['DB_USER'] ?? '');
$dbPass = (string)($_ENV['DB_PASS'] ?? '');

try {
    $pdo = new PDO(
        'mysql:host=' . $dbHost . ';dbname=' . $dbName . ';charset=utf8mb4',
        $dbUser,
        $dbPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Throwable $e) {
    fwrite(STDERR, "DB connection failed: " . $e->getMessage() . "\n");
    exit(2);
}

$stmt = $pdo->prepare(
    'SELECT id, email, password_hash, role, is_active, deleted_at
     FROM admin_users
     WHERE email = ?
     LIMIT 1'
);
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user === false) {
    echo json_encode([
        'email' => $email,
        'found' => false
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
    exit(0);
}

$hash = (string)($user['password_hash'] ?? '');
$isActive = (int)($user['is_active'] ?? 0) === 1;
$deletedAt = $user['deleted_at'] ?? null;

echo json_encode([
    'email' => (string)$user['email'],
    'found' => true,
    'id' => (int)$user['id'],
    'role' => (string)($user['role'] ?? ''),
    'is_active' => $isActive,
    'deleted_at' => $deletedAt,
    'hash_prefix' => substr($hash, 0, 4),
    'hash_length' => strlen($hash),
    'password_verify' => ($hash !== '' ? password_verify($password, $hash) : false),
    'password_needs_rehash' => ($hash !== '' ? password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => 12]) : null),
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;

