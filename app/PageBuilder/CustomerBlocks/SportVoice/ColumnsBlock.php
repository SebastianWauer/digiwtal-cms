<?php
declare(strict_types=1);

namespace App\PageBuilder\CustomerBlocks\SportVoice;

use App\PageBuilder\Blocks\AbstractBlockType;

final class ColumnsBlock extends AbstractBlockType
{
    public function type(): string { return 'sport_voice_columns'; }
    public function label(): string { return 'Sport Voice - Einsaetze'; }

    public function defaults(): array
    {
        $defaults = [
            'layout' => 'editorial', 'anchor' => '', 'topline' => '', 'section_number' => '', 'intro' => '',
            'title'       => '',
            'col_count'   => '2',
            'col_1_title' => '',
            'col_1_image_url' => '',
            'col_1_text'  => '',
            'col_1_link_url' => '',
            'col_1_button_show' => '0',
            'col_1_button_text' => '',
            'col_2_title' => '',
            'col_2_image_url' => '',
            'col_2_text'  => '',
            'col_2_link_url' => '',
            'col_2_button_show' => '0',
            'col_2_button_text' => '',
            'col_3_title' => '',
            'col_3_image_url' => '',
            'col_3_text'  => '',
            'col_3_link_url' => '',
            'col_3_button_show' => '0',
            'col_3_button_text' => '',
            'col_4_title' => '',
            'col_4_image_url' => '',
            'col_4_text'  => '',
            'col_4_link_url' => '',
            'col_4_button_show' => '0',
            'col_4_button_text' => '',
            'col_5_title' => '',
            'col_5_image_url' => '',
            'col_5_text'  => '',
            'col_5_link_url' => '',
            'col_5_button_show' => '0',
            'col_5_button_text' => '',
        ];
        $defaults += ['col_6_title' => '', 'col_6_image_url' => '', 'col_6_text' => '', 'col_6_link_url' => '', 'col_6_button_show' => '0', 'col_6_button_text' => ''];
        for ($i = 1; $i <= 6; $i++) $defaults += ["col_{$i}_category" => '', "col_{$i}_meta" => '', "col_{$i}_note" => ''];
        return $defaults;
    }

    public function fields(): array
    {
        $fields = [
            'layout' => ['type' => 'string', 'max' => 20, 'label' => 'Darstellung', 'control' => 'select', 'enum' => ['editorial']],
            'anchor' => ['type' => 'string', 'max' => 100, 'label' => 'Sprungmarke', 'control' => 'input'],
            'topline' => ['type' => 'string', 'max' => 200, 'label' => 'Topline', 'control' => 'input'],
            'section_number' => ['type' => 'string', 'max' => 10, 'label' => 'Abschnittsnummer', 'control' => 'input'],
            'intro' => ['type' => 'string', 'max' => 700, 'label' => 'Einleitung', 'control' => 'textarea', 'rows' => 3],
            'title'       => ['type' => 'string', 'max' => 200,  'label' => 'Titel',           'control' => 'input'],
            'col_count'   => ['type' => 'string', 'max' => 1,    'label' => 'Kachelanzahl',    'control' => 'select', 'enum' => ['1', '2', '3', '4', '5', '6']],
            'col_1_title' => ['type' => 'string', 'max' => 200,  'label' => 'Kachel 1 - Titel', 'control' => 'input'],
            'col_1_image_url' => ['type' => 'string', 'max' => 1000, 'label' => 'Kachel 1 - Bild', 'control' => 'input'],
            'col_1_text'  => ['type' => 'string', 'max' => 1000, 'label' => 'Kachel 1 - Text',  'control' => 'textarea', 'rows' => 4],
            'col_1_link_url' => ['type' => 'string', 'max' => 2000, 'label' => 'Kachel 1 - Linkziel', 'control' => 'input', 'hint' => 'Optional: interner Pfad wie /kontakt oder vollständige https://-Adresse.'],
            'col_1_button_show' => ['type' => 'string', 'max' => 1, 'label' => 'Kachel 1 - Button anzeigen', 'control' => 'checkbox'],
            'col_1_button_text' => ['type' => 'string', 'max' => 100, 'label' => 'Kachel 1 - Button-Text', 'control' => 'input', 'hint' => 'Wird nur angezeigt, wenn "Button anzeigen" aktiviert ist. Der Haken wird beim Eintippen automatisch gesetzt, lässt sich aber manuell wieder entfernen. Der Button verlinkt auf das gleiche Linkziel wie die Kachel.'],
            'col_2_title' => ['type' => 'string', 'max' => 200,  'label' => 'Kachel 2 - Titel', 'control' => 'input'],
            'col_2_image_url' => ['type' => 'string', 'max' => 1000, 'label' => 'Kachel 2 - Bild', 'control' => 'input'],
            'col_2_text'  => ['type' => 'string', 'max' => 1000, 'label' => 'Kachel 2 - Text',  'control' => 'textarea', 'rows' => 4],
            'col_2_link_url' => ['type' => 'string', 'max' => 2000, 'label' => 'Kachel 2 - Linkziel', 'control' => 'input', 'hint' => 'Optional: interner Pfad wie /kontakt oder vollständige https://-Adresse.'],
            'col_2_button_show' => ['type' => 'string', 'max' => 1, 'label' => 'Kachel 2 - Button anzeigen', 'control' => 'checkbox'],
            'col_2_button_text' => ['type' => 'string', 'max' => 100, 'label' => 'Kachel 2 - Button-Text', 'control' => 'input', 'hint' => 'Wird nur angezeigt, wenn "Button anzeigen" aktiviert ist. Der Haken wird beim Eintippen automatisch gesetzt, lässt sich aber manuell wieder entfernen. Der Button verlinkt auf das gleiche Linkziel wie die Kachel.'],
            'col_3_title' => ['type' => 'string', 'max' => 200,  'label' => 'Kachel 3 - Titel', 'control' => 'input'],
            'col_3_image_url' => ['type' => 'string', 'max' => 1000, 'label' => 'Kachel 3 - Bild', 'control' => 'input'],
            'col_3_text'  => ['type' => 'string', 'max' => 1000, 'label' => 'Kachel 3 - Text',  'control' => 'textarea', 'rows' => 4],
            'col_3_link_url' => ['type' => 'string', 'max' => 2000, 'label' => 'Kachel 3 - Linkziel', 'control' => 'input', 'hint' => 'Optional: interner Pfad wie /kontakt oder vollständige https://-Adresse.'],
            'col_3_button_show' => ['type' => 'string', 'max' => 1, 'label' => 'Kachel 3 - Button anzeigen', 'control' => 'checkbox'],
            'col_3_button_text' => ['type' => 'string', 'max' => 100, 'label' => 'Kachel 3 - Button-Text', 'control' => 'input', 'hint' => 'Wird nur angezeigt, wenn "Button anzeigen" aktiviert ist. Der Haken wird beim Eintippen automatisch gesetzt, lässt sich aber manuell wieder entfernen. Der Button verlinkt auf das gleiche Linkziel wie die Kachel.'],
            'col_4_title' => ['type' => 'string', 'max' => 200,  'label' => 'Kachel 4 - Titel', 'control' => 'input'],
            'col_4_image_url' => ['type' => 'string', 'max' => 1000, 'label' => 'Kachel 4 - Bild', 'control' => 'input'],
            'col_4_text'  => ['type' => 'string', 'max' => 1000, 'label' => 'Kachel 4 - Text',  'control' => 'textarea', 'rows' => 4],
            'col_4_link_url' => ['type' => 'string', 'max' => 2000, 'label' => 'Kachel 4 - Linkziel', 'control' => 'input', 'hint' => 'Optional: interner Pfad wie /kontakt oder vollständige https://-Adresse.'],
            'col_4_button_show' => ['type' => 'string', 'max' => 1, 'label' => 'Kachel 4 - Button anzeigen', 'control' => 'checkbox'],
            'col_4_button_text' => ['type' => 'string', 'max' => 100, 'label' => 'Kachel 4 - Button-Text', 'control' => 'input', 'hint' => 'Wird nur angezeigt, wenn "Button anzeigen" aktiviert ist. Der Haken wird beim Eintippen automatisch gesetzt, lässt sich aber manuell wieder entfernen. Der Button verlinkt auf das gleiche Linkziel wie die Kachel.'],
            'col_5_title' => ['type' => 'string', 'max' => 200,  'label' => 'Kachel 5 - Titel', 'control' => 'input'],
            'col_5_image_url' => ['type' => 'string', 'max' => 1000, 'label' => 'Kachel 5 - Bild', 'control' => 'input'],
            'col_5_text'  => ['type' => 'string', 'max' => 1000, 'label' => 'Kachel 5 - Text',  'control' => 'textarea', 'rows' => 4],
            'col_5_link_url' => ['type' => 'string', 'max' => 2000, 'label' => 'Kachel 5 - Linkziel', 'control' => 'input', 'hint' => 'Optional: interner Pfad wie /kontakt oder vollständige https://-Adresse.'],
            'col_5_button_show' => ['type' => 'string', 'max' => 1, 'label' => 'Kachel 5 - Button anzeigen', 'control' => 'checkbox'],
            'col_5_button_text' => ['type' => 'string', 'max' => 100, 'label' => 'Kachel 5 - Button-Text', 'control' => 'input', 'hint' => 'Wird nur angezeigt, wenn "Button anzeigen" aktiviert ist. Der Haken wird beim Eintippen automatisch gesetzt, lässt sich aber manuell wieder entfernen. Der Button verlinkt auf das gleiche Linkziel wie die Kachel.'],
        ];
        foreach (['title','image_url','text','link_url','button_show','button_text'] as $part) {
            $fields['col_6_' . $part] = $fields['col_5_' . $part];
            $fields['col_6_' . $part]['label'] = str_replace('5', '6', $fields['col_6_' . $part]['label']);
        }
        for ($i = 1; $i <= 6; $i++) {
            $fields += [
                "col_{$i}_category" => ['type' => 'string', 'max' => 200, 'label' => "Kachel {$i} - Kategorie", 'control' => 'input'],
                "col_{$i}_meta" => ['type' => 'string', 'max' => 200, 'label' => "Kachel {$i} - Zusatzzeile", 'control' => 'input'],
                "col_{$i}_note" => ['type' => 'string', 'max' => 700, 'label' => "Kachel {$i} - Hinweis", 'control' => 'textarea', 'rows' => 3],
            ];
        }
        return $fields;
    }

    public function validate(array $data): array
    {
        $clean = parent::validate($data);

        if (!in_array($clean['col_count'] ?? '', ['1', '2', '3', '4', '5', '6'], true)) {
            $clean['col_count'] = '2';
        }

        $count = (int)$clean['col_count'];
        for ($i = 1; $i <= $count; $i++) {
            $key = "col_{$i}_link_url";
            $url = trim((string)($clean[$key] ?? ''));
            $isInternal = str_starts_with($url, '/') && !str_starts_with($url, '//');
            $isAnchor = str_starts_with($url, '#');
            $isExternal = preg_match('#^https?://#i', $url) === 1;
            $clean[$key] = ($url === '' || $isInternal || $isAnchor || $isExternal) ? $url : '';

            $showKey = "col_{$i}_button_show";
            $clean[$showKey] = ((string)($clean[$showKey] ?? '')) === '1' ? '1' : '0';
        }
        for ($i = max(1, $count + 1); $i <= 6; $i++) {
            $clean["col_{$i}_title"] = '';
            $clean["col_{$i}_image_url"] = '';
            $clean["col_{$i}_text"]  = '';
            $clean["col_{$i}_link_url"] = '';
            $clean["col_{$i}_button_show"] = '0';
            $clean["col_{$i}_button_text"] = '';
        }

        return $clean;
    }
}
