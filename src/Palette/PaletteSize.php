<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Palette;

/**
 * A font size of the palette (`ut-fs-<px>`), standing for the close sizes found in the contents.
 */
final readonly class PaletteSize
{
    /**
     * @param int                $pixels  Size in pixels, as the class name carries it
     * @param array<string, int> $members Sizes the palette size stands for, with the contents which use them
     */
    public function __construct(
        public int $pixels,
        public array $members,
    ) {
    }

    public function getClass(): string
    {
        return 'ut-fs-' . $this->pixels;
    }

    public function getUsage(): int
    {
        return array_sum($this->members);
    }
}
