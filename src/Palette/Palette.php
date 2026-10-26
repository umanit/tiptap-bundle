<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Palette;

/**
 * The colors and the font sizes proposed for the contents of an application, and what does not fit in them.
 */
final readonly class Palette
{
    /**
     * @param list<PaletteColor>                $colors    Shared by the text and the background colors
     * @param list<PaletteSize>                 $sizes
     * @param array<string, array<string, int>> $unmapped  Values the palette cannot express (a color name, an `em`
     *                                                     size…), by property, with the contents which use them
     */
    public function __construct(
        public array $colors,
        public array $sizes,
        public array $unmapped = [],
    ) {
    }

    /**
     * The map of the legacy import mode (`legacyStyles` option of the editor, `--legacy-styles` of the CLI): every
     * inline value found in the contents, to the class which replaces it.
     *
     * @return array{
     *     color: array<string, string>,
     *     backgroundColor: array<string, string>,
     *     fontSize: array<string, string>,
     * }
     */
    public function toLegacyStyles(): array
    {
        $legacy = ['color' => [], 'backgroundColor' => [], 'fontSize' => []];

        foreach ($this->colors as $color) {
            foreach (array_keys($color->members) as $value) {
                $legacy['color'][$value] = 'ut-color-' . $color->name;
                $legacy['backgroundColor'][$value] = 'ut-bg-' . $color->name;
            }
        }

        foreach ($this->sizes as $size) {
            foreach (array_keys($size->members) as $value) {
                $legacy['fontSize'][$value] = $size->getClass();
            }
        }

        return $legacy;
    }
}
