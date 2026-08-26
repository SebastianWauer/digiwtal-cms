<?php
declare(strict_types=1);

namespace App\Http;

final class Redirect
{
    public static function to(string $path, int $code = 302): void
    {
        if ($path === '') $path = '/';
        if ($path[0] !== '/') $path = '/' . $path;
        if (function_exists('cms_base_path')) {
            $base = cms_base_path();
            // Manche Aufrufer (z.B. "next"-Redirects aus REQUEST_URI) liefern
            // den Pfad bereits inkl. Basis-Pfad -> nicht doppelt voranstellen.
            if ($base !== '' && !str_starts_with($path, $base . '/') && $path !== $base) {
                $path = $base . $path;
            }
        }

        if (headers_sent($file, $line)) {
            // Das ist der Jackpot: jetzt weißt du genau, welche Datei Output macht
            echo "Redirect blocked: headers already sent in {$file}:{$line}";
            exit;
        }

        header('Location: ' . $path, true, $code);
        exit;
    }
}
