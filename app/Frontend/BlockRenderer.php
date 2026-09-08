<?php
declare(strict_types=1);

namespace App\Frontend;

use App\Core\FrontendSource;

final class BlockRenderer
{
    public function renderBlock(array $block): string
    {
        $type = (string)($block['type'] ?? '');
        if ($type === '') {
            return '';
        }

        // Registrierte Blocktypen verwenden Bindestriche und Unterstriche. Beides
        // ist pfadsicher; Punkte und Slashes bleiben fuer Path-Traversal gesperrt.
        if (!preg_match('/^[a-z0-9_-]+$/', $type)) {
            return '';
        }

        $tpl = FrontendSource::file('themes/default/blocks/' . $type . '.php');
        if ($tpl === null) {
            return '';
        }

        ob_start();
        require $tpl;  // $block ist im Scope verfügbar
        return (string)ob_get_clean();
    }
}
