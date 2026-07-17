<?php
declare(strict_types=1);

namespace App\PageBuilder\Blocks;

interface BlockTypeInterface
{
    public function type(): string;
    public function label(): string;

    /** @return array<string,mixed> */
    public function defaults(): array;

    /** @return array<string,array<string,mixed>> */
    public function fields(): array;

    /** @return array<string,mixed> */
    public function definition(): array;
}
