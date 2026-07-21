<?php
declare(strict_types=1);

namespace App\PageBuilder\Blocks;

use App\PageBuilder\BlockRegistry;

final class ThreeColumnsLayoutBlock extends AbstractBlockType
{
    public function type(): string { return 'three_columns_layout'; }
    public function label(): string { return '3-Spalten Layout'; }

    public function defaults(): array
    {
        return [
            'title' => '',
            'left_blocks' => [],
            'center_blocks' => [],
            'right_blocks' => [],
        ];
    }

    public function fields(): array
    {
        return [
            'title' => ['type' => 'string', 'max' => 200, 'label' => 'Titel', 'control' => 'input'],
        ];
    }

    public function validate(array $data): array
    {
        $clean = parent::validate($data);
        $clean['left_blocks'] = $this->normalizeNestedBlocks($data['left_blocks'] ?? []);
        $clean['center_blocks'] = $this->normalizeNestedBlocks($data['center_blocks'] ?? []);
        $clean['right_blocks'] = $this->normalizeNestedBlocks($data['right_blocks'] ?? []);
        return $clean;
    }

    /**
     * @param mixed $raw
     * @return array<int,array<string,mixed>>
     */
    private function normalizeNestedBlocks(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $block) {
            if (!is_array($block)) {
                continue;
            }

            if (isset($block['data']) && is_array($block['data'])) {
                $merged = array_merge($block, $block['data']);
                unset($merged['data']);
                $block = $merged;
            }

            $type = trim((string)($block['type'] ?? ''));
            if ($type === '' || $type === $this->type() || !BlockRegistry::has($type)) {
                continue;
            }

            $blockType = BlockRegistry::get($type);
            if ($blockType === null || !method_exists($blockType, 'validate')) {
                continue;
            }

            /** @var array<string,mixed> $validated */
            $validated = $blockType->validate($block);
            $out[] = $validated;
        }

        return $out;
    }
}
