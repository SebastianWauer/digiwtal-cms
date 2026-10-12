<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require_once dirname(__DIR__) . '/app/Services/SvgSanitizer.php';
use App\Services\SvgSanitizer;

$check = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$wrap = static fn(string $content): string => '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">' . $content . '</svg>';
$png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=';
$safe = SvgSanitizer::sanitize($wrap('<image href="data:image/png;base64,' . $png . '"/>'));
$check(str_contains($safe, 'data:image/png;base64,'), 'Eingebettetes PNG wurde entfernt.');
$nested = $wrap('<script>alert(1)</script><path d="M0 0L1 1" onload="alert(1)"/>');
$safe = SvgSanitizer::sanitize($wrap('<image xlink:href="data:image/svg+xml;base64,' . base64_encode($nested) . '"/>'));
preg_match('/data:image\/svg\+xml;base64,([^"\s]+)/', $safe, $match);
$decoded = base64_decode($match[1] ?? '');
$check(str_contains($decoded, '<path') && !str_contains($decoded, '<script') && !str_contains($decoded, 'onload'), 'Verschachteltes SVG wurde nicht sicher bereinigt.');
foreach (['https://example.invalid/a.png', '//example.invalid/a.png', 'javascript:alert(1)', 'data:text/html;base64,' . base64_encode('<script/>'), 'data:image/png;base64,' . base64_encode('<svg/>'), 'data:image/jpeg;base64,' . $png] as $url) {
    $safe = SvgSanitizer::sanitize($wrap('<image href="' . $url . '"/>'));
    $check(!str_contains($safe, '<image'), 'Unsichere Bildquelle bleibt erhalten.');
}
$safe = SvgSanitizer::sanitize($wrap('<script/><foreignObject/><set attributeName="href" to="https://evil.invalid"/><g xml:base="https://evil.invalid"><use href="#local"/><path style="fill:url(https://evil.invalid/a)" onload="evil()"/></g><style>@import "https://evil.invalid/a";</style><path fill="url(#local)"/>'));
$check(!str_contains($safe, 'evil') && !str_contains($safe, 'script') && !str_contains($safe, 'foreignObject') && !str_contains($safe, '<set'), 'Aktive/externe Inhalte bleiben erhalten.');
$check(str_contains($safe, 'url(#local)') && str_contains($safe, 'href="#local"'), 'Lokale SVG-Definitionen gehen verloren.');
foreach (['<svg>', '<!DOCTYPE svg [<!ENTITY x SYSTEM "file:///etc/passwd">]>' . $wrap('&x;'), '<svg xmlns="http://example.invalid"/>'] as $invalid) {
    $rejected = false;
    try { SvgSanitizer::sanitize($invalid); } catch (RuntimeException) { $rejected = true; }
    $check($rejected, 'Ungueltiges XML oder externe Entitaet wurde akzeptiert.');
}
$deep = $wrap('<path d="M0 0L1 1"/>');
for ($i = 0; $i < 6; $i++) $deep = $wrap('<image href="data:image/svg+xml;base64,' . base64_encode($deep) . '"/>');
$safe = SvgSanitizer::sanitize($deep);
$check(strlen($safe) < strlen($deep), 'Verschachtelungsgrenze wird nicht angewendet.');
if (isset($argv[1])) {
    foreach (['gtwc-europe-mono.svg','gt4-europe-mono.svg','gt4-germany-mono.svg','dkm-germany-mono.svg','rmc-germany-mono.svg'] as $file) {
        $safe = SvgSanitizer::sanitize((string)file_get_contents(rtrim($argv[1], '/') . '/' . $file));
        $check(str_contains($safe, '<image') && str_contains($safe, 'data:image/'), 'Logo-Inhalt verloren: ' . $file);
    }
}
echo "PASS: eingebettete Rasterbilder und SVGs; fuenf Original-Logos; aktive/externe Inhalte gesperrt; MIME-Pruefung; XML- und Verschachtelungsschutz.\n";
