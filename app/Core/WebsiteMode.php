<?php
declare(strict_types=1);
namespace App\Core;

final class WebsiteMode
{
    public static function get(): string
    {
        return Env::get('WEBSITE_MODE', 'multipage') === 'onepager' ? 'onepager' : 'multipage';
    }

    public static function isOnepager(): bool { return self::get() === 'onepager'; }
}
