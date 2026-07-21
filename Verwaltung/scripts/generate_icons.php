<?php
declare(strict_types=1);

// Einmaliger Helfer: Generiert icon-192.png und icon-512.png
// Ausführen mit: php scripts/generate_icons.php

$iconsDir = dirname(__DIR__) . '/public/icons';
if (!is_dir($iconsDir)) {
    mkdir($iconsDir, 0755, true);
}

foreach ([192, 512] as $size) {
    $img = imagecreatetruecolor($size, $size);
    $bg    = imagecolorallocate($img, 37, 99, 235);
    $fg    = imagecolorallocate($img, 255, 255, 255);
    imagefill($img, 0, 0, $bg);

    $fontSize = (int)($size * 0.45);
    $fontFile = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';
    if (function_exists('imagettftext') && is_file($fontFile)) {
        $bbox = imagettfbbox($fontSize, 0, $fontFile, 'D');
        $x = (int)(($size - ($bbox[2] - $bbox[0])) / 2);
        $y = (int)(($size - ($bbox[1] - $bbox[7])) / 2) + ($fontSize);
        imagettftext($img, $fontSize, 0, $x, $y, $fg, $fontFile, 'D');
    } else {
        $gd_size = ($size >= 256) ? 5 : 4;
        $char_w = imagefontwidth($gd_size);
        $char_h = imagefontheight($gd_size);
        imagestring($img, $gd_size, (int)(($size - $char_w) / 2), (int)(($size - $char_h) / 2), 'D', $fg);
    }

    $path = $iconsDir . '/icon-' . $size . '.png';
    imagepng($img, $path);
    imagedestroy($img);
    echo "Erstellt: {$path}\n";
}
echo "Fertig. Ersetze die PNGs später durch echte Icons.\n";
