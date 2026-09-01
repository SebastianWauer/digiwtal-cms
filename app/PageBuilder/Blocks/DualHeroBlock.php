<?php
declare(strict_types=1);

namespace App\PageBuilder\Blocks;

final class DualHeroBlock extends AbstractBlockType
{
    public function type(): string { return 'dual_hero'; }
    public function label(): string { return 'Doppel-Hero'; }

    public function defaults(): array
    {
        $defaults = [];
        foreach (['left', 'right'] as $side) {
            $defaults += [
                $side . '_background_image_url' => '',
                $side . '_foreground_image_url' => '',
                $side . '_topline' => '',
                $side . '_headline' => '',
                $side . '_subtitle' => '',
                $side . '_button_text' => '',
                $side . '_button_url' => '',
                $side . '_button_secondary_text' => '',
                $side . '_button_secondary_url' => '',
            ];
        }
        return $defaults;
    }

    public function fields(): array
    {
        $fields = [];
        foreach (['left' => 'Hero 1', 'right' => 'Hero 2'] as $side => $label) {
            $fields += [
                $side . '_background_image_url' => [
                    'type' => 'string',
                    'max' => 2000,
                    'label' => $label . ' – Herobild',
                    'control' => 'input',
                ],
                $side . '_foreground_image_url' => [
                    'type' => 'string',
                    'max' => 2000,
                    'label' => $label . ' – Bild auf dem Herobild',
                    'control' => 'input',
                    'hint' => 'Zum Beispiel ein Logo, Produktbild oder freigestelltes Motiv.',
                ],
                $side . '_topline' => [
                    'type' => 'string',
                    'max' => 160,
                    'label' => $label . ' – Topline',
                    'control' => 'input',
                ],
                $side . '_headline' => [
                    'type' => 'string',
                    'max' => 200,
                    'label' => $label . ' – Überschrift',
                    'control' => 'input',
                ],
                $side . '_subtitle' => [
                    'type' => 'string',
                    'max' => 500,
                    'label' => $label . ' – Unterschrift',
                    'control' => 'textarea',
                    'rows' => 3,
                ],
                $side . '_button_text' => [
                    'type' => 'string',
                    'max' => 80,
                    'label' => $label . ' – Button 1 – Text',
                    'control' => 'input',
                ],
                $side . '_button_url' => [
                    'type' => 'string',
                    'max' => 2000,
                    'label' => $label . ' – Button 1 – Ziel',
                    'control' => 'input',
                ],
                $side . '_button_secondary_text' => [
                    'type' => 'string',
                    'max' => 80,
                    'label' => $label . ' – Button 2 – Text',
                    'control' => 'input',
                ],
                $side . '_button_secondary_url' => [
                    'type' => 'string',
                    'max' => 2000,
                    'label' => $label . ' – Button 2 – Ziel',
                    'control' => 'input',
                ],
            ];
        }
        return $fields;
    }
}
