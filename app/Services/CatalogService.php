<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\MediaFolderRepositoryDb;
use App\Repositories\MediaRepositoryDb;
use PDO;

final class CatalogService
{
    public const MAX_PDF_BYTES = 100 * 1024 * 1024;
    public const CHUNK_SIZE = 2 * 1024 * 1024;
    public const MAX_PAGES = 600;
    private const MAX_PAGE_BYTES = 4 * 1024 * 1024;

    private MediaRepositoryDb $mediaRepo;
    private MediaFolderRepositoryDb $folderRepo;
    private MediaService $mediaService;

    public function __construct(private PDO $pdo)
    {
        $this->mediaRepo = new MediaRepositoryDb($pdo);
        $this->folderRepo = new MediaFolderRepositoryDb($pdo);
        $this->mediaService = new MediaService($pdo, $this->mediaRepo, $this->folderRepo);
    }

    public function startUpload(string $filename, int $sizeBytes, int $folderId, int $userId): array
    {
        $filename = $this->safeOriginalFilename($filename);
        if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'pdf') {
            throw new \RuntimeException('Bitte eine PDF-Datei auswählen.');
        }
        if ($sizeBytes <= 0 || $sizeBytes > self::MAX_PDF_BYTES) {
            throw new \RuntimeException('Die Katalog-PDF darf maximal 100 MB groß sein.');
        }

        $folderId = $folderId > 0 ? $folderId : 1;
        if (!$this->folderRepo->findById($folderId)) {
            $folderId = 1;
        }
        if (!$this->folderRepo->findById($folderId)) {
            throw new \RuntimeException('Der Medienordner wurde nicht gefunden.');
        }

        $this->cleanupStaleUploads();
        $token = bin2hex(random_bytes(24));
        $dir = $this->uploadDirectory($token);
        $this->ensureDirectory($dir);

        $chunkCount = (int)ceil($sizeBytes / self::CHUNK_SIZE);
        $this->writeJson($dir . '/upload.json', [
            'token' => $token,
            'filename' => $filename,
            'size_bytes' => $sizeBytes,
            'folder_id' => $folderId,
            'user_id' => $userId,
            'chunk_size' => self::CHUNK_SIZE,
            'chunk_count' => $chunkCount,
            'created_at' => gmdate('c'),
        ]);

        return [
            'token' => $token,
            'chunk_size' => self::CHUNK_SIZE,
            'chunk_count' => $chunkCount,
            'max_bytes' => self::MAX_PDF_BYTES,
        ];
    }

    public function storeChunk(string $token, int $index, array $file, int $userId): array
    {
        $meta = $this->uploadMetadata($token, $userId);
        $chunkCount = (int)($meta['chunk_count'] ?? 0);
        if ($index < 0 || $index >= $chunkCount) {
            throw new \RuntimeException('Ungültiger Upload-Abschnitt.');
        }

        $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        $tmp = (string)($file['tmp_name'] ?? '');
        $size = (int)($file['size'] ?? 0);
        if ($error !== UPLOAD_ERR_OK || $tmp === '' || !is_uploaded_file($tmp)) {
            throw new \RuntimeException('Der Upload-Abschnitt konnte nicht gelesen werden.');
        }

        $totalSize = (int)($meta['size_bytes'] ?? 0);
        $expectedSize = min(self::CHUNK_SIZE, $totalSize - ($index * self::CHUNK_SIZE));
        if ($expectedSize <= 0 || $size !== $expectedSize) {
            throw new \RuntimeException('Der Upload-Abschnitt hat eine unerwartete Größe.');
        }

        $target = $this->chunkPath($token, $index);
        if (!@move_uploaded_file($tmp, $target)) {
            throw new \RuntimeException('Der Upload-Abschnitt konnte nicht gespeichert werden.');
        }
        @chmod($target, 0640);

        return ['index' => $index, 'received_bytes' => $size];
    }

    public function finishUpload(string $token, int $userId): array
    {
        $meta = $this->uploadMetadata($token, $userId);
        $dir = $this->uploadDirectory($token);
        $assembled = $dir . '/catalog.pdf';
        $out = @fopen($assembled, 'wb');
        if (!is_resource($out)) {
            throw new \RuntimeException('Die Katalog-PDF konnte nicht zusammengesetzt werden.');
        }

        try {
            $chunkCount = (int)($meta['chunk_count'] ?? 0);
            for ($index = 0; $index < $chunkCount; $index++) {
                $chunkPath = $this->chunkPath($token, $index);
                if (!is_file($chunkPath)) {
                    throw new \RuntimeException('Es fehlen Upload-Abschnitte. Bitte den Upload erneut starten.');
                }
                $in = @fopen($chunkPath, 'rb');
                if (!is_resource($in)) {
                    throw new \RuntimeException('Ein Upload-Abschnitt konnte nicht gelesen werden.');
                }
                stream_copy_to_stream($in, $out);
                fclose($in);
            }
        } finally {
            fclose($out);
        }

        $actualSize = (int)@filesize($assembled);
        if ($actualSize !== (int)($meta['size_bytes'] ?? 0)) {
            throw new \RuntimeException('Die zusammengesetzte PDF ist unvollständig.');
        }
        $signature = (string)@file_get_contents($assembled, false, null, 0, 5);
        if ($signature !== '%PDF-') {
            throw new \RuntimeException('Die hochgeladene Datei ist keine gültige PDF.');
        }
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = $finfo ? (string)finfo_file($finfo, $assembled) : '';
            if ($finfo) finfo_close($finfo);
            if ($mime !== '' && !in_array($mime, ['application/pdf', 'application/x-pdf'], true)) {
                throw new \RuntimeException('Die hochgeladene Datei wurde nicht als PDF erkannt.');
            }
        }

        $mediaId = $this->mediaService->importPdfFromPath(
            $assembled,
            (string)($meta['filename'] ?? 'katalog.pdf'),
            (int)($meta['folder_id'] ?? 1),
            self::MAX_PDF_BYTES
        );

        $catalogDir = $this->catalogDirectory($mediaId);
        $this->ensureDirectory($catalogDir . '/pages');
        $this->writeJson($catalogDir . '/manifest.json', [
            'schema' => 1,
            'media_id' => $mediaId,
            'status' => 'uploaded',
            'page_count' => 0,
            'pages' => [],
            'version_token' => bin2hex(random_bytes(8)),
            'updated_at' => gmdate('c'),
        ]);

        $this->removeDirectory($dir);

        return [
            'media_id' => $mediaId,
            'pdf_url' => '/media/file?id=' . $mediaId . '&download=1',
            'size_bytes' => $actualSize,
        ];
    }

    public function beginPageUpload(int $mediaId, int $pageCount): array
    {
        $this->requireActivePdf($mediaId);
        if ($pageCount <= 0 || $pageCount > self::MAX_PAGES) {
            throw new \RuntimeException('Der Katalog darf maximal ' . self::MAX_PAGES . ' Seiten enthalten.');
        }

        $catalogDir = $this->catalogDirectory($mediaId);
        $pagesDir = $catalogDir . '/pages';
        $this->ensureDirectory($pagesDir);
        foreach (glob($pagesDir . '/page-*.webp') ?: [] as $oldPage) {
            if (is_file($oldPage)) @unlink($oldPage);
        }

        $manifest = [
            'schema' => 1,
            'media_id' => $mediaId,
            'status' => 'processing',
            'page_count' => $pageCount,
            'pages' => [],
            'version_token' => bin2hex(random_bytes(8)),
            'updated_at' => gmdate('c'),
        ];
        $this->writeManifest($mediaId, $manifest);
        return $manifest;
    }

    public function storePage(int $mediaId, int $pageNumber, array $file): array
    {
        $this->requireActivePdf($mediaId);
        $manifest = $this->readManifest($mediaId);
        $pageCount = (int)($manifest['page_count'] ?? 0);
        if ((string)($manifest['status'] ?? '') !== 'processing' || $pageNumber <= 0 || $pageNumber > $pageCount) {
            throw new \RuntimeException('Diese Katalogseite wird nicht erwartet.');
        }

        $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        $tmp = (string)($file['tmp_name'] ?? '');
        $size = (int)($file['size'] ?? 0);
        if ($error !== UPLOAD_ERR_OK || $tmp === '' || !is_uploaded_file($tmp)) {
            throw new \RuntimeException('Die Katalogseite konnte nicht gelesen werden.');
        }
        if ($size <= 0 || $size > self::MAX_PAGE_BYTES) {
            throw new \RuntimeException('Die erzeugte Katalogseite ist zu groß.');
        }

        $imageInfo = @getimagesize($tmp);
        $width = is_array($imageInfo) ? (int)($imageInfo[0] ?? 0) : 0;
        $height = is_array($imageInfo) ? (int)($imageInfo[1] ?? 0) : 0;
        $type = is_array($imageInfo) ? (int)($imageInfo[2] ?? 0) : 0;
        if ($type !== IMAGETYPE_WEBP || $width <= 0 || $height <= 0 || $width > 3200 || $height > 4800) {
            throw new \RuntimeException('Die Katalogseite ist kein gültiges WebP-Bild.');
        }

        $target = $this->pagePathUnchecked($mediaId, $pageNumber);
        if (!@move_uploaded_file($tmp, $target)) {
            throw new \RuntimeException('Die Katalogseite konnte nicht gespeichert werden.');
        }
        @chmod($target, 0644);

        $manifest['pages'][(string)$pageNumber] = [
            'width' => $width,
            'height' => $height,
            'size_bytes' => (int)@filesize($target),
        ];
        $manifest['updated_at'] = gmdate('c');
        $this->writeManifest($mediaId, $manifest);

        return [
            'page' => $pageNumber,
            'width' => $width,
            'height' => $height,
            'size_bytes' => (int)@filesize($target),
        ];
    }

    public function completePageUpload(int $mediaId): array
    {
        $this->requireActivePdf($mediaId);
        $manifest = $this->readManifest($mediaId);
        $pageCount = (int)($manifest['page_count'] ?? 0);
        if ($pageCount <= 0 || $pageCount > self::MAX_PAGES) {
            throw new \RuntimeException('Die Seitenanzahl des Katalogs ist ungültig.');
        }

        for ($page = 1; $page <= $pageCount; $page++) {
            if (!is_file($this->pagePathUnchecked($mediaId, $page))) {
                throw new \RuntimeException('Katalogseite ' . $page . ' fehlt.');
            }
        }

        $manifest['status'] = 'ready';
        $manifest['version_token'] = bin2hex(random_bytes(8));
        $manifest['updated_at'] = gmdate('c');
        $this->writeManifest($mediaId, $manifest);
        return $this->publicMetadata($mediaId) ?? $manifest;
    }

    public function publicMetadata(int $mediaId): ?array
    {
        try {
            $this->requireActivePdf($mediaId);
            $manifest = $this->readManifest($mediaId);
        } catch (\Throwable) {
            return null;
        }

        $pageCount = (int)($manifest['page_count'] ?? 0);
        $status = (string)($manifest['status'] ?? 'missing');
        $version = preg_replace('/[^a-f0-9]/i', '', (string)($manifest['version_token'] ?? '')) ?: '1';
        return [
            'media_id' => $mediaId,
            'status' => $status,
            'ready' => $status === 'ready' && $pageCount > 0,
            'page_count' => $pageCount,
            'uploaded_pages' => is_array($manifest['pages'] ?? null) ? count($manifest['pages']) : 0,
            'pdf_url' => '/media/file?id=' . $mediaId . '&download=1',
            'page_url_template' => '/media/catalog/page?id=' . $mediaId . '&page={page}&v=' . $version,
            'version' => $version,
            'updated_at' => (string)($manifest['updated_at'] ?? ''),
        ];
    }

    public function publicPagePath(int $mediaId, int $pageNumber): ?string
    {
        $metadata = $this->publicMetadata($mediaId);
        if (!$metadata || empty($metadata['ready'])) return null;
        if ($pageNumber <= 0 || $pageNumber > (int)$metadata['page_count']) return null;
        $path = $this->pagePathUnchecked($mediaId, $pageNumber);
        return is_file($path) ? $path : null;
    }

    public function deleteAssets(int $mediaId): void
    {
        if ($mediaId <= 0) return;
        $dir = $this->catalogDirectory($mediaId);
        if (is_dir($dir)) $this->removeDirectory($dir);
    }

    private function requireActivePdf(int $mediaId): array
    {
        $row = $mediaId > 0 ? $this->mediaRepo->findById($mediaId) : null;
        if (!is_array($row) || (int)($row['is_deleted'] ?? 0) === 1 || strtolower((string)($row['ext'] ?? '')) !== 'pdf') {
            throw new \RuntimeException('Die Katalog-PDF wurde nicht gefunden.');
        }
        return $row;
    }

    private function readManifest(int $mediaId): array
    {
        $path = $this->catalogDirectory($mediaId) . '/manifest.json';
        $raw = is_file($path) ? @file_get_contents($path) : false;
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($decoded)) {
            throw new \RuntimeException('Die Katalogverarbeitung wurde noch nicht gestartet.');
        }
        return $decoded;
    }

    private function writeManifest(int $mediaId, array $manifest): void
    {
        $dir = $this->catalogDirectory($mediaId);
        $this->ensureDirectory($dir . '/pages');
        $this->writeJson($dir . '/manifest.json', $manifest);
    }

    private function uploadMetadata(string $token, int $userId): array
    {
        if (preg_match('/^[a-f0-9]{48}$/', $token) !== 1) {
            throw new \RuntimeException('Ungültiger Upload-Schlüssel.');
        }
        $path = $this->uploadDirectory($token) . '/upload.json';
        $raw = is_file($path) ? @file_get_contents($path) : false;
        $meta = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($meta) || (int)($meta['user_id'] ?? -1) !== $userId) {
            throw new \RuntimeException('Der Katalog-Upload wurde nicht gefunden oder ist abgelaufen.');
        }
        return $meta;
    }

    private function safeOriginalFilename(string $filename): string
    {
        $filename = trim(str_replace(["\r", "\n", "\0"], '', basename($filename)));
        if ($filename === '') return 'katalog.pdf';
        return mb_strlen($filename) > 180 ? mb_substr($filename, -180) : $filename;
    }

    private function storageRoot(): string
    {
        return dirname(__DIR__, 2) . '/storage';
    }

    private function uploadsRoot(): string
    {
        return $this->storageRoot() . '/catalog-uploads';
    }

    private function catalogsRoot(): string
    {
        return $this->storageRoot() . '/catalogs';
    }

    private function uploadDirectory(string $token): string
    {
        return $this->uploadsRoot() . '/' . $token;
    }

    private function catalogDirectory(int $mediaId): string
    {
        return $this->catalogsRoot() . '/' . $mediaId;
    }

    private function chunkPath(string $token, int $index): string
    {
        return $this->uploadDirectory($token) . '/chunk-' . str_pad((string)$index, 4, '0', STR_PAD_LEFT) . '.part';
    }

    private function pagePathUnchecked(int $mediaId, int $pageNumber): string
    {
        return $this->catalogDirectory($mediaId) . '/pages/page-' . str_pad((string)$pageNumber, 4, '0', STR_PAD_LEFT) . '.webp';
    }

    private function ensureDirectory(string $dir): void
    {
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        if (!is_dir($dir) || !is_writable($dir)) {
            throw new \RuntimeException('Das Katalog-Verzeichnis ist nicht beschreibbar.');
        }
    }

    private function writeJson(string $path, array $data): void
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if (!is_string($json)) throw new \RuntimeException('Katalogdaten konnten nicht serialisiert werden.');
        $tmp = $path . '.tmp-' . bin2hex(random_bytes(4));
        if (@file_put_contents($tmp, $json, LOCK_EX) === false || !@rename($tmp, $path)) {
            @unlink($tmp);
            throw new \RuntimeException('Katalogdaten konnten nicht gespeichert werden.');
        }
        @chmod($path, 0644);
    }

    private function cleanupStaleUploads(): void
    {
        $root = $this->uploadsRoot();
        $this->ensureDirectory($root);
        $cutoff = time() - (48 * 3600);
        foreach (scandir($root) ?: [] as $name) {
            if (preg_match('/^[a-f0-9]{48}$/', $name) !== 1) continue;
            $dir = $root . '/' . $name;
            if (is_dir($dir) && (int)@filemtime($dir) < $cutoff) $this->removeDirectory($dir);
        }
    }

    private function removeDirectory(string $dir): void
    {
        $root = realpath($this->storageRoot());
        $resolved = realpath($dir);
        if ($root === false || $resolved === false || !str_starts_with($resolved, $root . DIRECTORY_SEPARATOR)) return;
        foreach (scandir($resolved) ?: [] as $name) {
            if ($name === '.' || $name === '..') continue;
            $path = $resolved . DIRECTORY_SEPARATOR . $name;
            if (is_dir($path)) $this->removeDirectory($path);
            elseif (is_file($path)) @unlink($path);
        }
        @rmdir($resolved);
    }
}
