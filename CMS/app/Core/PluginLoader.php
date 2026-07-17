<?php
declare(strict_types=1);

namespace App\Core;

final class PluginLoader
{
    public static function load(string $pluginsDir): void
    {
        if (!is_dir($pluginsDir)) {
            return;
        }

        $entries = scandir($pluginsDir);
        if ($entries === false) {
            return;
        }

        sort($entries); // alphabetisch, konsistent

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $dir    = $pluginsDir . '/' . $entry;
            $plugin = $dir . '/plugin.php';

            if (is_dir($dir) && is_file($plugin)) {
                require_once $plugin;
            }
        }
    }
}
