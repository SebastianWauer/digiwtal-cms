<?php
declare(strict_types=1);

namespace App\PageBuilder\Blocks;

use App\PageBuilder\BlockRegistry;

final class ThreeColumnsLayoutBlock extends AbstractBlockType
{
    public function type(): string { return 'three_columns_layout'; }
    public function label(): string { return 'Mehrspalten-Layout'; }

    public function defaults(): array
    {
        return [
            'title' => '',
            'columns' => [
                ['id' => 'column-1', 'blocks' => []],
                ['id' => 'column-2', 'blocks' => []],
            ],
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
        $rawColumns = $data['columns'] ?? null;

        // Bestehende 3-Spalten-Bloecke ohne Inhaltsverlust uebernehmen.
        if (!is_array($rawColumns)) {
            $rawColumns = [
                ['id' => 'legacy-left', 'blocks' => $data['left_blocks'] ?? []],
                ['id' => 'legacy-center', 'blocks' => $data['center_blocks'] ?? []],
                ['id' => 'legacy-right', 'blocks' => $data['right_blocks'] ?? []],
            ];
        }

        $columns = [];
        foreach (array_slice($rawColumns, 0, 12) as $index => $column) {
            if (!is_array($column)) {
                continue;
            }
            $id = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($column['id'] ?? '')) ?: ('column-' . ($index + 1));
            $columns[] = [
                'id' => substr($id, 0, 80),
                'blocks' => $this->normalizeNestedBlocks($column['blocks'] ?? []),
            ];
        }
        if ($columns === []) {
            $columns[] = ['id' => 'column-1', 'blocks' => []];
        }

        $clean['columns'] = $columns;
        // Alte Frontends koennen die ersten drei Spalten weiterhin darstellen.
        $clean['left_blocks'] = $columns[0]['blocks'] ?? [];
        $clean['center_blocks'] = $columns[1]['blocks'] ?? [];
        $clean['right_blocks'] = $columns[2]['blocks'] ?? [];
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
