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

        // Nur alphanumerische Zeichen + Bindestriche erlaubt (verhindert Path-Traversal)
        if (!preg_match('/^[a-z0-9\-]+$/', $type)) {
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
