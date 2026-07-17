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
            'headline' => '',
            'subtitle' => '',
            'button_text' => '',
            'button_url' => '',
            'image_url' => '',
            'overlay_opacity' => '0',
            'height_vh' => '55',
        ];
    }

    public function fields(): array
    {
        return [
            'headline' => ['type' => 'string', 'max' => 200, 'label' => 'Headline', 'control' => 'input'],
            'subtitle' => ['type' => 'string', 'max' => 500, 'label' => 'Subline',  'control' => 'textarea', 'rows' => 3],
            'button_text' => ['type' => 'string', 'max' => 80, 'label' => 'Button-Text', 'control' => 'input'],
            'button_url'  => ['type' => 'string', 'max' => 2000, 'label' => 'Button-URL', 'control' => 'input'],
            'image_url'   => ['type' => 'string', 'max' => 2000, 'label' => 'Bild (URL)', 'control' => 'input'],
            'overlay_opacity' => [
                'type' => 'string',
                'max' => 3,
                'label' => 'Schleier-Stärke (0-100)',
                'control' => 'range',
                'min' => 0,
                'max' => 100,
                'step' => 1,
                'hint' => '0 = kein Schleier, 100 = sehr stark',
            ],
            'height_vh' => [
                'type' => 'string',
                'max' => 3,
                'label' => 'Hero-Höhe (vh)',
                'control' => 'range',
                'min' => 25,
                'max' => 100,
                'step' => 1,
                'hint' => '25 = flach, 100 = volle Bildschirmhöhe',
            ],
        ];
    }

    public function validate(array $data): array
    {
        $clean = parent::validate($data);
        $raw = trim((string)($clean['overlay_opacity'] ?? '0'));
        if ($raw === '' || !is_numeric($raw)) {
            $raw = '0';
        }
        $n = (int)round((float)$raw);
        if ($n < 0) $n = 0;
        if ($n > 100) $n = 100;
        $clean['overlay_opacity'] = (string)$n;

        $hRaw = trim((string)($clean['height_vh'] ?? '55'));
        if ($hRaw === '' || !is_numeric($hRaw)) {
            $hRaw = '55';
        }
        $h = (int)round((float)$hRaw);
        if ($h < 25) $h = 25;
        if ($h > 100) $h = 100;
        $clean['height_vh'] = (string)$h;
        return $clean;
    }
}
