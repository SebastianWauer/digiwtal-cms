<?php
declare(strict_types=1);

namespace App\PageBuilder\CustomerBlocks\SportVoice;

use App\PageBuilder\Blocks\AbstractBlockType;

final class TextBlock extends AbstractBlockType
{
    public function type(): string { return 'sport_voice_text'; }
    public function label(): string { return 'Sport Voice - Portraet'; }

    public function defaults(): array
    {
        $data = [
            'layout' => 'portrait', 'anchor' => '', 'topline' => '', 'section_number' => '',
            'title_accent' => '', 'image_alt' => '', 'button_text' => '', 'button_url' => '', 'point_count' => '0',
            'title'          => '',
            'subtitle'       => '',
            'intro'          => '',
            'image_url'      => '',
            'image_position' => 'right',
            'image_caption'  => '',
            'image_credit'   => '',
            'text'           => '',
        ];
        for ($i = 1; $i <= 6; $i++) $data += ["point_{$i}_title" => '', "point_{$i}_text" => ''];
        return $data;
    }

    public function fields(): array
    {
        $fields = [
            'layout' => ['type' => 'string', 'max' => 20, 'label' => 'Darstellung', 'control' => 'select', 'enum' => ['portrait']],
            'anchor' => ['type' => 'string', 'max' => 100, 'label' => 'Sprungmarke', 'control' => 'input'],
            'topline' => ['type' => 'string', 'max' => 200, 'label' => 'Topline', 'control' => 'input'],
            'section_number' => ['type' => 'string', 'max' => 10, 'label' => 'Abschnittsnummer', 'control' => 'input'],
            'title_accent' => ['type' => 'string', 'max' => 300, 'label' => 'Titel - abgesetzter Schluss', 'control' => 'textarea', 'rows' => 3],
            'image_alt' => ['type' => 'string', 'max' => 300, 'label' => 'Bild - Alternativtext', 'control' => 'input'],
            'button_text' => ['type' => 'string', 'max' => 100, 'label' => 'Linktext', 'control' => 'input'],
            'button_url' => ['type' => 'string', 'max' => 2000, 'label' => 'Linkziel', 'control' => 'page_link'],
            'point_count' => ['type' => 'string', 'max' => 1, 'label' => 'Anzahl Stichpunkte', 'control' => 'select', 'enum' => ['0','1','2','3','4','5','6']],
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
        for ($i = 1; $i <= 6; $i++) {
            $fields += [
                "point_{$i}_title" => ['type' => 'string', 'max' => 200, 'label' => "Stichpunkt {$i} - Titel", 'control' => 'input'],
                "point_{$i}_text" => ['type' => 'string', 'max' => 700, 'label' => "Stichpunkt {$i} - Text", 'control' => 'textarea', 'rows' => 2],
            ];
        }
        return $fields;
    }
}
