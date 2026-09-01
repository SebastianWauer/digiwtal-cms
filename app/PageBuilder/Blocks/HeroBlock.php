<?php
declare(strict_types=1);

namespace App\PageBuilder\Blocks;

final class HeroBlock extends AbstractBlockType
{
    public function type(): string { return 'hero'; }
    public function label(): string { return 'Hero'; }

    public function defaults(): array
    {
        return [
            'topline' => '',
            'headline' => '',
            'subtitle' => '',
            'button_text' => '',
            'button_url' => '',
            'button_secondary_text' => '',
            'button_secondary_url' => '',
            'image_url' => '',
        ];
    }

    public function fields(): array
    {
        return [
            'topline' => ['type' => 'string', 'max' => 160, 'label' => 'Topline', 'control' => 'input'],
            'headline' => ['type' => 'string', 'max' => 200, 'label' => 'Überschrift', 'control' => 'input'],
            'subtitle' => ['type' => 'string', 'max' => 500, 'label' => 'Subline',  'control' => 'textarea', 'rows' => 3],
            'button_text' => ['type' => 'string', 'max' => 80, 'label' => 'Button 1 – Text', 'control' => 'input'],
            'button_url' => ['type' => 'string', 'max' => 2000, 'label' => 'Button 1 – Ziel', 'control' => 'input'],
            'button_secondary_text' => ['type' => 'string', 'max' => 80, 'label' => 'Button 2 – Text', 'control' => 'input'],
            'button_secondary_url' => ['type' => 'string', 'max' => 2000, 'label' => 'Button 2 – Ziel', 'control' => 'input'],
            'image_url' => ['type' => 'string', 'max' => 2000, 'label' => 'Bild (URL)', 'control' => 'input'],
        ];
    }
}
