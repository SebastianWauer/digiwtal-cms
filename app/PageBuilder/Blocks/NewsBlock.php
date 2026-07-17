<?php
declare(strict_types=1);

namespace App\PageBuilder\Blocks;

final class NewsBlock extends AbstractBlockType
{
    public function type(): string { return 'news'; }
    public function label(): string { return 'News'; }

    public function defaults(): array
    {
        return [
            'headline' => 'Aktuelle News',
            'category_slugs' => '',
            'limit' => '6',
            'show_teaser' => '1',
        ];
    }

    public function fields(): array
    {
        return [
            'headline' => ['type' => 'string', 'max' => 200, 'label' => 'Titel', 'control' => 'input'],
            'category_slugs' => ['type' => 'string', 'max' => 500, 'label' => 'Kategorie-Slugs (CSV)', 'control' => 'input'],
            'limit' => ['type' => 'string', 'label' => 'Anzahl', 'control' => 'select', 'enum' => ['3','4','6','9','12','all']],
            'show_teaser' => ['type' => 'string', 'label' => 'Teaser anzeigen', 'control' => 'select', 'enum' => ['1','0']],
        ];
    }
}
