<?php
declare(strict_types=1);

namespace App\PageBuilder;

use App\Core\Env;

final class BlockAccess
{
    public static function allows(string $type): bool
    {
        $catalog = self::catalog();
        if (!isset($catalog[$type])) return false;
        $owner = (string)($catalog[$type]['customer_key'] ?? '');
        if ($owner !== '' && $owner !== trim((string)Env::get('PAGEBUILDER_CUSTOMER_KEY', ''))) return false;

        $raw = trim((string)Env::get('PAGEBUILDER_ALLOWED_BLOCKS', ''));
        // Bestandsinstallationen behalten nur den unveraenderten Standardbestand.
        if ($raw === '' || $raw === 'null') return $owner === '';
        $allowed = json_decode($raw, true);
        return is_array($allowed) && array_is_list($allowed) && in_array($type, $allowed, true);
    }

    public static function catalog(): array
    {
        static $catalog = null;
        if ($catalog === null) {
            $decoded = json_decode((string)file_get_contents(dirname(__DIR__, 2) . '/config/pagebuilder-blocks.json'), true);
            $catalog = is_array($decoded) ? $decoded : [];
        }
        return $catalog;
    }

    public static function assertContentAllowed(array $blocks): void
    {
        $catalog = self::catalog();
        foreach ($blocks as $block) {
            if (!is_array($block)) continue;
            $data = is_array($block['data'] ?? null) ? array_merge($block, $block['data']) : $block;
            $type = (string)($data['type'] ?? '');
            if (isset($catalog[$type]) && !self::allows($type)) {
                throw new \DomainException('Der Inhalt enthaelt einen nicht freigegebenen Pagebuilder-Block. Bitte die Freigabe in der Verwaltung pruefen. Es wurde nichts gespeichert.');
            }
            foreach (['left_blocks', 'center_blocks', 'right_blocks'] as $key) {
                if (is_array($data[$key] ?? null)) self::assertContentAllowed($data[$key]);
            }
            foreach (is_array($data['columns'] ?? null) ? $data['columns'] : [] as $column) {
                if (is_array($column) && is_array($column['blocks'] ?? null)) self::assertContentAllowed($column['blocks']);
            }
        }
    }
}
