<?php
declare(strict_types=1);

/**
 * Minimaler Env/Config Zugriff ohne Output.
 * - Erst getenv()
 * - Dann optional $GLOBALS['APP_CONFIG']
 *
 * Hinweis:
 * - Diese Datei darf von mehreren Entry-Points eingebunden werden.
 * - Deshalb: Funktionsdeklarationen sind gegen Doppel-Loads abgesichert.
 */

if (!function_exists('admin_env')) {
    function admin_env(string $key, mixed $default = null): mixed
    {
        $v = getenv($key);
        if ($v !== false) return $v;

        $cfg = $GLOBALS['APP_CONFIG'] ?? null;
        if (is_array($cfg) && array_key_exists($key, $cfg)) {
            return $cfg[$key];
        }

        return $default;
    }
}

if (!function_exists('admin_project_root')) {
    function admin_project_root(): string
    {
        $root = realpath(__DIR__ . '/..');
        return $root !== false ? $root : '';
    }
}
