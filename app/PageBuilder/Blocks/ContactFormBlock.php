<?php
declare(strict_types=1);

namespace App\PageBuilder\Blocks;

final class ContactFormBlock extends AbstractBlockType
{
    public function type(): string { return 'contact_form'; }
    public function label(): string { return 'Kontaktformular'; }

    public function defaults(): array
    {
        return [
            'headline' => 'Kontakt',
            'intro' => 'Schreiben Sie mir direkt eine Nachricht.',
            'submit_label' => 'Nachricht senden',
            'form_id' => '',
            'show_name' => '1',
            'show_email' => '1',
            'show_phone' => '1',
            'show_message' => '1',
        ];
    }

    public function fields(): array
    {
        return [
            'headline' => [
                'type' => 'string', 'max' => 200,
                'label' => 'Titel', 'control' => 'input',
            ],
            'intro' => [
                'type' => 'string', 'max' => 800,
                'label' => 'Einleitung', 'control' => 'textarea', 'rows' => 3,
            ],
            'submit_label' => [
                'type' => 'string', 'max' => 60,
                'label' => 'Button-Text', 'control' => 'input',
            ],
            'form_id' => [
                'type' => 'string', 'max' => 64,
                'label' => 'Form-ID (optional)', 'control' => 'input',
                'hint' => 'Nur a-z, 0-9, _ und -. Leer lassen fuer automatische ID.',
            ],
            'show_name' => [
                'type' => 'string',
                'label' => 'Feld Name anzeigen',
                'control' => 'select',
                'enum' => ['1', '0'],
            ],
            'show_email' => [
                'type' => 'string',
                'label' => 'Feld E-Mail anzeigen',
                'control' => 'select',
                'enum' => ['1', '0'],
            ],
            'show_phone' => [
                'type' => 'string',
                'label' => 'Feld Telefon anzeigen',
                'control' => 'select',
                'enum' => ['1', '0'],
            ],
            'show_message' => [
                'type' => 'string',
                'label' => 'Feld Nachricht anzeigen',
                'control' => 'select',
                'enum' => ['1', '0'],
            ],
        ];
    }

    public function validate(array $data): array
    {
        $clean = parent::validate($data);
        $formId = trim((string)($clean['form_id'] ?? ''));
        if ($formId !== '' && preg_match('/^[a-z0-9_-]{1,64}$/i', $formId) !== 1) {
            $formId = '';
        }
        $clean['form_id'] = $formId;

        foreach (['show_name', 'show_email', 'show_phone', 'show_message'] as $k) {
            $clean[$k] = ((string)($clean[$k] ?? '1') === '0') ? '0' : '1';
        }
        if ($clean['show_name'] === '0' && $clean['show_email'] === '0' && $clean['show_phone'] === '0' && $clean['show_message'] === '0') {
            $clean['show_message'] = '1';
        }

        return $clean;
    }
}