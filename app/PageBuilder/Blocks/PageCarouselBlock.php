<?php
declare(strict_types=1);

namespace App\PageBuilder\Blocks;

final class PageCarouselBlock extends AbstractBlockType
{
    private const MAX_ITEMS = 12;

    public function type(): string { return 'page_carousel'; }
    public function label(): string { return 'Seiten-Karussell'; }

    public function defaults(): array
    {
        return [
            'headline' => 'Leistung im Fokus',
            'items' => [],
        ];
    }

    public function fields(): array
    {
        return [
            'headline' => [
                'type' => 'string',
                'max' => 200,
                'label' => 'Überschrift',
                'control' => 'input',
            ],
            'items' => [
                'type' => 'array',
                'label' => 'Seiten',
                'control' => 'page_carousel_items',
            ],
        ];
    }

    public function validate(array $data): array
    {
        $clean = parent::validate($data);
        $rawItems = $data['items'] ?? [];
        if (!is_array($rawItems)) {
            $rawItems = [];
        }

        $items = [];
        foreach (array_slice($rawItems, 0, self::MAX_ITEMS) as $rawItem) {
            if (!is_array($rawItem)) {
                continue;
            }

            $pageId = max(0, (int)($rawItem['page_id'] ?? 0));
            $pageSlug = $this->normalizeInternalPath((string)($rawItem['page_slug'] ?? ''));
            $pageTitle = $this->limit((string)($rawItem['page_title'] ?? ''), 200);
            $imageUrl = trim((string)($rawItem['image_url'] ?? ''));
            $text = $this->limit((string)($rawItem['text'] ?? ''), 1000);

            if ($pageSlug === '' || $pageTitle === '') {
                continue;
            }
            if ($imageUrl !== '' && preg_match('#^(https?://|/)#i', $imageUrl) !== 1) {
                $imageUrl = '';
            }
            $imageUrl = $this->limit($imageUrl, 2000);

            $items[] = [
                'page_id' => $pageId,
                'page_slug' => $pageSlug,
                'page_title' => $pageTitle,
                'image_url' => $imageUrl,
                'text' => $text,
            ];
        }

        $clean['items'] = $items;
        return $clean;
    }

    private function normalizeInternalPath(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        $parts = parse_url($value);
        if ($parts === false || isset($parts['scheme']) || isset($parts['host'])) {
            return '';
        }
        $path = '/' . trim((string)($parts['path'] ?? ''), '/');
        return $path === '//' ? '/' : $path;
    }

    private function limit(string $value, int $max): string
    {
        $value = trim($value);
        return mb_strlen($value) > $max ? mb_substr($value, 0, $max) : $value;
    }
}
