<?php
declare(strict_types=1);

namespace App\PageBuilder\Blocks;

final class ColumnsBlock extends AbstractBlockType
{
    public function type(): string { return 'columns'; }
    public function label(): string { return 'Kacheln'; }

    public function defaults(): array
    {
        return [
            'title'       => '',
            'col_count'   => '2',
            'col_1_title' => '',
            'col_1_image_url' => '',
            'col_1_text'  => '',
            'col_2_title' => '',
            'col_2_image_url' => '',
            'col_2_text'  => '',
            'col_3_title' => '',
            'col_3_image_url' => '',
            'col_3_text'  => '',
            'col_4_title' => '',
            'col_4_image_url' => '',
            'col_4_text'  => '',
            'col_5_title' => '',
            'col_5_image_url' => '',
            'col_5_text'  => '',
        ];
    }

    public function fields(): array
    {
        return [
            'title'       => ['type' => 'string', 'max' => 200,  'label' => 'Titel',           'control' => 'input'],
            'col_count'   => ['type' => 'string', 'max' => 1,    'label' => 'Kachelanzahl',    'control' => 'select', 'enum' => ['1', '2', '3', '4', '5']],
            'col_1_title' => ['type' => 'string', 'max' => 200,  'label' => 'Kachel 1 - Titel', 'control' => 'input'],
            'col_1_image_url' => ['type' => 'string', 'max' => 1000, 'label' => 'Kachel 1 - Bild', 'control' => 'input'],
            'col_1_text'  => ['type' => 'string', 'max' => 1000, 'label' => 'Kachel 1 - Text',  'control' => 'textarea', 'rows' => 4],
            'col_2_title' => ['type' => 'string', 'max' => 200,  'label' => 'Kachel 2 - Titel', 'control' => 'input'],
            'col_2_image_url' => ['type' => 'string', 'max' => 1000, 'label' => 'Kachel 2 - Bild', 'control' => 'input'],
            'col_2_text'  => ['type' => 'string', 'max' => 1000, 'label' => 'Kachel 2 - Text',  'control' => 'textarea', 'rows' => 4],
            'col_3_title' => ['type' => 'string', 'max' => 200,  'label' => 'Kachel 3 - Titel', 'control' => 'input'],
            'col_3_image_url' => ['type' => 'string', 'max' => 1000, 'label' => 'Kachel 3 - Bild', 'control' => 'input'],
            'col_3_text'  => ['type' => 'string', 'max' => 1000, 'label' => 'Kachel 3 - Text',  'control' => 'textarea', 'rows' => 4],
            'col_4_title' => ['type' => 'string', 'max' => 200,  'label' => 'Kachel 4 - Titel', 'control' => 'input'],
            'col_4_image_url' => ['type' => 'string', 'max' => 1000, 'label' => 'Kachel 4 - Bild', 'control' => 'input'],
            'col_4_text'  => ['type' => 'string', 'max' => 1000, 'label' => 'Kachel 4 - Text',  'control' => 'textarea', 'rows' => 4],
            'col_5_title' => ['type' => 'string', 'max' => 200,  'label' => 'Kachel 5 - Titel', 'control' => 'input'],
            'col_5_image_url' => ['type' => 'string', 'max' => 1000, 'label' => 'Kachel 5 - Bild', 'control' => 'input'],
            'col_5_text'  => ['type' => 'string', 'max' => 1000, 'label' => 'Kachel 5 - Text',  'control' => 'textarea', 'rows' => 4],
        ];
    }

    public function validate(array $data): array
    {
        $clean = parent::validate($data);

        if (!in_array($clean['col_count'] ?? '', ['1', '2', '3', '4', '5'], true)) {
            $clean['col_count'] = '2';
        }

        $count = (int)$clean['col_count'];
        for ($i = max(1, $count + 1); $i <= 5; $i++) {
            $clean["col_{$i}_title"] = '';
            $clean["col_{$i}_image_url"] = '';
            $clean["col_{$i}_text"]  = '';
        }

        return $clean;
    }
}
