<?php
declare(strict_types=1);

namespace App\PageBuilder\CustomerBlocks\SportVoice;

use App\PageBuilder\Blocks\AbstractBlockType;

final class ReferencesBlock extends AbstractBlockType
{
    public function type(): string { return 'sport_voice_references'; }
    public function label(): string { return 'Sport Voice - Referenzen'; }

    public function defaults(): array
    {
        $data = [
            'anchor' => '', 'topline' => '', 'section_number' => '', 'headline' => '', 'intro' => '',
            'show_feature' => '0', 'feature_anchor' => '', 'feature_title' => '', 'feature_text' => '',
            'feature_image_url' => '', 'feature_image_alt' => '', 'feature_caption' => '', 'feature_topline' => '',
            'feature_detail_1_label' => '', 'feature_detail_1_text' => '',
            'feature_detail_2_label' => '', 'feature_detail_2_text' => '',
            'feature_link_text' => '', 'feature_link_url' => '',
            'show_filters' => '1', 'filter_label' => '', 'item_count' => '4', 'footnote' => '',
        ];
        for ($i = 1; $i <= 12; $i++) {
            foreach (['title','text','image_url','image_alt','category','meta','link_url','anchor'] as $part) $data["item_{$i}_{$part}"] = '';
        }
        return $data;
    }

    public function fields(): array
    {
        $fields = [];
        $labels = [
            'anchor' => 'Sprungmarke', 'topline' => 'Topline', 'section_number' => 'Abschnittsnummer',
            'headline' => 'Ueberschrift', 'intro' => 'Einleitung', 'show_feature' => 'Hauptreferenz anzeigen',
            'feature_anchor' => 'Hauptreferenz - Sprungmarke', 'feature_title' => 'Hauptreferenz - Titel',
            'feature_text' => 'Hauptreferenz - Beschreibung', 'feature_image_url' => 'Hauptreferenz - Bild',
            'feature_image_alt' => 'Hauptreferenz - Alternativtext', 'feature_caption' => 'Hauptreferenz - Bildbeschriftung',
            'feature_topline' => 'Hauptreferenz - Topline', 'feature_detail_1_label' => 'Hauptreferenz - Detail 1 Bezeichnung',
            'feature_detail_1_text' => 'Hauptreferenz - Detail 1 Inhalt', 'feature_detail_2_label' => 'Hauptreferenz - Detail 2 Bezeichnung',
            'feature_detail_2_text' => 'Hauptreferenz - Detail 2 Inhalt', 'feature_link_text' => 'Hauptreferenz - Linktext',
            'feature_link_url' => 'Hauptreferenz - Linkziel', 'show_filters' => 'Kategoriefilter anzeigen',
            'filter_label' => 'Beschriftung der Filter', 'item_count' => 'Anzahl Referenzen', 'footnote' => 'Abschlusstext',
        ];
        foreach ($labels as $key => $label) {
            $control = in_array($key, ['show_feature','show_filters'], true) ? 'checkbox'
                : (str_ends_with($key, '_url') && !str_ends_with($key, '_image_url') ? 'page_link'
                : (in_array($key, ['headline','intro','feature_title','feature_text','footnote'], true) ? 'textarea' : 'input'));
            $fields[$key] = ['type' => 'string', 'max' => $control === 'checkbox' ? 1 : ($control === 'textarea' ? 3000 : 2000), 'label' => $label, 'control' => $control];
            if ($control === 'textarea') $fields[$key]['rows'] = 3;
        }
        $fields['item_count'] = ['type' => 'string', 'max' => 2, 'label' => 'Anzahl Referenzen', 'control' => 'select', 'enum' => array_map('strval', range(1,12))];
        for ($i = 1; $i <= 12; $i++) {
            foreach (['title' => 'Titel', 'text' => 'Beschreibung', 'image_url' => 'Logo / Bild', 'image_alt' => 'Alternativtext', 'category' => 'Kategorie', 'meta' => 'Leistung / Zusatzzeile', 'link_url' => 'Linkziel', 'anchor' => 'Sprungmarke'] as $part => $label) {
                $fields["item_{$i}_{$part}"] = ['type' => 'string', 'max' => in_array($part, ['text','image_url','link_url'], true) ? 2000 : 300, 'label' => "Referenz {$i} - {$label}", 'control' => $part === 'link_url' ? 'page_link' : ($part === 'text' ? 'textarea' : 'input')];
            }
        }
        return $fields;
    }

    public function validate(array $data): array
    {
        $clean = parent::validate(array_replace($this->defaults(), $data));
        if (!in_array($clean['item_count'], $this->fields()['item_count']['enum'], true)) $clean['item_count'] = '4';
        foreach ($clean as $key => $value) {
            if ($key === 'type') continue;
            $value = is_scalar($value) ? (string)$value : '';
            if (in_array($key, ['show_feature','show_filters'], true)) $value = $value === '1' ? '1' : '0';
            if ($key === 'anchor' || str_ends_with($key, '_anchor')) $value = preg_replace('/[^a-zA-Z0-9_-]/', '', $value) ?? '';
            if (str_ends_with($key, '_url')) {
                $pattern = str_ends_with($key, '_image_url') ? '#^(https?://|/(?!/))#i' : '#^(https?://|/(?!/)|\#|mailto:|tel:)#i';
                if (preg_match('/[\x00-\x20]/', $value) || !preg_match($pattern, $value)) $value = '';
            }
            $clean[$key] = $value;
        }
        return $clean;
    }
}
