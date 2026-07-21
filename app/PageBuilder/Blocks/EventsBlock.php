<?php
declare(strict_types=1);

namespace App\PageBuilder\Blocks;

final class EventsBlock extends AbstractBlockType
{
    public function type(): string { return 'events'; }
    public function label(): string { return 'Events'; }

    public function defaults(): array
    {
        return [
            'headline' => 'Events & Termine',
            'category_slugs' => '',
            'limit' => 'all',
            'include_past' => '1',
        ];
    }

    public function fields(): array
    {
        return [
            'headline' => ['type' => 'string', 'max' => 200, 'label' => 'Titel', 'control' => 'input'],
            'category_slugs' => ['type' => 'string', 'max' => 500, 'label' => 'Kategorien', 'control' => 'input', 'hint' => 'Leer = alle Kategorien'],
            'limit' => ['type' => 'string', 'max' => 4, 'label' => 'Anzahl', 'control' => 'select', 'enum' => ['all', '6', '12', '24', '36', '50']],
            'include_past' => ['type' => 'string', 'max' => 1, 'label' => 'Vergangene anzeigen', 'control' => 'select', 'enum' => ['0', '1']],
        ];
    }

    public function validate(array $data): array
    {
        $clean = parent::validate($data);
        if (!in_array((string)($clean['limit'] ?? ''), ['all', '6', '12', '24', '36', '50'], true)) {
            $clean['limit'] = 'all';
        }
        if (!in_array((string)($clean['include_past'] ?? ''), ['0', '1'], true)) {
            $clean['include_past'] = '1';
        }
        $raw = trim((string)($clean['category_slugs'] ?? ''));
        if ($raw !== '') {
            $parts = array_values(array_filter(array_map(static function (string $v): string {
                $v = preg_replace('/[^a-z0-9-]+/i', '-', strtolower(trim($v))) ?? '';
                return trim($v, '-');
            }, explode(',', $raw)), static fn(string $v): bool => $v !== ''));
            $parts = array_values(array_unique($parts));
            $clean['category_slugs'] = implode(',', $parts);
        } else {
            $clean['category_slugs'] = '';
        }

        // Backward compatibility with older field name.
        unset($clean['category_slug']);
        return $clean;
    }
}
