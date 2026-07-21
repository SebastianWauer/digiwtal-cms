<?php

declare(strict_types=1);

namespace App\PageBuilder\Blocks;

final class VideoBlock extends AbstractBlockType
{
    public function type(): string { return 'video'; }
    public function label(): string { return 'Video'; }

    public function defaults(): array
    {
        return [
            'headline'   => '',
            'provider'   => 'youtube',
            'video_id'   => '',
            'video_url'  => '',
            'poster_url' => '',
            'caption'    => '',
        ];
    }

    public function fields(): array
    {
        return [
            'headline'   => ['type' => 'string', 'max' => 200,  'label' => 'Überschrift',         'control' => 'input'],
            'provider'   => ['type' => 'string', 'max' => 10,   'label' => 'Anbieter',             'control' => 'select',
                            'enum' => ['youtube', 'vimeo', 'self']],
            'video_id'   => ['type' => 'string', 'max' => 200,  'label' => 'Video-ID',             'control' => 'input',
                            'hint' => 'YouTube- oder Vimeo-ID (z.B. dQw4w9WgXcQ)'],
            'video_url'  => ['type' => 'string', 'max' => 2000, 'label' => 'Video-URL (self)',     'control' => 'input',
                            'hint' => 'Nur bei Anbieter "self" – https://...'],
            'poster_url' => ['type' => 'string', 'max' => 2000, 'label' => 'Vorschaubild (URL)',   'control' => 'input'],
            'caption'    => ['type' => 'string', 'max' => 300,  'label' => 'Bildunterschrift',     'control' => 'input'],
        ];
    }

    public function validate(array $data): array
    {
        $clean = parent::validate($data);

        // provider clamp auf enum
        if (!in_array($clean['provider'] ?? '', ['youtube', 'vimeo', 'self'], true)) {
            $clean['provider'] = 'youtube';
        }

        // Wenn provider != 'self': video_url = ''
        if ($clean['provider'] !== 'self') {
            $clean['video_url'] = '';
        }

        // Wenn provider == 'self': video_id = ''
        if ($clean['provider'] === 'self') {
            $clean['video_id'] = '';
        }

        // video_url und poster_url Validierung
        foreach (['video_url', 'poster_url'] as $urlField) {
            $url = trim((string)($clean[$urlField] ?? ''));
            if ($url !== '' && !preg_match('#^(https?://|/)#', $url)) {
                $url = '';
            }
            $clean[$urlField] = $url;
        }

        // video_id Validierung
        if (in_array($clean['provider'], ['youtube', 'vimeo'], true)) {
            $videoId = trim((string)($clean['video_id'] ?? ''));
            if (!preg_match('/^[A-Za-z0-9_-]{1,200}$/', $videoId)) {
                $videoId = '';
            }
            $clean['video_id'] = $videoId;
        }

        return $clean;
    }
}