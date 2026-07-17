<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\MediaRepositoryDb;
use App\Repositories\MediaFolderRepositoryDb;
use PDO;

final class MediaService
{
    private const ALLOWED_EXT = ['jpg', 'jpeg', 'png', 'webp', 'svg', 'pdf'];

    public function __construct(
        private PDO $pdo,
        private MediaRepositoryDb $media,
        private MediaFolderRepositoryDb $folders,
    ) {}

    public function mediaStorageDir(): string
    {
        // app/Services -> app -> project root -> storage/media
        $dir = \dirname(__DIR__, 2) . '/storage/media';
        return $dir;
    }

    private function ensureStorageDir(): void
    {
        $dir = $this->mediaStorageDir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        if (!is_dir($dir) || !is_writable($dir)) {
            throw new \RuntimeException('Storage-Verzeichnis nicht beschreibbar: ' . $dir);
        }
    }

    /**
     * @return array{ext:string,mime:string,size:int,tmp:string,original:string}
     */
    private function normalizeUploadedFile(array $f): array
    {
        $tmp = (string)($f['tmp_name'] ?? '');
        $err = (int)($f['error'] ?? UPLOAD_ERR_NO_FILE);
        $size = (int)($f['size'] ?? 0);
        $orig = trim((string)($f['name'] ?? ''));

        if ($err !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Upload fehlgeschlagen (error=' . $err . ').');
        }
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new \RuntimeException('Upload-Datei ungültig.');
        }
        if ($orig === '') {
            $orig = 'upload';
        }

        // Extension aus Originalname
        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION) ?: '');
        if ($ext === '') {
            throw new \RuntimeException('Dateiendung fehlt.');
        }
        if (!in_array($ext, self::ALLOWED_EXT, true)) {
            throw new \RuntimeException('Dateityp nicht erlaubt: ' . $ext);
        }

        // MIME serverseitig bestimmen
        $mime = $this->detectMime($tmp, $ext);

        // Size-Grenze
        $maxBytes = 25 * 1024 * 1024; // 25 MB
        if ($size <= 0 || $size > $maxBytes) {
            throw new \RuntimeException('Dateigröße ungültig (max 25 MB).');
        }

        return [
            'ext' => $ext,
            'mime' => $mime,
            'size' => $size,
            'tmp' => $tmp,
            'original' => $orig,
        ];
    }

    private function detectMime(string $tmpFile, string $ext): string
    {
        $mime = '';
        if (function_exists('finfo_open')) {
            $fi = finfo_open(FILEINFO_MIME_TYPE);
            if ($fi) {
                $m = finfo_file($fi, $tmpFile);
                if (is_string($m)) $mime = $m;
                finfo_close($fi);
            }
        }

        // SVG ist tricky: finfo liefert je nach System text/plain / image/svg+xml.
        if ($ext === 'svg') {
            $head = @file_get_contents($tmpFile, false, null, 0, 2048);
            $head = is_string($head) ? $head : '';
            if (stripos($head, '<svg') === false) {
                throw new \RuntimeException('SVG ungültig (kein <svg> gefunden).');
            }
            return 'image/svg+xml';
        }

        $allowed = [
            'jpg'  => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png'  => ['image/png'],
            'webp' => ['image/webp'],
            'pdf'  => ['application/pdf'],
        ];

        $ok = isset($allowed[$ext]) && in_array($mime, $allowed[$ext], true);
        if (!$ok) {
            throw new \RuntimeException('MIME nicht erlaubt: ' . ($mime ?: 'unknown'));
        }

        return $mime;
    }

    /**
     * Multiupload aus <input name="files[]" multiple> oder einzelner Datei.
     *
     * @param array<string,mixed> $filesGlobal $_FILES['files'] oder $_FILES['file']
     * @return array<int,int> neu angelegte media_ids
     */
    public function uploadFromFilesGlobal(array $filesGlobal, int $folderId): array
    {
        // Root ist ID=1
        if ($folderId <= 0) $folderId = 1;

        $folderId = $this->normalizeFolderId($folderId);

        // Existenz prüfen
        $folder = $this->folders->findById($folderId);
        if (!$folder) {
            $folderId = 1;
        }

        $this->ensureStorageDir();

        $items = $this->explodeFilesArray($filesGlobal);
        if (!$items) {
            throw new \RuntimeException('Keine Dateien ausgewählt.');
        }

        $created = [];
        $tempFilesToCleanup = [];

        $this->pdo->beginTransaction();
        try {
            foreach ($items as $f) {
                $nf = $this->normalizeUploadedFile($f);

                $tmpToStore = $nf['tmp'];
                if ($nf['ext'] === 'svg') {
                    $tmpToStore = $this->sanitizeSvgToTempFile($nf['tmp']);
                    $tempFilesToCleanup[] = $tmpToStore;
                }

                $displayBase = $this->sanitizeDisplayBaseName($nf['original']);
                $storagePlaceholder = 'pending.' . $nf['ext'];

                $id = $this->media->insertItem([
                    'folder_id'         => $folderId,
                    'original_filename' => $nf['original'],
                    'display_filename'  => $displayBase,
                    'storage_filename'  => $storagePlaceholder, // wird direkt nachher ersetzt
                    'ext'               => $nf['ext'],
                    'mime'              => $nf['mime'],
                    'size_bytes'        => $nf['size'],
                    'width'             => null,
                    'height'            => null,
                    'title'             => null,
                    'alt_text'          => null,
                    'description'       => null,
                    'focus_x'           => null,
                    'focus_y'           => null,
                    'usage_count'       => 0,
                ]);

                if ($id <= 0) {
                    throw new \RuntimeException('DB Insert fehlgeschlagen.');
                }

                if (in_array($nf['ext'], ['jpg','jpeg','png','webp'], true)) {
                    $tmpToStore = $this->optimizeRasterImageToTempFile($tmpToStore, $nf['ext'], 1920);
                    $tempFilesToCleanup[] = $tmpToStore;
                }

                $dims = $this->detectDimensions($tmpToStore, $nf['ext']);
                $finalStorage = $id . '.' . $nf['ext'];

                $dest = $this->mediaStorageDir() . '/' . $finalStorage;

                if ($tmpToStore === $nf['tmp']) {
                    if (!@move_uploaded_file($nf['tmp'], $dest)) {
                        throw new \RuntimeException('Datei konnte nicht gespeichert werden.');
                    }
                } else {
                    if (!@copy($tmpToStore, $dest)) {
                        throw new \RuntimeException('Datei konnte nicht gespeichert werden.');
                    }
                    @unlink($tmpToStore);
                    $tempFilesToCleanup = array_values(array_filter(
                        $tempFilesToCleanup,
                        static fn (string $tmp): bool => $tmp !== $tmpToStore
                    ));
                }

                @chmod($dest, 0644);

                $finalSize = (int)@filesize($dest);
                if ($finalSize < 0) $finalSize = 0;

                if ($dims) {
                    $this->finalizeStorageFields($id, $finalStorage, $dims['w'], $dims['h'], $finalSize);
                } else {
                    $this->finalizeStorageFields($id, $finalStorage, null, null, $finalSize);
                }

                @chmod($dest, 0644);

                $created[] = $id;
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            foreach ($tempFilesToCleanup as $tmpPath) {
                if (is_string($tmpPath) && $tmpPath !== '') {
                    @unlink($tmpPath);
                }
            }
            throw $e;
        }

        return $created;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function explodeFilesArray(array $filesGlobal): array
    {
        if (!isset($filesGlobal['name'])) return [];

        $name = $filesGlobal['name'];

        // Single
        if (!is_array($name)) {
            return [[
                'name' => $filesGlobal['name'] ?? null,
                'type' => $filesGlobal['type'] ?? null,
                'tmp_name' => $filesGlobal['tmp_name'] ?? null,
                'error' => $filesGlobal['error'] ?? null,
                'size' => $filesGlobal['size'] ?? null,
            ]];
        }

        // Multi
        $out = [];
        $count = count($name);
        for ($i = 0; $i < $count; $i++) {
            $out[] = [
                'name' => $filesGlobal['name'][$i] ?? null,
                'type' => $filesGlobal['type'][$i] ?? null,
                'tmp_name' => $filesGlobal['tmp_name'][$i] ?? null,
                'error' => $filesGlobal['error'][$i] ?? null,
                'size' => $filesGlobal['size'][$i] ?? null,
            ];
        }
        return $out;
    }

    private function sanitizeDisplayBaseName(string $originalFilename): string
    {
        $base = pathinfo($originalFilename, PATHINFO_FILENAME);
        $base = trim((string)$base);
        if ($base === '') $base = 'datei';

        $base = str_replace(' ', '-', $base);
        $base = preg_replace('/[^a-zA-Z0-9._-]+/u', '-', $base) ?? $base;
        $base = trim($base, '-_.');

        if ($base === '') $base = 'datei';

        if (mb_strlen($base) > 120) {
            $base = mb_substr($base, 0, 120);
        }
        return $base;
    }

    private function normalizeFolderId(int $folderId): int
    {
        if ($folderId <= 0) return 1; // Root
        return $folderId;
    }

    /**
     * @return array{w:int,h:int}|null
     */
    private function detectDimensions(string $tmpFile, string $ext): ?array
    {
        if ($ext === 'svg') {
            $svg = @file_get_contents($tmpFile);
            if (!is_string($svg) || $svg === '') return null;

            $w = null; $h = null;
            if (preg_match('/\bwidth\s*=\s*["\']\s*([0-9.]+)\s*(px)?\s*["\']/i', $svg, $m)) {
                $w = (int)floor((float)$m[1]);
            }
            if (preg_match('/\bheight\s*=\s*["\']\s*([0-9.]+)\s*(px)?\s*["\']/i', $svg, $m)) {
                $h = (int)floor((float)$m[1]);
            }
            if ($w && $h) return ['w'=>$w, 'h'=>$h];

            if (preg_match('/\bviewBox\s*=\s*["\']\s*[0-9.\-]+\s+[0-9.\-]+\s+([0-9.\-]+)\s+([0-9.\-]+)\s*["\']/i', $svg, $m)) {
                $w = (int)floor((float)$m[1]);
                $h = (int)floor((float)$m[2]);
                if ($w && $h) return ['w'=>$w, 'h'=>$h];
            }

            return null;
        }

        $info = @getimagesize($tmpFile);
        if (!is_array($info)) return null;

        $w = (int)($info[0] ?? 0);
        $h = (int)($info[1] ?? 0);
        if ($w > 0 && $h > 0) return ['w' => $w, 'h' => $h];

        return null;
    }

    private function optimizeRasterImageToTempFile(string $tmpFile, string $ext, int $maxEdge): string
    {
        $ext = strtolower($ext);

        if (!function_exists('getimagesize')) {
            throw new \RuntimeException('Bildverarbeitung nicht verfügbar (getimagesize fehlt).');
        }

        $info = @getimagesize($tmpFile);
        if (!is_array($info)) {
            throw new \RuntimeException('Bild konnte nicht gelesen werden.');
        }

        $w = (int)($info[0] ?? 0);
        $h = (int)($info[1] ?? 0);
        if ($w <= 0 || $h <= 0) {
            throw new \RuntimeException('Bildabmessungen ungültig.');
        }

        // Loader
        $src = null;
        if ($ext === 'jpg' || $ext === 'jpeg') {
            if (!function_exists('imagecreatefromjpeg')) throw new \RuntimeException('GD JPEG fehlt.');
            $src = @imagecreatefromjpeg($tmpFile);
        } elseif ($ext === 'png') {
            if (!function_exists('imagecreatefrompng')) throw new \RuntimeException('GD PNG fehlt.');
            $src = @imagecreatefrompng($tmpFile);
        } elseif ($ext === 'webp') {
            if (!function_exists('imagecreatefromwebp')) throw new \RuntimeException('GD WebP fehlt.');
            $src = @imagecreatefromwebp($tmpFile);
        }

        if (!is_resource($src) && !($src instanceof \GdImage)) {
            throw new \RuntimeException('Bild konnte nicht decodiert werden.');
        }

        $newW = $w;
        $newH = $h;

        $max = max($w, $h);
        if ($max > $maxEdge) {
            $scale = $maxEdge / $max;
            $newW = (int)max(1, (int)round($w * $scale));
            $newH = (int)max(1, (int)round($h * $scale));
        }

        // Zielbild
        $dst = imagecreatetruecolor($newW, $newH);

        // Alpha für PNG/WebP erhalten
        if ($ext === 'png' || $ext === 'webp') {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
            imagefilledrectangle($dst, 0, 0, $newW, $newH, $transparent);
        }

        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);

        $out = tempnam(sys_get_temp_dir(), 'img_');
        if ($out === false) {
            throw new \RuntimeException('Tempfile konnte nicht erstellt werden.');
        }

        $ok = false;
        if ($ext === 'jpg' || $ext === 'jpeg') {
            // Qualität 82 ist ein guter Default für starke Reduktion ohne sichtbaren Schaden
            $ok = imagejpeg($dst, $out, 82);
        } elseif ($ext === 'png') {
            // PNG compression 0..9 (6 ist guter Kompromiss)
            $ok = imagepng($dst, $out, 6);
        } elseif ($ext === 'webp') {
            // WebP Qualität (0..100)
            $ok = imagewebp($dst, $out, 80);
        }

        imagedestroy($src);
        imagedestroy($dst);

        if (!$ok) {
            @unlink($out);
            throw new \RuntimeException('Bild konnte nicht optimiert werden.');
        }

        return $out;
    }

    private function sanitizeSvgToTempFile(string $tmpFile): string
    {
        $svg = @file_get_contents($tmpFile);
        if (!is_string($svg) || trim($svg) === '') {
            throw new \RuntimeException('SVG konnte nicht gelesen werden.');
        }

        $svg = preg_replace('/<\s*script\b[^>]*>.*?<\s*\/\s*script\s*>/is', '', $svg) ?? $svg;
        $svg = preg_replace('/<\s*foreignObject\b[^>]*>.*?<\s*\/\s*foreignObject\s*>/is', '', $svg) ?? $svg;
        $svg = preg_replace('/<\s*(animate|set|animateTransform)\b[^>]*>.*?<\s*\/\s*\1\s*>/is', '', $svg) ?? $svg;
        $svg = preg_replace('/<\s*(animate|set|animateTransform)\b[^>]*\/\s*>/is', '', $svg) ?? $svg;
        $svg = preg_replace('/<\s*use\b[^>]*\b(?:href|xlink:href)\s*=\s*([\'"])[^\'"]*\1[^>]*>.*?<\s*\/\s*use\s*>/is', '', $svg) ?? $svg;
        $svg = preg_replace('/<\s*use\b[^>]*\b(?:href|xlink:href)\s*=\s*([\'"])[^\'"]*\1[^>]*\/\s*>/is', '', $svg) ?? $svg;
        $svg = preg_replace('/<\s*image\b[^>]*\b(?:href|xlink:href)\s*=\s*([\'"])\s*data:[^\'"]*\1[^>]*>.*?<\s*\/\s*image\s*>/is', '', $svg) ?? $svg;
        $svg = preg_replace('/<\s*image\b[^>]*\b(?:href|xlink:href)\s*=\s*([\'"])\s*data:[^\'"]*\1[^>]*\/\s*>/is', '', $svg) ?? $svg;

        $svg = preg_replace('/\son[a-z]+\s*=\s*"[^"]*"/i', '', $svg) ?? $svg;
        $svg = preg_replace("/\son[a-z]+\s*=\s*'[^']*'/i", '', $svg) ?? $svg;

        $svg = preg_replace('/\b(href|xlink:href)\s*=\s*([\'"])\s*javascript:[^\'"]*\2/i', '$1=$2$2', $svg) ?? $svg;
        $svg = preg_replace('/\b(href|xlink:href)\s*=\s*([\'"])\s*(https?:|data:)[^\'"]*\2/i', '$1=$2$2', $svg) ?? $svg;

        if (stripos($svg, '<svg') === false) {
            throw new \RuntimeException('SVG ungültig.');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'svg_');
        if ($tmp === false) {
            throw new \RuntimeException('Tempfile konnte nicht erstellt werden.');
        }
        file_put_contents($tmp, $svg);
        return $tmp;
    }

    private function finalizeStorageFields(int $id, string $finalStorageFilename, ?int $w, ?int $h, int $sizeBytes): void
    {
        $st = $this->pdo->prepare("
            UPDATE media_items
            SET storage_filename = :s,
                width = :w,
                height = :h,
                size_bytes = :sb
            WHERE id = :id
            LIMIT 1
        ");
        $st->execute([
            ':s'  => $finalStorageFilename,
            ':w'  => $w,
            ':h'  => $h,
            ':id' => $id,
            ':sb' => $sizeBytes,
        ]);
    }

    public function getStoragePathForMedia(array $row): string
    {
        $storage = (string)($row['storage_filename'] ?? '');
        if ($storage === '') {
            return '';
        }
        return $this->mediaStorageDir() . '/' . $storage;
    }

    public function rotateStoredMedia(array $row, int $degrees): void
    {
        $id = (int)($row['id'] ?? 0);
        $ext = strtolower((string)($row['ext'] ?? ''));
        if ($id <= 0) {
            throw new \RuntimeException('Ungültige Medien-ID.');
        }
        if (!in_array($degrees, [90, 180, 270], true)) {
            throw new \RuntimeException('Ungültiger Rotationswinkel.');
        }
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            throw new \RuntimeException('Drehen ist nur für JPG, PNG und WebP möglich.');
        }

        $path = $this->getStoragePathForMedia($row);
        if ($path === '' || !is_file($path)) {
            throw new \RuntimeException('Datei wurde nicht gefunden.');
        }

        $src = null;
        if (in_array($ext, ['jpg', 'jpeg'], true)) {
            if (!function_exists('imagecreatefromjpeg')) {
                throw new \RuntimeException('GD JPEG-Unterstützung fehlt.');
            }
            $src = @imagecreatefromjpeg($path);
        } elseif ($ext === 'png') {
            if (!function_exists('imagecreatefrompng')) {
                throw new \RuntimeException('GD PNG-Unterstützung fehlt.');
            }
            $src = @imagecreatefrompng($path);
        } else {
            if (!function_exists('imagecreatefromwebp')) {
                throw new \RuntimeException('GD WebP-Unterstützung fehlt.');
            }
            $src = @imagecreatefromwebp($path);
        }

        if (!is_resource($src) && !($src instanceof \GdImage)) {
            throw new \RuntimeException('Bild konnte nicht gelesen werden.');
        }

        // imagerotate rotiert gegen den Uhrzeigersinn.
        $angle = match ($degrees) {
            90 => -90,
            180 => 180,
            default => 90,
        };

        $bg = imagecolorallocatealpha($src, 0, 0, 0, 127);
        $rotated = @imagerotate($src, $angle, $bg);
        imagedestroy($src);

        if (!is_resource($rotated) && !($rotated instanceof \GdImage)) {
            throw new \RuntimeException('Bild konnte nicht gedreht werden.');
        }

        if ($ext === 'png' || $ext === 'webp') {
            imagealphablending($rotated, false);
            imagesavealpha($rotated, true);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'rot_');
        if ($tmp === false) {
            imagedestroy($rotated);
            throw new \RuntimeException('Temporäre Datei konnte nicht erstellt werden.');
        }

        $ok = false;
        if (in_array($ext, ['jpg', 'jpeg'], true)) {
            $ok = imagejpeg($rotated, $tmp, 88);
        } elseif ($ext === 'png') {
            $ok = imagepng($rotated, $tmp, 6);
        } else {
            $ok = imagewebp($rotated, $tmp, 82);
        }

        imagedestroy($rotated);

        if (!$ok) {
            @unlink($tmp);
            throw new \RuntimeException('Gedrehtes Bild konnte nicht gespeichert werden.');
        }

        if (!@copy($tmp, $path)) {
            @unlink($tmp);
            throw new \RuntimeException('Datei konnte nicht aktualisiert werden.');
        }
        @chmod($path, 0644);

        $dim = @getimagesize($path);
        $w = is_array($dim) ? (int)($dim[0] ?? 0) : 0;
        $h = is_array($dim) ? (int)($dim[1] ?? 0) : 0;
        $bytes = (int)@filesize($path);
        if ($bytes < 0) $bytes = 0;

        $st = $this->pdo->prepare("
            UPDATE media_items
            SET width = :w,
                height = :h,
                size_bytes = :sb,
                updated_at = NOW()
            WHERE id = :id
            LIMIT 1
        ");
        $st->execute([
            ':w' => $w > 0 ? $w : null,
            ':h' => $h > 0 ? $h : null,
            ':sb' => $bytes,
            ':id' => $id,
        ]);

        @unlink($tmp);
    }
}
