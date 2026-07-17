<?php
declare(strict_types=1);

namespace App\PageBuilder\Blocks;

final class TextBlock extends AbstractBlockType
{
    public function type(): string { return 'text'; }
    public function label(): string { return 'Text'; }

    public function defaults(): array
    {
        return [
            'title'          => '',
            'subtitle'       => '',
            'intro'          => '',
            'image_url'      => '',
            'image_size'     => 'm',
            'image_position' => 'right',
            'image_caption'  => '',
            'image_credit'   => '',
            'text'           => '',
        ];
    }

    public function fields(): array
    {
        return [
            'title' => [
                'type' => 'string', 'max' => 200,
                'label' => 'Titel', 'control' => 'input',
            ],
            'subtitle' => [
                'type' => 'string', 'max' => 500,
                'label' => 'Untertitel', 'control' => 'input',
            ],
            'intro' => [
                'type' => 'string', 'max' => 700,
                'label' => 'Einleitung', 'control' => 'input',
                'hint' => 'Wird im Frontend fett dargestellt.',
            ],
            'image_url' => [
                'type' => 'string', 'max' => 2000,
                'label' => 'Bild (URL)', 'control' => 'input',
                'hint' => 'Optional. Später: Media-Picker.',
            ],
            'image_size' => [
                'type' => 'string', 'max' => 10,
                'label' => 'Bildgröße', 'control' => 'select',
                'enum' => ['s','m','l','xl','full'],
            ],
            'image_position' => [
                'type' => 'string', 'max' => 10,
                'label' => 'Bildposition', 'control' => 'select',
                'enum' => ['left','right','top','bottom'],
            ],
            'image_caption' => [
                'type' => 'string', 'max' => 300,
                'label' => 'Bildunterschrift', 'control' => 'input',
            ],
            'image_credit' => [
                'type' => 'string', 'max' => 200,
                'label' => 'Bild von', 'control' => 'input',
            ],
            'text' => [
                'type' => 'string', 'max' => 20000,
                'label' => 'Text', 'control' => 'textarea', 'rows' => 10,
            ],
        ];
    }
}
