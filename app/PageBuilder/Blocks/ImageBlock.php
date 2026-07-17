<?php
declare(strict_types=1);

namespace App\PageBuilder\Blocks;

final class ImageBlock extends AbstractBlockType
{
    public function type(): string { return 'image'; }
    public function label(): string { return 'Bild'; }

    public function defaults(): array
    {
        return [
            'url' => '',
            'alt' => '',
        ];
    }

    public function fields(): array
    {
        return [
            'url' => ['type' => 'string', 'max' => 2000, 'label' => 'URL', 'control' => 'input'],
            'alt' => ['type' => 'string', 'max' => 300,  'label' => 'Alt-Text', 'control' => 'input'],
        ];
    }
}
