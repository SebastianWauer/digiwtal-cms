<?php
declare(strict_types=1);

namespace App\PageBuilder\CustomerBlocks\SportVoice;

use App\PageBuilder\Blocks\AbstractBlockType;

final class HeroBlock extends AbstractBlockType
{
    public function type(): string { return 'sport_voice_hero'; }
    public function label(): string { return 'Sport Voice - Hero'; }

    public function defaults(): array
    {
        return [
            'layout' => 'cover',
            'anchor' => '',
            'headline_accent' => '',
            'image_alt' => '',
            'image_caption' => '',
            'meta_left' => '',
            'meta_right' => '',
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
            'layout' => ['type' => 'string', 'max' => 20, 'label' => 'Darstellung', 'control' => 'select', 'enum' => ['cover']],
            'anchor' => ['type' => 'string', 'max' => 100, 'label' => 'Sprungmarke', 'control' => 'input'],
            'headline_accent' => ['type' => 'string', 'max' => 200, 'label' => 'Ueberschrift - abgesetzter Schluss', 'control' => 'textarea', 'rows' => 2],
            'image_alt' => ['type' => 'string', 'max' => 300, 'label' => 'Bild - Alternativtext', 'control' => 'input'],
            'image_caption' => ['type' => 'string', 'max' => 200, 'label' => 'Bildbeschriftung', 'control' => 'input'],
            'meta_left' => ['type' => 'string', 'max' => 200, 'label' => 'Fusszeile links', 'control' => 'input'],
            'meta_right' => ['type' => 'string', 'max' => 200, 'label' => 'Fusszeile rechts', 'control' => 'input'],
            'topline' => ['type' => 'string', 'max' => 160, 'label' => 'Topline', 'control' => 'input'],
            'headline' => ['type' => 'string', 'max' => 200, 'label' => 'Überschrift', 'control' => 'textarea', 'rows' => 3],
            'subtitle' => ['type' => 'string', 'max' => 500, 'label' => 'Subline',  'control' => 'textarea', 'rows' => 3],
            'button_text' => ['type' => 'string', 'max' => 80, 'label' => 'Button 1 – Text', 'control' => 'input'],
            'button_url' => ['type' => 'string', 'max' => 2000, 'label' => 'Button 1 – Ziel', 'control' => 'input'],
            'button_secondary_text' => ['type' => 'string', 'max' => 80, 'label' => 'Button 2 – Text', 'control' => 'input'],
            'button_secondary_url' => ['type' => 'string', 'max' => 2000, 'label' => 'Button 2 – Ziel', 'control' => 'input'],
            'image_url' => ['type' => 'string', 'max' => 2000, 'label' => 'Bild (URL)', 'control' => 'input'],
        ];
    }
}
