<?php
declare(strict_types=1);

namespace App\Frontend;

final class BlockRenderer
{
    public function renderBlock(array $block): string
    {
        $type = (string)($block['type'] ?? '');
        if ($type === '') {
            return '';
        }

        // Nur alphanumerische Zeichen + Bindestriche erlaubt (verhindert Path-Traversal)
        if (!preg_match('/^[a-z0-9\-]+$/', $type)) {
            return '';
        }

        $tpl = dirname(__DIR__, 3) . '/Frontend/themes/default/blocks/' . $type . '.php';
        if (!is_file($tpl)) {
            return '';
        }

        ob_start();
        require $tpl;  // $block ist im Scope verfügbar
        return (string)ob_get_clean();
    }
}
