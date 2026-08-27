<?php
declare(strict_types=1);

namespace App\PageBuilder\Blocks;

final class SocialAccountBlock extends AbstractBlockType
{
    public const PLATFORMS = ['facebook', 'instagram', 'youtube', 'tiktok', 'x'];
    public const STYLES = ['embed', 'card'];

    public function type(): string { return 'social_account'; }
    public function label(): string { return 'Social-Media-Account'; }

    public function defaults(): array
    {
        return [
            'platform' => 'facebook',
            'label'    => '',
            'style'    => 'embed',
        ];
    }

    public function fields(): array
    {
        return [
            'platform' => [
                'type' => 'string', 'max' => 20,
                'label' => 'Plattform', 'control' => 'select', 'enum' => self::PLATFORMS,
                'hint' => 'Das Profil wird aus den Einstellungen (Social Media) übernommen. '
                    . 'Live-Embed: Facebook & X direkt, YouTube nur mit Kanal-URL /channel/UC..., '
                    . 'Instagram nur wenn unter Einstellungen mit Instagram verbunden. TikTok zeigt immer eine Profil-Karte mit Link.',
            ],
            'label' => [
                'type' => 'string', 'max' => 80,
                'label' => 'Beschriftung (optional)', 'control' => 'input',
                'hint' => 'Standard: Plattformname',
            ],
            'style' => [
                'type' => 'string', 'max' => 20,
                'label' => 'Darstellung', 'control' => 'select', 'enum' => self::STYLES,
                'hint' => 'embed = Live-Profil (falls verfügbar, sonst automatisch Profil-Karte), card = immer Profil-Karte mit Link.',
            ],
        ];
    }

    public function validate(array $data): array
    {
        $clean = parent::validate($data);

        if (!in_array($clean['platform'] ?? '', self::PLATFORMS, true)) {
            $clean['platform'] = 'facebook';
        }

        if (!in_array($clean['style'] ?? '', self::STYLES, true)) {
            $clean['style'] = 'embed';
        }

        return $clean;
    }
}
