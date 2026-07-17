<?php
declare(strict_types=1);

namespace App\PageBuilder\Blocks;

final class FaqBlock extends AbstractBlockType
{
    public function type(): string { return 'faq'; }
    public function label(): string { return 'FAQ'; }

    public function defaults(): array
    {
        return [
            'headline'   => '',
            'items_json' => '[]',
        ];
    }

    public function fields(): array
    {
        return [
            'headline'   => ['type' => 'string', 'max' => 200,   'label' => 'Überschrift',           'control' => 'input'],
            'items_json' => ['type' => 'string', 'max' => 10000, 'label' => 'FAQ-Einträge (JSON)',    'control' => 'textarea', 'rows' => 8,
                             'hint' => 'Format: [{"q":"Frage","a":"Antwort"}, ...]'],
        ];
    }

    public function validate(array $data): array
    {
        $clean = parent::validate($data);

        $raw     = (string)($clean['items_json'] ?? '[]');
        $decoded = json_decode($raw, true);

        if (!is_array($decoded)) {
            $decoded = [];
        }

        $items = [];
        foreach (array_slice($decoded, 0, 10) as $item) {
            if (!is_array($item)) {
                continue;
            }

            $q = trim((string)($item['q'] ?? ''));
            if ($q === '') {
                continue; // leere Fragen verwerfen
            }
            if (mb_strlen($q) > 200) {
                $q = mb_substr($q, 0, 200);
            }

            $a = trim((string)($item['a'] ?? ''));
            if (mb_strlen($a) > 1000) {
                $a = mb_substr($a, 0, 1000);
            }

            $items[] = ['q' => $q, 'a' => $a];
        }

        $clean['items_json'] = (string)json_encode($items, JSON_UNESCAPED_UNICODE);

        return $clean;
    }
}
