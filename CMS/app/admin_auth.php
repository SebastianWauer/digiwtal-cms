<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

require_once __DIR__ . '/Core/Paths.php';
require_once __DIR__ . '/Http/Redirect.php';

use App\Core\Paths;
use App\Http\Redirect;
use App\Repositories\RolePermissionRepositoryDb;

const ADMIN_COOKIE_NAME = 'CMS_ADMIN_TOKEN';
const ADMIN_TOKEN_TTL_SECONDS = 60 * 60 * 24 * 14; // 14 Tage

function admin_ip(): string
{
    return (string)($_SERVER['REMOTE_ADDR'] ?? '');
}

function admin_user_agent(): string
{
    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    return mb_substr($ua, 0, 255);
}

function admin_pdo(): PDO
{
    return db();
}

/**
 * Defensiver Schema-Check (ohne Migration-Sideeffects).
 * Admin-Login darf niemals an optionalen Spalten scheitern.
 */
function admin_db_column_exists(PDO $pdo, string $table, string $column): bool
{
    $table = trim($table);
    $column = trim($column);
    if ($table === '' || $column === '') return false;

    static $cache = [];
    $key = strtolower($table . ':' . $column);
    if (array_key_exists($key, $cache)) {
        return (bool)$cache[$key];
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT 1\n" .
            "FROM information_schema.columns\n" .
            "WHERE table_schema = DATABASE()\n" .
            "  AND table_name = :t\n" .
            "  AND column_name = :c\n" .
            "LIMIT 1"
        );
        $stmt->execute([':t' => $table, ':c' => $column]);
        $exists = (bool)$stmt->fetchColumn();
        $cache[$key] = $exists;
        return $exists;
    } catch (\Throwable) {
        $cache[$key] = false;
        return false;
    }
}

function admin_hash_token(string $token): string
{
    return hash('sha256', $token);
}

function admin_generate_token(): string
{
    return bin2hex(random_bytes(32));
}

/**
 * Current user: request-scope caching (massiver Performance-Hebel)
 */
function admin_current_user(): ?array
{
    static $loaded = false;
    static $cached = null;

    if ($loaded) {
        return is_array($cached) ? $cached : null;
    }
    $loaded = true;

    $token = (string)($_COOKIE[ADMIN_COOKIE_NAME] ?? '');
    if ($token === '') {
        $cached = null;
        return null;
    }

    $hash = admin_hash_token($token);
    $pdo  = admin_pdo();

    $stmt = $pdo->prepare("
        SELECT lt.user_id, lt.expires_at
        FROM login_tokens lt
        JOIN users u ON u.id = lt.user_id
        WHERE lt.token_hash = :h
          AND u.enabled = 1
        LIMIT 1
    ");
    $stmt->execute([':h' => $hash]);
    $row = $stmt->fetch();
    if (!is_array($row)) {
        $cached = null;
        return null;
    }

    $expiresAt = (string)($row['expires_at'] ?? '');
    if ($expiresAt === '' || strtotime($expiresAt) < time()) {
        $cached = null;
        return null;
    }

    $uid = (int)($row['user_id'] ?? 0);
    if ($uid <= 0) {
        $cached = null;
        return null;
    }

    $stmt = $pdo->prepare("SELECT id, username, name, email, enabled FROM users WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $uid]);
    $u = $stmt->fetch();
    if (!is_array($u)) {
        $cached = null;
        return null;
    }

    $cached = $u;
    return $u;
}

function admin_require_login(): array
{
    $u = admin_current_user();
    if (!$u) {
        Redirect::to(Paths::LOGIN, 302);
        exit;
    }
    return $u;
}

/**
 * Prüft, ob ein User die Rolle key='admin' besitzt.
 * (zentral, nicht delegierbar für Systembereiche)
 *
 * Request-scope caching: sonst sehr teuer (Sidebar + Layout + Controllers).
 */
function admin_user_has_admin_role(int $userId): bool
{
    static $cache = []; // uid => bool

    $userId = (int)$userId;
    if ($userId <= 0) return false;

    if (array_key_exists($userId, $cache)) {
        return (bool)$cache[$userId];
    }

    try {
        $pdo = admin_pdo();
        $st = $pdo->prepare("
            SELECT 1
            FROM user_roles ur
            JOIN roles r ON r.id = ur.role_id
            WHERE ur.user_id = :uid
              AND r.is_deleted = 0
              AND r.`key` = 'admin'
            LIMIT 1
        ");
        $st->execute([':uid' => $userId]);
        $cache[$userId] = (bool)$st->fetchColumn();
        return (bool)$cache[$userId];
    } catch (\Throwable) {
        $cache[$userId] = false;
        return false;
    }
}

/**
 * SystemUser ist NICHT "Role=admin", sondern:
 * - username exakt 'admin'
 * - UND Rolle key='admin'
 */
function admin_is_system_user(array $u): bool
{
    $uid = (int)($u['id'] ?? 0);
    if ($uid <= 0) return false;

    $username = (string)($u['username'] ?? '');
    if ($username !== 'admin') return false;

    return admin_user_has_admin_role($uid);
}

function admin_require_system_user(): array
{
    $u = admin_require_login();

    if (!admin_is_system_user($u)) {
        http_response_code(404);
        echo 'Not Found';
        exit;
    }

    return $u;
}

/**
 * Permission check: request-scope caching (massiver Hebel).
 */
function admin_can(string $permissionKey): bool
{
    static $permCache = []; // uid => [perm => bool]

    $u = admin_current_user();
    if (!$u) return false;

    $uid = (int)($u['id'] ?? 0);
    if ($uid <= 0) return false;

    // Root-Regel: admin-Rolle => alles erlaubt
    if (admin_user_has_admin_role($uid)) {
        return true;
    }

    if (isset($permCache[$uid]) && array_key_exists($permissionKey, $permCache[$uid])) {
        return (bool)$permCache[$uid][$permissionKey];
    }

    $pdo = admin_pdo();
    $repo = new RolePermissionRepositoryDb($pdo);
    $ok = $repo->userHasPermission($uid, $permissionKey);

    $permCache[$uid] ??= [];
    $permCache[$uid][$permissionKey] = (bool)$ok;

    return (bool)$ok;
}

function admin_require_perm(string $permissionKey): array
{
    $u = admin_require_login();

    if (!admin_can($permissionKey)) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }

    return $u;
}

function admin_logout(): void
{
    // Aktuellen User für Audit merken (BEVOR Token gelöscht wird)
    $u = admin_current_user();
    $uid = is_array($u) ? (int)($u['id'] ?? 0) : null;

    // Token aus Cookie lesen
    $token = (string)($_COOKIE[ADMIN_COOKIE_NAME] ?? '');
    if ($token !== '') {
        try {
            $pdo = admin_pdo();
            $hash = admin_hash_token($token);

            $st = $pdo->prepare(
                "DELETE FROM login_tokens WHERE token_hash = :h"
            );
            $st->execute([':h' => $hash]);
        } catch (\Throwable) {
            // Logout darf niemals hart fehlschlagen
        }
    }

    // 🔹 Audit-Log: Logout
    try {
        if ($uid && $uid > 0) {
            $pdo = admin_pdo();
            $st = $pdo->prepare("
                INSERT INTO login_audit (user_id, action, ip, user_agent, meta_json, created_at)
                VALUES (:uid, 'logout', :ip, :ua, NULL, NOW())
            ");
            $st->execute([
                ':uid' => $uid,
                ':ip'  => admin_ip(),
                ':ua'  => admin_user_agent(),
            ]);
        }
    } catch (\Throwable) {
        // Audit darf Logout niemals blockieren
    }

    // Cookie löschen
    setcookie(ADMIN_COOKIE_NAME, '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    // CSRF-Token ebenfalls verwerfen
    if (function_exists('admin_csrf_reset')) {
        admin_csrf_reset();
    }
}


/**
 * ✅ MINIMAL: schreibt in BEIDE Tabellen:
 * - login_attempts (username, ip, user_agent, success, created_at)
 * - login_audit (user_id, action, ip, user_agent, meta_json, created_at)
 *
 * Darf Login niemals beeinflussen → try/catch.
 */
function admin_log_login_attempt(string $username, bool $success, ?int $userId = null): void
{
    $username = trim($username);
    if ($username === '') return;

    // 1) login_attempts
    try {
        $pdo = admin_pdo();
        $st = $pdo->prepare("
            INSERT INTO login_attempts (username, ip, user_agent, success, created_at)
            VALUES (:u, :ip, :ua, :s, NOW())
        ");
        $st->execute([
            ':u'  => $username,
            ':ip' => admin_ip(),
            ':ua' => admin_user_agent(),
            ':s'  => $success ? 1 : 0,
        ]);
    } catch (\Throwable) {
        // niemals blockieren
    }

    // 2) login_audit
    try {
        $pdo = admin_pdo();
        $action = $success ? 'login_success' : 'login_failed';

        $st = $pdo->prepare("
            INSERT INTO login_audit (user_id, action, ip, user_agent, meta_json, created_at)
            VALUES (:uid, :a, :ip, :ua, NULL, NOW())
        ");
        $st->execute([
            ':uid' => ($userId !== null && $userId > 0) ? $userId : null,
            ':a'   => $action,
            ':ip'  => admin_ip(),
            ':ua'  => admin_user_agent(),
        ]);
    } catch (\Throwable) {
        // niemals blockieren
    }
}

/**
 * Brute-Force-Rate-Limit: true = gesperrt (>= 5 Fehlversuche in 15 Min).
 * Sperrt separat nach IP und nach Username (unabhängig von IP).
 * Fail-open bei DB-Fehler (return false).
 */
function admin_check_brute_force(string $ip, string $username = ''): bool
{
    static $cache = []; // request-scope cache

    $ip = trim($ip);
    $usernameNorm = mb_strtolower(trim($username));
    if ($ip === '' && $usernameNorm === '') return false;

    $cacheKey = $ip . '|' . $usernameNorm;
    if (array_key_exists($cacheKey, $cache)) {
        return (bool)$cache[$cacheKey];
    }

    try {
        $pdo  = admin_pdo();
        $ipCount = 0;
        if ($ip !== '') {
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM login_attempts
                WHERE ip = :ip
                  AND success = 0
                  AND created_at >= (NOW() - INTERVAL 15 MINUTE)
            ");
            $stmt->execute([':ip' => $ip]);
            $ipCount = (int)$stmt->fetchColumn();
        }

        $usernameCount = 0;
        if ($usernameNorm !== '') {
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM login_attempts
                WHERE LOWER(username) = :u
                  AND success = 0
                  AND created_at >= (NOW() - INTERVAL 15 MINUTE)
            ");
            $stmt->execute([':u' => $usernameNorm]);
            $usernameCount = (int)$stmt->fetchColumn();
        }

        $cache[$cacheKey] = ($ipCount >= 5 || $usernameCount >= 5);
        return $cache[$cacheKey];
    } catch (\Throwable) {
        $cache[$cacheKey] = false;
        return false;
    }
}

function admin_login(string $username, string $password): bool
{
    $pdo = admin_pdo();

    $username = trim($username);
    if ($username === '' || $password === '') return false;

    if (admin_check_brute_force(admin_ip(), $username)) {
        admin_log_login_attempt($username, false, null);
        try {
            $pdo->prepare("
                INSERT INTO login_audit (user_id, action, ip, user_agent, meta_json, created_at)
                VALUES (NULL, 'login_blocked', :ip, :ua, NULL, NOW())
            ")->execute([':ip' => admin_ip(), ':ua' => admin_user_agent()]);
        } catch (\Throwable) {}
        return false;
    }

    usleep(50_000);

    $stmt = $pdo->prepare("
        SELECT id, password_hash, enabled
        FROM users
        WHERE username = :u OR email = :u
        ORDER BY CASE WHEN username = :u THEN 0 ELSE 1 END, id ASC
        LIMIT 1
    ");
    $stmt->execute([':u' => $username]);
    $row = $stmt->fetch();
    if (!is_array($row)) {
        admin_log_login_attempt($username, false, null);
        return false;
    }

    $uid = (int)($row['id'] ?? 0);

    if ((int)($row['enabled'] ?? 0) !== 1) {
        admin_log_login_attempt($username, false, $uid > 0 ? $uid : null);
        return false;
    }

    $hash = (string)($row['password_hash'] ?? '');
    if ($hash === '') {
        admin_log_login_attempt($username, false, $uid > 0 ? $uid : null);
        return false;
    }

    if (!password_verify($password, $hash)) {
        admin_log_login_attempt($username, false, $uid > 0 ? $uid : null);
        return false;
    }

    $token = admin_generate_token();
    $tokenHash = admin_hash_token($token);
    $expires = date('Y-m-d H:i:s', time() + ADMIN_TOKEN_TTL_SECONDS);

    // Defensive insert: login_tokens Schema kann je nach Migration unterschiedlich sein.
    // Login darf niemals an fehlenden optionalen Spalten scheitern.
    $hasIp = admin_db_column_exists($pdo, 'login_tokens', 'ip');
    $hasUa = admin_db_column_exists($pdo, 'login_tokens', 'user_agent');

    if ($hasIp && $hasUa) {
        $stmt = $pdo->prepare("
            INSERT INTO login_tokens (token_hash, user_id, expires_at, ip, user_agent, created_at)
            VALUES (:th, :uid, :ex, :ip, :ua, NOW())
        ");
        $stmt->execute([
            ':th'  => $tokenHash,
            ':uid' => $uid,
            ':ex'  => $expires,
            ':ip'  => admin_ip(),
            ':ua'  => admin_user_agent(),
        ]);
    } else {
        // Legacy Schema (ohne ip/user_agent)
        $stmt = $pdo->prepare("
            INSERT INTO login_tokens (token_hash, user_id, expires_at, created_at)
            VALUES (:th, :uid, :ex, NOW())
        ");
        $stmt->execute([
            ':th'  => $tokenHash,
            ':uid' => $uid,
            ':ex'  => $expires,
        ]);
    }

    setcookie(ADMIN_COOKIE_NAME, $token, [
        'expires'  => time() + ADMIN_TOKEN_TTL_SECONDS,
        'path'     => '/',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    admin_log_login_attempt($username, true, $uid > 0 ? $uid : null);
    return true;
}
