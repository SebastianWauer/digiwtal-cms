<?php

declare(strict_types=1);

namespace App\PageBuilder\CustomerBlocks\SportVoice;

use App\PageBuilder\Blocks\AbstractBlockType;

final class GalleryBlock extends AbstractBlockType
{
    public function type(): string { return 'sport_voice_gallery'; }
    public function label(): string { return 'Sport Voice - Galerie'; }

    public function defaults(): array
    {
        return [
            'layout' => 'editorial',
            'headline'   => '',
            'cols'       => '3',
            'items_json' => '[]',
        ];
    }

    public function fields(): array
    {
        return [
            'layout' => ['type' => 'string', 'max' => 20, 'label' => 'Darstellung', 'control' => 'select', 'enum' => ['editorial']],
            'headline'   => ['type' => 'string', 'max' => 200,   'label' => 'Überschrift',   'control' => 'input'],
            'cols'       => ['type' => 'string', 'max' => 1,     'label' => 'Spalten',        'control' => 'select',
                            'enum' => ['2', '3', '4']],
            'items_json' => ['type' => 'string', 'max' => 20000, 'label' => 'Bilder (JSON)', 'control' => 'textarea', 'rows' => 8,
                            'hint' => 'Format: [{"url":"https://...","alt":"Beschreibung","caption":""}]'],
        ];
    }

    public function validate(array $data): array
    {
        $clean = parent::validate($data);

        // cols clamp auf enum
        if (!in_array($clean['cols'] ?? '', ['2', '3', '4'], true)) {
            $clean['cols'] = '3';
        }

        // items_json Validierung
        $raw     = (string)($clean['items_json'] ?? '[]');
        $decoded = json_decode($raw, true);

        if (!is_array($decoded)) {
            $decoded = [];
        }

        $items = [];
        foreach (array_slice($decoded, 0, 20) as $item) {
            if (!is_array($item)) {
                continue;
            }

            $url = trim((string)($item['url'] ?? ''));
            if ($url === '' || !preg_match('#^(https?://|/)#', $url)) {
                continue; // ungültige URL verwerfen
            }

            $alt = trim((string)($item['alt'] ?? ''));
            if (mb_strlen($alt) > 300) {
                $alt = mb_substr($alt, 0, 300);
            }

            $caption = trim((string)($item['caption'] ?? ''));
            if (mb_strlen($caption) > 300) {
                $caption = mb_substr($caption, 0, 300);
            }

            $captionSecondary = mb_substr(trim((string)($item['caption_secondary'] ?? '')), 0, 300);
            $entry = ['url' => $url, 'alt' => $alt, 'caption' => $caption];
            if ($captionSecondary !== '') $entry['caption_secondary'] = $captionSecondary;
            $items[] = $entry;
        }

        $clean['items_json'] = (string)json_encode($items, JSON_UNESCAPED_UNICODE);

        return $clean;
    }
}
