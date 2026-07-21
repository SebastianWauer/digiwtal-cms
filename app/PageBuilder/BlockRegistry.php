<?php
declare(strict_types=1);

namespace App\PageBuilder;

use App\PageBuilder\Blocks\BlockTypeInterface;
use App\PageBuilder\Blocks\TextBlock;
use App\PageBuilder\Blocks\ImageBlock;
use App\PageBuilder\Blocks\HeroBlock;
use App\PageBuilder\Blocks\ColumnsBlock;
use App\PageBuilder\Blocks\CtaBlock;
use App\PageBuilder\Blocks\FaqBlock;
use App\PageBuilder\Blocks\VideoBlock;
use App\PageBuilder\Blocks\GalleryBlock;
use App\PageBuilder\Blocks\ContactFormBlock;
use App\PageBuilder\Blocks\ImprintBlock;
use App\PageBuilder\Blocks\EventsBlock;
use App\PageBuilder\Blocks\ThreeColumnsLayoutBlock;
use App\PageBuilder\Blocks\NewsBlock;

final class BlockRegistry
{
    /** @var BlockTypeInterface[]|null */
    private static ?array $typesCache = null;

    /** @var array<string,array>|null */
    private static ?array $defsCache = null;

    /**
     * @return BlockTypeInterface[]
     */
    private static function types(): array
    {
        if (self::$typesCache !== null) {
            return self::$typesCache;
        }

        // Einmal pro Request instanziieren (statt bei jedem definitions()-Call)
        self::$typesCache = [
            new TextBlock(),
            new ImageBlock(),
            new HeroBlock(),
            new ColumnsBlock(),
            new CtaBlock(),
            new FaqBlock(),
            new VideoBlock(),
            new GalleryBlock(),
            new ContactFormBlock(),
            new ImprintBlock(),
            new EventsBlock(),
            new ThreeColumnsLayoutBlock(),
            new NewsBlock(),
        ];

        return self::$typesCache;
    }

    /**
     * @return array<string,array{label:string,defaults:array,fields:array}>
     */
    public static function definitions(): array
    {
        if (self::$defsCache !== null) {
            return self::$defsCache;
        }

        $out = [];
        foreach (self::types() as $t) {
            $out[$t->type()] = $t->definition();
        }

        self::$defsCache = $out;
        return self::$defsCache;
    }

    public static function has(string $type): bool
    {
        $defs = self::definitions();
        return isset($defs[$type]);
    }

    /** Liefert die Block-Instanz für einen Typ, oder null wenn unbekannt. */
    public static function get(string $type): ?BlockTypeInterface
    {
        foreach (self::types() as $t) {
            if ($t->type() === $type) {
                return $t;
            }
        }
        return null;
    }

    public static function label(string $type): string
    {
        $defs = self::definitions();
        return (string)($defs[$type]['label'] ?? $type);
    }

    public static function defaults(string $type): array
    {
        $defs = self::definitions();
        $d = $defs[$type]['defaults'] ?? [];
        return is_array($d) ? $d : [];
    }

    public static function fields(string $type): array
    {
        $defs = self::definitions();
        $f = $defs[$type]['fields'] ?? [];
        return is_array($f) ? $f : [];
    }

    /**
     * Optional: Für Tests/Dev, falls du in einem Request neu laden willst.
     */
    public static function resetCache(): void
    {
        self::$typesCache = null;
        self::$defsCache = null;
    }
}

