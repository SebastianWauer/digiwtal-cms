<?php
declare(strict_types=1);

namespace App\PageBuilder;

final class BlockValidator
{
    public function __construct(private BlockRegistry $registry) {}

    public function validateJson(string $json): string
    {
        if (trim($json) === '') {
            return '{"blocks":[]}';
        }

        $decoded = json_decode($json, true);

        if (!is_array($decoded)) {
            return '{"blocks":[]}';
        }

        // Fallback: Array ohne 'blocks'-Key → Liste von Blöcken direkt
        if (!isset($decoded['blocks'])) {
            $decoded = ['blocks' => array_values($decoded)];
        }

        if (!is_array($decoded['blocks'])) {
            return '{"blocks":[]}';
        }

        $normalized = ['blocks' => []];

        foreach ($decoded['blocks'] as $block) {
            if (!is_array($block)) {
                continue;
            }

            // Altes Format: {"type":"text","data":{...}} → flach mergen
            if (isset($block['data']) && is_array($block['data'])) {
                $merged = array_merge($block, $block['data']);
                unset($merged['data']);
                $block = $merged;
            }

            $type = (string)($block['type'] ?? '');

            if ($type === '' || !BlockRegistry::has($type)) {
                continue;
            }

            $blockType = BlockRegistry::get($type);
            if ($blockType === null) {
                continue;
            }

            $normalized['blocks'][] = $blockType->validate($block);
        }

        return (string)json_encode($normalized, JSON_UNESCAPED_UNICODE);
    }
}
