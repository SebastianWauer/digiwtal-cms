<?php
declare(strict_types=1);

namespace App\PageBuilder\Blocks;

/**
 * Leistungs-Showcase: eine Oberkategorie mit ihren Unterkategorien untereinander.
 *
 * Die Felder liegen flach (item_1_title, item_2_title, ...) statt in einem
 * items-Array. Grund: der Editor in app/Views/pages_edit.php baut seine
 * Eingabefelder generisch aus fields(); flache Felder brauchen deshalb keine
 * eigene Editor-Komponente. Felder auf *_image_url bekommen dort zusaetzlich
 * den Medien-Picker geschenkt. Vorbild ist ColumnsBlock.
 *
 * Der Block liefert die H1 der Seite selbst. Frontends muessen ihn deshalb
 * nicht nur im match-Arm, sondern auch in der $hasHero-Liste fuehren.
 */
final class ServiceShowcaseBlock extends AbstractBlockType
{
    private const MAX_ITEMS = 6;

    public function type(): string { return 'service_showcase'; }
    public function label(): string { return 'Leistungs-Showcase'; }

    public function defaults(): array
    {
        $defaults = [
            'kicker' => '',
            'headline' => '',
            'headline_muted' => '',
            'lead' => '',
            'stock_label' => 'Werkstoffe',
            'stock_items' => '',
            'item_count' => '3',
            'closing_kicker' => '',
            'closing_headline' => '',
            'closing_text' => '',
            'closing_button_text' => '',
            'closing_button_url' => '',
            'closing_secondary_text' => '',
            'closing_secondary_url' => '',
        ];

        for ($i = 1; $i <= self::MAX_ITEMS; $i++) {
            $defaults += [
                "item_{$i}_title" => '',
                "item_{$i}_lead" => '',
                "item_{$i}_text" => '',
                "item_{$i}_image_url" => '',
                "item_{$i}_link_url" => '',
                "item_{$i}_link_text" => '',
            ];
        }

        return $defaults;
    }

    public function fields(): array
    {
        $fields = [
            'kicker' => [
                'type' => 'string', 'max' => 120,
                'label' => 'Topline', 'control' => 'input',
                'hint' => 'Kleine Zeile ueber der Ueberschrift, z. B. "Leistung 01 / 06 · Gravuren".',
            ],
            'headline' => [
                'type' => 'string', 'max' => 200,
                'label' => 'Ueberschrift (H1 der Seite)', 'control' => 'input',
                'hint' => 'Dieser Block liefert die Seitenueberschrift. Der Seitentitel wird dann nicht zusaetzlich ausgegeben.',
            ],
            'headline_muted' => [
                'type' => 'string', 'max' => 120,
                'label' => 'Ueberschrift – abgesetzter Schluss', 'control' => 'input',
                'hint' => 'Optionaler, heller abgesetzter Teil direkt hinter der H1, z. B. "aller Art".',
            ],
            'lead' => [
                'type' => 'string', 'max' => 700,
                'label' => 'Einleitung', 'control' => 'textarea', 'rows' => 4,
            ],
            'stock_label' => [
                'type' => 'string', 'max' => 80,
                'label' => 'Werkstoffleiste – Beschriftung', 'control' => 'input',
            ],
            'stock_items' => [
                'type' => 'string', 'max' => 300,
                'label' => 'Werkstoffleiste – Eintraege', 'control' => 'input',
                'hint' => 'Mit Komma trennen, z. B. "Aluminium, Messing, Edelstahl". Leer lassen blendet die Leiste aus.',
            ],
            'item_count' => [
                'type' => 'string', 'max' => 1,
                'label' => 'Anzahl Unterkategorien', 'control' => 'select',
                'enum' => ['1', '2', '3', '4', '5', '6'],
            ],
        ];

        for ($i = 1; $i <= self::MAX_ITEMS; $i++) {
            $fields += [
                "item_{$i}_title" => [
                    'type' => 'string', 'max' => 200,
                    'label' => "Unterkategorie {$i} – Titel", 'control' => 'input',
                ],
                "item_{$i}_lead" => [
                    'type' => 'string', 'max' => 300,
                    'label' => "Unterkategorie {$i} – Kernsatz", 'control' => 'textarea', 'rows' => 2,
                    'hint' => 'Ein Satz, wird groesser und dunkler dargestellt als der Fliesstext.',
                ],
                "item_{$i}_text" => [
                    'type' => 'string', 'max' => 1200,
                    'label' => "Unterkategorie {$i} – Text", 'control' => 'textarea', 'rows' => 4,
                ],
                "item_{$i}_image_url" => [
                    'type' => 'string', 'max' => 2000,
                    'label' => "Unterkategorie {$i} – Bild", 'control' => 'input',
                    'hint' => 'Hochformat wirkt am besten, Richtwert 520 × 620.',
                ],
                "item_{$i}_link_url" => [
                    'type' => 'string', 'max' => 2000,
                    'label' => "Unterkategorie {$i} – Linkziel", 'control' => 'page_link',
                    'hint' => 'Seite auswaehlen oder manuell einen internen Pfad wie /gravuren/firmenschilder bzw. eine vollstaendige https://-Adresse eingeben.',
                ],
                "item_{$i}_link_text" => [
                    'type' => 'string', 'max' => 80,
                    'label' => "Unterkategorie {$i} – Linktext", 'control' => 'input',
                    'hint' => 'Ohne Text und Ziel wird kein Link ausgegeben.',
                ],
            ];
        }

        $fields += [
            'closing_kicker' => [
                'type' => 'string', 'max' => 120,
                'label' => 'Abschluss – Topline', 'control' => 'input',
            ],
            'closing_headline' => [
                'type' => 'string', 'max' => 200,
                'label' => 'Abschluss – Ueberschrift', 'control' => 'input',
                'hint' => 'Leer lassen blendet das ganze Abschlussband aus.',
            ],
            'closing_text' => [
                'type' => 'string', 'max' => 700,
                'label' => 'Abschluss – Text', 'control' => 'textarea', 'rows' => 3,
            ],
            'closing_button_text' => [
                'type' => 'string', 'max' => 80,
                'label' => 'Abschluss – Button-Text', 'control' => 'input',
            ],
            'closing_button_url' => [
                'type' => 'string', 'max' => 2000,
                'label' => 'Abschluss – Button-Ziel', 'control' => 'input',
            ],
            'closing_secondary_text' => [
                'type' => 'string', 'max' => 80,
                'label' => 'Abschluss – Zweitlink Text', 'control' => 'input',
                'hint' => 'Zum Beispiel eine Telefonnummer.',
            ],
            'closing_secondary_url' => [
                'type' => 'string', 'max' => 2000,
                'label' => 'Abschluss – Zweitlink Ziel', 'control' => 'input',
                'hint' => 'Interner Pfad, https://-Adresse, tel: oder mailto:.',
            ],
        ];

        return $fields;
    }

    public function validate(array $data): array
    {
        $clean = parent::validate($data);

        if (!in_array($clean['item_count'] ?? '', ['1', '2', '3', '4', '5', '6'], true)) {
            $clean['item_count'] = '3';
        }
        $count = (int)$clean['item_count'];

        for ($i = 1; $i <= $count; $i++) {
            $urlKey = "item_{$i}_link_url";
            $clean[$urlKey] = $this->safeLinkTarget((string)($clean[$urlKey] ?? ''), false);

            $imgKey = "item_{$i}_image_url";
            $img = trim((string)($clean[$imgKey] ?? ''));
            if (str_starts_with($img, '//') || ($img !== '' && preg_match('#^(https?://|/)#i', $img) !== 1)) {
                $img = '';
            }
            $clean[$imgKey] = $img;
        }

        // Nicht genutzte Unterkategorien leeren, damit nichts Verwaistes im JSON stehen bleibt.
        for ($i = max(1, $count + 1); $i <= self::MAX_ITEMS; $i++) {
            foreach (['title', 'lead', 'text', 'image_url', 'link_url', 'link_text'] as $part) {
                $clean["item_{$i}_{$part}"] = '';
            }
        }

        $clean['closing_button_url'] = $this->safeLinkTarget((string)($clean['closing_button_url'] ?? ''), false);
        $clean['closing_secondary_url'] = $this->safeLinkTarget((string)($clean['closing_secondary_url'] ?? ''), true);

        return $clean;
    }

    /**
     * Laesst interne Pfade, Anker und http/https durch; alles andere
     * (javascript:, data: ...) faellt auf einen leeren String zurueck.
     * Mit $allowContact zusaetzlich tel: und mailto: fuer den Zweitlink.
     */
    private function safeLinkTarget(string $url, bool $allowContact): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (str_starts_with($url, '//')) {
            return '';
        }
        if (str_starts_with($url, '/') || str_starts_with($url, '#')) {
            return $url;
        }
        if (preg_match('#^https?://#i', $url) === 1) {
            return $url;
        }
        if ($allowContact && preg_match('#^(tel:|mailto:)#i', $url) === 1) {
            return $url;
        }
        return '';
    }
}
