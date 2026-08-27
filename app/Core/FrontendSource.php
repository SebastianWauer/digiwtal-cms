<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Resolves the independently deployed frontend used for CMS previews.
 *
 * The CMS no longer assumes that a Frontend directory exists beside its own
 * repository. Each installation explicitly supplies FRONTEND_SOURCE_DIR.
 */
final class FrontendSource
{
    public static function root(): ?string
    {
        $configured = trim(Env::get('FRONTEND_SOURCE_DIR', ''));
        if ($configured === '') {
            return null;
        }

        $resolved = realpath($configured);
        if ($resolved === false || !is_dir($resolved)) {
            return null;
        }

        return rtrim($resolved, '/\\');
    }

    public static function directory(string $relative): ?string
    {
        return self::resolve($relative, true);
    }

    public static function file(string $relative): ?string
    {
        return self::resolve($relative, false);
    }

    private static function resolve(string $relative, bool $directory): ?string
    {
        $root = self::root();
        $relative = str_replace('\\', '/', trim($relative));
        if ($root === null || $relative === '' || str_starts_with($relative, '/')) {
            return null;
        }

        foreach (explode('/', $relative) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return null;
            }
        }

        $candidate = $root . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $resolved = realpath($candidate);
        if ($resolved === false) {
            return null;
        }

        $rootPrefix = strtolower(str_replace('\\', '/', $root)) . '/';
        $resolvedNormalized = strtolower(str_replace('\\', '/', $resolved));
        if (!str_starts_with($resolvedNormalized . ($directory ? '/' : ''), $rootPrefix)) {
            return null;
        }

        if ($directory ? !is_dir($resolved) : !is_file($resolved)) {
            return null;
        }

        return $resolved;
    }
}
