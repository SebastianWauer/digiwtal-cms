<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/**
 * Admin User Preferences
 * Tabelle laut IST-Stand: admin_user_prefs (user_id, pref_key, pref_value)
 *
 * Wichtig (Systemhärtung / Migration-Isolation):
 * - KEIN db_migrate_if_needed() hier drin
 * - Prefs sind optional: wenn Tabelle nicht existiert -> Default nutzen
 */

function admin_get_pref(int $userId, string $key, string $default = ''): string
{
    $userId = (int)$userId;
    $key = trim($key);
    if ($userId <= 0 || $key === '') return $default;

    $pdo = db();

    // Prefs sind optional – wenn Tabelle fehlt, einfach Default zurück
    if (!db_table_exists($pdo, 'admin_user_prefs')) return $default;

    $stmt = $pdo->prepare("
        SELECT pref_value
        FROM admin_user_prefs
        WHERE user_id = :uid AND pref_key = :k
        LIMIT 1
    ");
    $stmt->execute([':uid' => $userId, ':k' => $key]);

    $val = $stmt->fetchColumn();
    return is_string($val) ? $val : $default;
}

function admin_set_pref(int $userId, string $key, string $value): void
{
    $userId = (int)$userId;
    $key = trim($key);
    if ($userId <= 0 || $key === '') return;

    $pdo = db();

    // Prefs sind optional – wenn Tabelle fehlt, keine Side-Effects
    if (!db_table_exists($pdo, 'admin_user_prefs')) return;

    // Upsert kompatibel (MySQL/MariaDB)
    $stmt = $pdo->prepare("
        INSERT INTO admin_user_prefs (user_id, pref_key, pref_value)
        VALUES (:uid, :k, :v)
        ON DUPLICATE KEY UPDATE pref_value = VALUES(pref_value)
    ");
    $stmt->execute([':uid' => $userId, ':k' => $key, ':v' => $value]);
}

/**
 * Dashboard/Layout erwartet diese Funktion.
 * Liefert stabil 'light' oder 'dark'.
 */
function admin_theme_for_user(int $userId): string
{
    $t = admin_get_pref($userId, 'theme', 'light');
    return ($t === 'dark') ? 'dark' : 'light';
}
