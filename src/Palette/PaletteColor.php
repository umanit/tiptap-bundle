<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Palette;

/**
 * A color of the palette, standing for the close colors found in the contents.
 */
final readonly class PaletteColor
{
    /**
     * @param string             $name    Generic name (`red-1`), meant to be renamed by the application
     * @param string             $hex     `#rrggbb` of the most used color of the group
     * @param array<string, int> $members Colors the palette color stands for, with the contents which use them
     */
    public function __construct(
        public string $name,
        public string $hex,
        public array $members,
    ) {
    }

    public function getUsage(): int
    {
        return array_sum($this->members);
    }
}
