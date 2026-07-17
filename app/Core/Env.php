<?php
declare(strict_types=1);

namespace App\Core;

final class Env
{
    /** @var array<string, string> */
    private static array $cache = [];
    private static bool  $loaded = false;

    public static function load(string $rootDir): void
    {
        if (self::$loaded) {
            return;
        }
        self::$loaded = true;

        $file = $rootDir . '/.env';
        if (!is_file($file)) {
            return;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }

            $eqPos = strpos($line, '=');
            if ($eqPos === false) {
                continue;
            }

            $key   = trim(substr($line, 0, $eqPos));
            $value = trim(substr($line, $eqPos + 1));

            // Entferne optionale Quotes ("..." oder '...')
            if (strlen($value) >= 2) {
                $first = $value[0];
                $last  = $value[strlen($value) - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            self::$cache[$key] = $value;
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $val = getenv($key);
        if ($val !== false) {
            return $val;
        }

        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }

        return $default;
    }
}
