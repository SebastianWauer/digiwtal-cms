<?php
declare(strict_types=1);

namespace App\Core;

final class Hooks
{
    /** @var array<string, array<int, array<int, callable>>> */
    private static array $actions = [];

    /** @var array<string, array<int, array<int, callable>>> */
    private static array $filters = [];

    public static function add_action(string $hook, callable $cb, int $priority = 10): void
    {
        self::$actions[$hook][$priority][] = $cb;
    }

    public static function do_action(string $hook, mixed ...$args): void
    {
        if (!isset(self::$actions[$hook])) {
            return;
        }

        $buckets = self::$actions[$hook];
        ksort($buckets);

        foreach ($buckets as $cbs) {
            foreach ($cbs as $cb) {
                $cb(...$args);
            }
        }
    }

    public static function add_filter(string $hook, callable $cb, int $priority = 10): void
    {
        self::$filters[$hook][$priority][] = $cb;
    }

    public static function apply_filters(string $hook, mixed $value, mixed ...$args): mixed
    {
        if (!isset(self::$filters[$hook])) {
            return $value;
        }

        $buckets = self::$filters[$hook];
        ksort($buckets);

        foreach ($buckets as $cbs) {
            foreach ($cbs as $cb) {
                $value = $cb($value, ...$args);
            }
        }

        return $value;
    }
}
