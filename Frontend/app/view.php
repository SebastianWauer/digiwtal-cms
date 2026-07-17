<?php
declare(strict_types=1);

/**
 * Escape HTML special characters
 */
function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Render a template file with given variables
 */
function render(string $file, array $vars = []): void {
    if (str_contains($file, '..')) {
        throw new RuntimeException('Invalid template path');
    }
    $path = __DIR__ . '/../' . $file;
    if (!is_file($path)) {
        throw new RuntimeException("Template not found: {$file}");
    }
    extract($vars, EXTR_SKIP);
    require $path;
}

/**
 * Normalize focus values from either legacy -1..1 or percent-like ranges.
 */
function focus_to_percent(mixed $raw, float $default = 50.0): float
{
    if ($raw === null || $raw === '') {
        return $default;
    }
    $v = (float)$raw;

    // Legacy normalized range.
    if ($v < 0.0) {
        $v = (($v + 1.0) / 2.0) * 100.0;
    } elseif ($v <= 1.0) {
        // Common normalized 0..1 range.
        $v = $v * 100.0;
    }

    if ($v < 0.0) $v = 0.0;
    if ($v > 100.0) $v = 100.0;
    return $v;
}
