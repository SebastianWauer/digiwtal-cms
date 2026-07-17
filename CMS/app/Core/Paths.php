<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Single Source of Truth für alle internen Pfade/URLs im CMS.
 * Keine "/admin/..." Hardcodings mehr irgendwo sonst.
 */
final class Paths
{
public const LOGIN = '/login';
public const LOGOUT = '/logout';
public const DASHBOARD = '/';
public const NAVIGATION = '/navigation';
public const THEME = '/theme';
public const PREFS = '/prefs';
public const COOKIE_PATH = '/';

    public static function publicPath(string $relative): string
    {
        // "/assets/css/..." etc.
        $relative = ltrim($relative, '/');
        return '/' . $relative;
    }

    public static function safeInternal(string $path, string $fallback = '/'): string
    {
        $path = trim($path);
        if ($path === '' || $path[0] !== '/') return $fallback;
        // Optional: keine Protokolle/Hosts zulassen
        if (str_starts_with($path, '//')) return $fallback;
        return $path;
    }
}
