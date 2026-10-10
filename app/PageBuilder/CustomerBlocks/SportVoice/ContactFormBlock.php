<?php
declare(strict_types=1);

namespace App\PageBuilder\CustomerBlocks\SportVoice;

use App\PageBuilder\Blocks\AbstractBlockType;

final class ContactFormBlock extends AbstractBlockType
{
    public function type(): string { return 'sport_voice_contact_form'; }
    public function label(): string { return 'Sport Voice - Kontakt'; }

    public function defaults(): array
    {
        return [
            'layout' => 'split', 'delivery' => 'server', 'anchor' => '', 'topline' => '', 'section_number' => '',
            'headline_accent' => '', 'contact_email' => '', 'contact_phone' => '', 'social_text' => '', 'social_url' => '',
            'recipient_email' => '', 'form_title' => '', 'service_options' => '', 'show_project' => '0',
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
            'layout' => ['type' => 'string', 'max' => 20, 'label' => 'Darstellung', 'control' => 'select', 'enum' => ['split']],
            'delivery' => ['type' => 'string', 'max' => 20, 'label' => 'Versand', 'control' => 'select', 'enum' => ['server','mailto'], 'hint' => 'server = vorhandener CMS-Versand; mailto = E-Mail-Entwurf im Mailprogramm.'],
            'anchor' => ['type' => 'string', 'max' => 100, 'label' => 'Sprungmarke', 'control' => 'input'],
            'topline' => ['type' => 'string', 'max' => 200, 'label' => 'Topline', 'control' => 'input'],
            'section_number' => ['type' => 'string', 'max' => 10, 'label' => 'Abschnittsnummer', 'control' => 'input'],
            'headline_accent' => ['type' => 'string', 'max' => 300, 'label' => 'Ueberschrift - abgesetzter Schluss', 'control' => 'textarea', 'rows' => 3],
            'contact_email' => ['type' => 'string', 'max' => 200, 'label' => 'Kontaktadresse', 'control' => 'input'],
            'contact_phone' => ['type' => 'string', 'max' => 100, 'label' => 'Telefon', 'control' => 'input'],
            'social_text' => ['type' => 'string', 'max' => 100, 'label' => 'Weiterer Link - Text', 'control' => 'input'],
            'social_url' => ['type' => 'string', 'max' => 2000, 'label' => 'Weiterer Link - Ziel', 'control' => 'page_link'],
            'recipient_email' => ['type' => 'string', 'max' => 200, 'label' => 'Empfaenger (E-Mail-Entwurf)', 'control' => 'input'],
            'form_title' => ['type' => 'string', 'max' => 200, 'label' => 'Formulartitel', 'control' => 'input'],
            'service_options' => ['type' => 'string', 'max' => 2000, 'label' => 'Leistungen (eine pro Zeile)', 'control' => 'textarea', 'rows' => 5],
            'show_project' => ['type' => 'string', 'max' => 1, 'label' => 'Projekt und Zeitraum abfragen (E-Mail-Entwurf)', 'control' => 'checkbox'],
            'headline' => [
                'type' => 'string', 'max' => 200,
                'label' => 'Titel', 'control' => 'textarea', 'rows' => 3,
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
        $clean['layout'] = 'split';
        $clean['delivery'] = in_array($clean['delivery'] ?? '', ['server','mailto'], true) ? $clean['delivery'] : 'server';
        foreach (['contact_email','recipient_email'] as $key) {
            $email = is_string($clean[$key] ?? null) ? $clean[$key] : '';
            $clean[$key] = filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
        }
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
