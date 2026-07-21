<?php
declare(strict_types=1);

namespace App\PageBuilder\Blocks;

final class ImprintBlock extends AbstractBlockType
{
    public function type(): string { return 'imprint'; }
    public function label(): string { return 'Impressum'; }

    public function defaults(): array
    {
        return [
            'headline' => 'Impressum',
            'additional_info' => '',
        ];
    }

    public function fields(): array
    {
        return [
            'headline' => [
                'type' => 'string', 'max' => 120,
                'label' => 'Titel', 'control' => 'input',
            ],
            'additional_info' => [
                'type' => 'string', 'max' => 4000,
                'label' => 'Zusatzhinweis (optional)', 'control' => 'textarea', 'rows' => 5,
                'hint' => 'Stammdaten kommen aus Einstellungen.',
            ],
        ];
    }
}
