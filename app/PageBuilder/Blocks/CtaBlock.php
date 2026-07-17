<?php
declare(strict_types=1);

namespace App\PageBuilder\Blocks;

final class CtaBlock extends AbstractBlockType
{
    public function type(): string { return 'cta'; }
    public function label(): string { return 'CTA'; }

    public function defaults(): array
    {
        return [
            'headline'    => '',
            'text'        => '',
            'button_text' => '',
            'button_url'  => '',
            'style'       => 'primary',
        ];
    }

    public function fields(): array
    {
        return [
            'headline'    => ['type' => 'string', 'max' => 200,  'label' => 'Überschrift',  'control' => 'input'],
            'text'        => ['type' => 'string', 'max' => 1000, 'label' => 'Text',          'control' => 'textarea', 'rows' => 4],
            'button_text' => ['type' => 'string', 'max' => 80,   'label' => 'Button-Text',   'control' => 'input'],
            'button_url'  => ['type' => 'string', 'max' => 2000, 'label' => 'Button-URL',    'control' => 'input'],
            'style'       => ['type' => 'string', 'max' => 10,   'label' => 'Stil',          'control' => 'select', 'enum' => ['primary', 'secondary', 'light', 'dark']],
        ];
    }

    public function validate(array $data): array
    {
        $clean = parent::validate($data);

        // style nur aus Enum erlauben
        if (!in_array($clean['style'] ?? '', ['primary', 'secondary', 'light', 'dark'], true)) {
            $clean['style'] = 'primary';
        }

        // button_url: nur http/https oder relative Pfade (verhindert javascript: etc.)
        $url = trim((string)($clean['button_url'] ?? ''));
        if ($url !== '' && !preg_match('#^(https?://|/)#', $url)) {
            $url = '';
        }
        $clean['button_url'] = $url;

        return $clean;
    }
}
