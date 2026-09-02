<?php
declare(strict_types=1);

namespace App\PageBuilder\Blocks;

final class CatalogBlock extends AbstractBlockType
{
    public function type(): string { return 'catalog'; }
    public function label(): string { return 'Blätterkatalog'; }

    public function defaults(): array
    {
        return [
            'title' => 'Katalog',
            'subtitle' => '',
            'pdf_media_id' => '0',
            'pdf_url' => '',
            'page_count' => '0',
            'catalog_status' => '',
            'download_label' => 'PDF herunterladen',
            'back_label' => 'Startseite',
            'back_url' => '/',
        ];
    }

    public function fields(): array
    {
        return [
            'title' => [
                'type' => 'string',
                'max' => 160,
                'label' => 'Katalogtitel',
                'control' => 'input',
            ],
            'subtitle' => [
                'type' => 'string',
                'max' => 300,
                'label' => 'Unterzeile',
                'control' => 'textarea',
                'rows' => 2,
            ],
            'pdf_media_id' => [
                'type' => 'string',
                'max' => 20,
                'label' => 'PDF Media-ID',
                'control' => 'input',
            ],
            'pdf_url' => [
                'type' => 'string',
                'max' => 2000,
                'label' => 'PDF-Download',
                'control' => 'input',
            ],
            'page_count' => [
                'type' => 'string',
                'max' => 4,
                'label' => 'Seitenanzahl',
                'control' => 'input',
            ],
            'catalog_status' => [
                'type' => 'string',
                'max' => 30,
                'label' => 'Verarbeitungsstatus',
                'control' => 'input',
            ],
            'download_label' => [
                'type' => 'string',
                'max' => 80,
                'label' => 'Download-Button',
                'control' => 'input',
            ],
            'back_label' => [
                'type' => 'string',
                'max' => 80,
                'label' => 'Zurück-Link – Text',
                'control' => 'input',
            ],
            'back_url' => [
                'type' => 'string',
                'max' => 2000,
                'label' => 'Zurück-Link – Ziel',
                'control' => 'input',
            ],
        ];
    }

    public function validate(array $data): array
    {
        $clean = parent::validate($data);
        $mediaId = max(0, (int)($data['pdf_media_id'] ?? 0));
        $pageCount = max(0, min(600, (int)($data['page_count'] ?? 0)));
        $pdfUrl = trim((string)($data['pdf_url'] ?? ''));
        if ($pdfUrl !== '' && preg_match('#^(https?://|/)#i', $pdfUrl) !== 1) $pdfUrl = '';
        $backUrl = trim((string)($data['back_url'] ?? '/'));
        if ($backUrl === '' || preg_match('#^(https?://|/)#i', $backUrl) !== 1) $backUrl = '/';

        $clean['pdf_media_id'] = (string)$mediaId;
        $clean['page_count'] = (string)$pageCount;
        $clean['pdf_url'] = mb_substr($pdfUrl, 0, 2000);
        $clean['back_url'] = mb_substr($backUrl, 0, 2000);
        $clean['catalog_status'] = in_array((string)($data['catalog_status'] ?? ''), ['uploaded', 'processing', 'ready'], true)
            ? (string)$data['catalog_status']
            : '';
        return $clean;
    }
}
