<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Palette;

/**
 * Builds a palette from an inventory: groups the close colors and the close sizes, and names the result.
 *
 * The groups are made greedily: the most used value takes every unassigned value which is close enough, then the most
 * used of the remaining ones does the same. The value of a group is therefore a real value of the contents, not an
 * average, and the group of a very common color does not drift.
 */
final readonly class PaletteBuilder
{
    // Hue ranges in degrees, from the first one which is greater than the hue
    private const array HUES = [
        15  => 'red',
        45  => 'orange',
        70  => 'yellow',
        165 => 'green',
        200 => 'cyan',
        255 => 'blue',
        290 => 'purple',
        345 => 'pink',
        360 => 'red',
    ];

    /**
     * @param int<0, max> $maxColorDistance Largest difference on a single RGB channel between two colors of a group
     * @param int<0, max> $maxSizeDistance  Largest difference in pixels between two sizes of a group
     */
    public function build(StyleInventory $inventory, int $maxColorDistance = 24, int $maxSizeDistance = 0): Palette
    {
        $colors = [];
        $sizes = [];
        $unmapped = [];

        foreach (['color', 'backgroundColor'] as $property) {
            foreach ($inventory->get($property) as $value => $count) {
                $value = (string) $value;

                if (null === $this->parseHex($value)) {
                    $unmapped[$property][$value] = $count;

                    continue;
                }

                $colors[$value] = ($colors[$value] ?? 0) + $count;
            }
        }

        foreach ($inventory->get('fontSize') as $value => $count) {
            $value = (string) $value;
            $pixels = $this->parsePixels($value);

            if (null === $pixels) {
                $unmapped['fontSize'][$value] = $count;

                continue;
            }

            $sizes[$value] = $count;
        }

        return new Palette(
            $this->name($this->groupColors($colors, $maxColorDistance)),
            $this->groupSizes($sizes, $maxSizeDistance),
            $unmapped,
        );
    }

    /**
     * @param array<string, int> $counts
     *
     * @return list<array{string, array<string, int>}> Representative and members, the most used group first
     */
    private function groupColors(array $counts, int $maxDistance): array
    {
        $groups = [];

        while ([] !== $counts) {
            $representative = $this->mostUsed($counts);
            $members = [$representative => $counts[$representative]];
            unset($counts[$representative]);

            foreach ($counts as $hex => $count) {
                if ($this->distance($representative, $hex) <= $maxDistance) {
                    $members[$hex] = $count;
                    unset($counts[$hex]);
                }
            }

            $groups[] = [$representative, $members];
        }

        return $groups;
    }

    /**
     * @param array<string, int> $counts
     */
    private function mostUsed(array $counts): string
    {
        // Ties go to the lowest value, so that two runs give the same palette
        $best = null;

        foreach ($counts as $value => $count) {
            if (
                null === $best
                || $count > $counts[$best]
                || (
                    $count === $counts[$best]
                    && strcmp((string) $value, $best) < 0
                )
            ) {
                $best = (string) $value;
            }
        }

        return $best ?? throw new \LogicException('No value to group.');
    }

    /**
     * @param list<array{string, array<string, int>}> $groups
     *
     * @return list<PaletteColor>
     */
    private function name(array $groups): array
    {
        $numbers = [];
        $colors = [];

        foreach ($groups as [$hex, $members]) {
            $family = $this->family($hex);
            $numbers[$family] = ($numbers[$family] ?? 0) + 1;

            $colors[] = new PaletteColor(\sprintf('%s-%d', $family, $numbers[$family]), $hex, $members);
        }

        return $colors;
    }

    /**
     * @param array<string, int> $counts
     *
     * @return list<PaletteSize>
     */
    private function groupSizes(array $counts, int $maxDistance): array
    {
        $sizes = [];

        while ([] !== $counts) {
            $representative = $this->mostUsed($counts);
            $pixels = $this->parsePixels($representative) ?? 0;
            $members = [$representative => $counts[$representative]];
            unset($counts[$representative]);

            foreach ($counts as $value => $count) {
                if (abs(($this->parsePixels((string) $value) ?? 0) - $pixels) <= $maxDistance) {
                    $members[(string) $value] = $count;
                    unset($counts[$value]);
                }
            }

            $sizes[] = new PaletteSize(max(1, (int) round($pixels)), $members);
        }

        // Two groups can round to the same class: they are merged into the most used one
        $merged = [];

        foreach ($sizes as $size) {
            $merged[$size->pixels] = isset($merged[$size->pixels])
                ? new PaletteSize($size->pixels, $merged[$size->pixels]->members + $size->members)
                : $size;
        }

        ksort($merged);

        return array_values($merged);
    }

    /**
     * Largest difference on a single RGB channel.
     */
    private function distance(string $a, string $b): int
    {
        $rgbA = $this->parseHex($a) ?? [0, 0, 0];
        $rgbB = $this->parseHex($b) ?? [0, 0, 0];

        return max(abs($rgbA[0] - $rgbB[0]), abs($rgbA[1] - $rgbB[1]), abs($rgbA[2] - $rgbB[2]));
    }

    /**
     * @return array{int, int, int}|null
     */
    private function parseHex(string $value): ?array
    {
        if (1 !== preg_match('/^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/', $value, $matches)) {
            return null;
        }

        return [(int) hexdec($matches[1]), (int) hexdec($matches[2]), (int) hexdec($matches[3])];
    }

    private function parsePixels(string $value): ?float
    {
        return 1 === preg_match('/^(\d*\.?\d+)px$/', $value, $matches) ? (float) $matches[1] : null;
    }

    /**
     * Generic name of the color: `white`, `grey` and `black` for the colors without a hue, a hue name otherwise.
     */
    private function family(string $hex): string
    {
        [$red, $green, $blue] = array_map(
            static fn(int $channel): float => $channel / 255,
            $this->parseHex($hex) ?? [0, 0, 0],
        );

        $max = max($red, $green, $blue);
        $min = min($red, $green, $blue);
        $delta = $max - $min;
        $lightness = ($max + $min) / 2;
        $saturation = 0.0 === $delta ? 0.0 : $delta / (1 - abs(2 * $lightness - 1));

        if ($saturation < 0.15 || $lightness < 0.08 || $lightness > 0.95) {
            return match (true) {
                $lightness > 0.92 => 'white',
                $lightness < 0.12 => 'black',
                default => 'grey',
            };
        }

        $hue = match ($max) {
            $red => 60 * fmod(($green - $blue) / $delta, 6),
            $green => 60 * (($blue - $red) / $delta + 2),
            default => 60 * (($red - $green) / $delta + 4),
        };
        $hue = fmod($hue + 360, 360);

        foreach (self::HUES as $limit => $name) {
            if ($hue < $limit) {
                return $name;
            }
        }

        return 'red';
    }
}
