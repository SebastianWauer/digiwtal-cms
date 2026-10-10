<?php
declare(strict_types=1);

namespace App\PageBuilder\CustomerBlocks\SportVoice;

use App\PageBuilder\Blocks\AbstractBlockType;

final class LogoStripBlock extends AbstractBlockType
{
    public function type(): string { return 'sport_voice_logo_strip'; }
    public function label(): string { return 'Sport Voice - Logo-Leiste'; }

    public function defaults(): array
    {
        $data = ['caption' => '', 'item_count' => '5'];
        for ($i = 1; $i <= 8; $i++) {
            $data += ["item_{$i}_title" => '', "item_{$i}_image_url" => '', "item_{$i}_link_url" => '', "item_{$i}_width" => '160'];
        }
        return $data;
    }

    public function fields(): array
    {
        $fields = [
            'caption' => ['type' => 'string', 'max' => 200, 'label' => 'Beschriftung', 'control' => 'textarea', 'rows' => 2],
            'item_count' => ['type' => 'string', 'max' => 1, 'label' => 'Anzahl Logos', 'control' => 'select', 'enum' => ['1','2','3','4','5','6','7','8']],
        ];
        for ($i = 1; $i <= 8; $i++) {
            $fields += [
                "item_{$i}_title" => ['type' => 'string', 'max' => 200, 'label' => "Logo {$i} - Name / Alternativtext", 'control' => 'input'],
                "item_{$i}_image_url" => ['type' => 'string', 'max' => 2000, 'label' => "Logo {$i} - Bild", 'control' => 'input'],
                "item_{$i}_link_url" => ['type' => 'string', 'max' => 2000, 'label' => "Logo {$i} - Linkziel", 'control' => 'page_link'],
                "item_{$i}_width" => ['type' => 'string', 'max' => 3, 'label' => "Logo {$i} - Maximale Breite", 'control' => 'range', 'min' => 60, 'max' => 240, 'step' => 1],
            ];
        }
        return $fields;
    }

    public function validate(array $data): array
    {
        $clean = parent::validate(array_replace($this->defaults(), $data));
        if (!in_array($clean['item_count'], $this->fields()['item_count']['enum'], true)) $clean['item_count'] = '5';
        for ($i = 1; $i <= 8; $i++) {
            $clean["item_{$i}_width"] = (string)max(60, min(240, (int)$clean["item_{$i}_width"]));
            foreach (['image_url', 'link_url'] as $suffix) {
                $key = "item_{$i}_{$suffix}";
                $value = is_string($clean[$key]) ? $clean[$key] : '';
                $pattern = $suffix === 'image_url' ? '#^(https?://|/(?!/))#i' : '#^(https?://|/(?!/)|\#)#i';
                $clean[$key] = !preg_match('/[\x00-\x20]/', $value) && preg_match($pattern, $value) ? $value : '';
            }
        }
        return $clean;
    }
}
